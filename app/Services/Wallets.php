<?php

namespace App\Services;

use App\Enums\LedgerKind;
use App\Exceptions\WalletException;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Support\Experience;
use App\Support\WalletModule;
use Illuminate\Support\Facades\DB;

/**
 * Portefeuilles ZAHAB (D-164, D-172) : la seule porte d'écriture du registre. Le solde est celui du dernier mouvement ; chaque
 * mouvement verrouille le portefeuille, vérifie le signe, le plafond et l'absence de solde négatif, puis s'ajoute au registre.
 */
class Wallets
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Les portefeuilles sont masqués tant que le Président ne les a pas ouverts. Dans le parcours actif, c'est l'état du service Zahab
     * Wallet (D-218) ; pour une personne en test privé, l'accès se vérifie aussi par personne (WalletModule::accessibleTo).
     */
    public function enabled(): bool
    {
        return Experience::initial() ? WalletModule::state() !== WalletModule::OFF : $this->settings->get('wallet_enabled') === '1';
    }

    /** Les relevés antérieurs au recentrage restent consultables selon l'ancien réglage, indépendamment du service Zahab Wallet (D-218). */
    public function legacyEnabled(): bool
    {
        return $this->settings->get('wallet_enabled') === '1';
    }

    /** Le portefeuille d'un titulaire, s'il l'a activé ; sinon rien (aucune création automatique, D-218). */
    public function existing(string $ownerType, string $ownerId): ?Wallet
    {
        return Wallet::query()->where(['owner_type' => $ownerType, 'owner_id' => $ownerId])->first();
    }

    /**
     * Activation volontaire (D-218) : une personne vérifiée active son Zahab Wallet ; le fondateur ou un responsable active celui de sa
     * ZUMRA (un bien commun de ses responsables). Rien ne se crée tout seul. Sans effet si le portefeuille existe déjà.
     */
    public function activate(User $actor, ?Zumra $zumra = null): Wallet
    {
        abort_unless(WalletModule::accessibleTo($actor) && $actor->hasVerifiedEmail(), 404);

        if ($zumra) {
            abort_unless(in_array($zumra->getKey(), $actor->managedZumraIds(), true), 404);

            return Wallet::query()->firstOrCreate(['owner_type' => Wallet::ZUMRA, 'owner_id' => $zumra->getKey()]);
        }

        return Wallet::query()->firstOrCreate(['owner_type' => Wallet::PERSON, 'owner_id' => $actor->getKey()]);
    }

    public function forPerson(User $user): Wallet
    {
        return $this->forOwner(Wallet::PERSON, $user->getKey());
    }

    public function forZumra(Zumra $zumra): Wallet
    {
        return $this->forOwner(Wallet::ZUMRA, $zumra->getKey());
    }

    public function balance(Wallet $wallet): int
    {
        return (int) (LedgerEntry::query()->where('wallet_id', $wallet->getKey())->orderByDesc('sequence')->value('balance_after') ?? 0);
    }

    public function ceilingFor(Wallet $wallet): int
    {
        return (int) $this->settings->get($wallet->isPerson() ? 'wallet_max_balance_person' : 'wallet_max_balance_zumra');
    }

    /**
     * @param  int  $amount  Montant en ZAHAB, toujours positif : le signe vient de la nature du mouvement.
     */
    public function post(Wallet $wallet, LedgerKind $kind, int $amount, ?string $reference = null, ?User $actor = null): LedgerEntry
    {
        if ($amount < 1) {
            throw WalletException::invalidAmount();
        }

        return DB::transaction(function () use ($wallet, $kind, $amount, $reference, $actor) {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->getKey());

            if ($locked->isClosed()) {
                throw WalletException::closed();
            }

            $last = LedgerEntry::query()->where('wallet_id', $locked->getKey())->orderByDesc('sequence')->first();
            $signed = $kind->credits() ? $amount : -$amount;
            $after = ($last?->balance_after ?? 0) + $signed;

            if ($after < 0) {
                throw WalletException::insufficient();
            }

            // Le plafond ne protège que des recharges : une somme qui revient (reversement annulé) n'est jamais refusée.
            if ($kind === LedgerKind::Recharge && $after > ($max = $this->ceilingFor($locked))) {
                throw WalletException::ceiling($max);
            }

            return LedgerEntry::query()->create([
                'wallet_id' => $locked->getKey(),
                'sequence' => ($last?->sequence ?? 0) + 1,
                'kind' => $kind,
                'amount' => $signed,
                'balance_after' => $after,
                'reference' => $reference,
                'actor_id' => $actor?->getKey(),
            ]);
        });
    }

    /**
     * Un compte ne disparaît pas avec de l'argent dedans (D-172) : il n'y a ni retrait ni transfert. Vaut aussi pour la dernière
     * personne d'une ZUMRA, puisque la ZUMRA disparaît avec elle. Les portefeuilles sont verrouillés (comme dans `post()`) : sous la
     * transaction de fermeture, un mouvement concurrent attend le verdict au lieu de passer entre le contrôle et la suppression.
     */
    public function assertAccountCanBeDeleted(User $user): void
    {
        $own = Wallet::query()->where(['owner_type' => Wallet::PERSON, 'owner_id' => $user->getKey()])->lockForUpdate()->first();

        if ($own && $this->balance($own) > 0) {
            throw WalletException::notEmpty();
        }

        $alone = Zumra::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->getKey()))
            ->whereDoesntHave('memberships', fn ($q) => $q->where('user_id', '!=', $user->getKey()))
            ->pluck('id');

        foreach (Wallet::query()->where('owner_type', Wallet::ZUMRA)->whereIn('owner_id', $alone)->lockForUpdate()->get() as $wallet) {
            if ($this->balance($wallet) > 0) {
                throw WalletException::zumraNotEmpty();
            }
        }
    }

    /** Ferme les portefeuilles d'un titulaire qui disparaît (le registre, lui, reste). */
    public function closeFor(string $ownerType, string $ownerId): void
    {
        Wallet::query()->where(['owner_type' => $ownerType, 'owner_id' => $ownerId])->whereNull('closed_at')->update(['closed_at' => now()]);
    }

    private function forOwner(string $type, string $id): Wallet
    {
        $owner = ['owner_type' => $type, 'owner_id' => $id];

        if (Experience::initial()) {
            return Wallet::query()->where($owner)->firstOrFail();
        }

        return Wallet::query()->firstOrCreate($owner);
    }
}
