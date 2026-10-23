<?php

namespace App\Services;

use App\Enums\LedgerKind;
use App\Exceptions\ContributionException;
use App\Exceptions\WalletException;
use App\Models\Allocation;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Support\Experience;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contributions et fonds de contribution (Lot 6c, D-163 à D-170, D-172). Le fonds de contribution est distinct du fonds des
 * portefeuilles (D-165) : seules les contributions « enregistrées » le forment, et toutes les sommes sont comptées par mode
 * (essai ou réel), pour que de l'argent d'essai ne donne ni badge ni fonds en mode réel.
 */
class Contributions
{
    /** Contributions de moins de cinq personnes : le rapport public n'en donne pas le nombre (anonymat). */
    public const PUBLIC_COUNT_THRESHOLD = 5;

    public function __construct(
        private readonly Wallets $wallets,
        private readonly GeniusPay $provider,
        private readonly Settings $settings,
    ) {}

    public function environment(): string
    {
        return $this->provider->mode();
    }

    public function minimum(): int
    {
        return (int) $this->settings->get('contribution_min');
    }

    public function engagementDays(): int
    {
        return (int) $this->settings->get('contribution_engagement_days');
    }

    public function threshold(): int
    {
        return (int) $this->settings->get('wallet_zumra_validation_threshold');
    }

    // ─── Contribuer ──────────────────────────────────────────────────────────────

    /**
     * Contribue depuis le portefeuille. La dépense d'une ZUMRA au-delà du seuil attend la validation du Président (D-168) : rien
     * n'est débité avant. Retourne la contribution (enregistrée, ou en attente de validation).
     *
     * @param  array{0: string, 1: ?string}  $target  [type d'orientation, identifiant]
     */
    public function fromWallet(User $actor, Wallet $wallet, int $amount, array $target = [Contribution::FUND, null]): Contribution
    {
        if (Experience::initial()) {
            throw ContributionException::unavailable();
        }

        $this->guardAmount($amount);
        $target = $this->validTarget($target);

        if (! $this->wallets->enabled() || $wallet->isClosed()) {
            throw ContributionException::unavailable();
        }

        if (! $wallet->isPerson() && $amount > $this->threshold()) {
            return $this->create($wallet, 'wallet', $amount, $target, Contribution::AWAITING, $actor);
        }

        return DB::transaction(function () use ($actor, $wallet, $amount, $target) {
            $contribution = $this->create($wallet, 'wallet', $amount, $target, Contribution::RECORDED, $actor);

            return $this->debit($contribution, $actor);
        });
    }

    /** Le Président valide une dépense de ZUMRA : le débit se fait maintenant, si le solde le permet. */
    public function validate(User $admin, Contribution $contribution): Contribution
    {
        return DB::transaction(function () use ($admin, $contribution) {
            $locked = Contribution::query()->lockForUpdate()->findOrFail($contribution->getKey());

            if (! $locked->isAwaiting()) {
                throw ContributionException::notPending();
            }

            $locked->forceFill(['status' => Contribution::RECORDED, 'decided_by' => $admin->getKey(), 'decided_at' => now()])->save();
            $this->debit($locked, $admin);
            AdminJournal::record($admin, 'contribution.validated', payload: ['zahab' => $locked->amount]);

            return $locked;
        });
    }

    public function refuse(User $admin, Contribution $contribution, ?string $reason = null): Contribution
    {
        return DB::transaction(function () use ($admin, $contribution, $reason) {
            $locked = Contribution::query()->lockForUpdate()->findOrFail($contribution->getKey());

            if (! $locked->isAwaiting()) {
                throw ContributionException::notPending();
            }

            $locked->forceFill(['status' => Contribution::REFUSED, 'decided_by' => $admin->getKey(), 'decided_at' => now(), 'note' => $reason])->save();
            AdminJournal::record($admin, 'contribution.refused', reason: $reason, payload: ['zahab' => $locked->amount]);

            return $locked;
        });
    }

    /** Un responsable retire sa demande avant la décision du Président. */
    public function withdraw(Contribution $contribution): Contribution
    {
        return DB::transaction(function () use ($contribution) {
            $locked = Contribution::query()->lockForUpdate()->findOrFail($contribution->getKey());

            if (! $locked->isAwaiting()) {
                throw ContributionException::notPending();
            }

            $locked->forceFill(['status' => Contribution::WITHDRAWN])->save();

            return $locked;
        });
    }

    /** Une contribution confirmée par le prestataire (paiement direct) entre au fonds, une seule fois. Appelé sous le verrou du paiement. */
    public function recordDirect(Payment $payment): Contribution
    {
        $existing = Contribution::query()->where('payment_id', $payment->getKey())->first();

        if ($existing) {
            return $existing;
        }

        return $this->create($payment->wallet, 'direct', $payment->amount, [$payment->target_type, $payment->target_id], Contribution::RECORDED, User::query()->find($payment->initiated_by), $payment);
    }

    /** Un paiement direct remboursé par le prestataire sort la contribution du fonds. */
    public function refundDirect(Payment $payment): void
    {
        Contribution::query()->where('payment_id', $payment->getKey())->where('status', Contribution::RECORDED)->update(['status' => Contribution::REFUNDED]);
    }

    /**
     * Orientation voulue : le fonds commun, une ZUMRA engagée, ou un projet d'une ZUMRA engagée (D-166).
     *
     * @param  array{0: string, 1: ?string}  $target
     * @return array{0: string, 1: ?string}
     */
    public function validTarget(array $target): array
    {
        [$type, $id] = [$target[0] ?? Contribution::FUND, $target[1] ?? null];

        if ($type === Contribution::FUND && $id === null) {
            return [Contribution::FUND, null];
        }

        if ($type === Contribution::ZUMRA && $id !== null && $this->isEngaged(Zumra::query()->find($id))) {
            return [$type, $id];
        }

        if ($type === Contribution::PROJECT && $id !== null && ($project = Project::query()->with('zumra')->find($id)) && $this->isEngaged($project->zumra)) {
            return [$type, $id];
        }

        throw ContributionException::target();
    }

    // ─── Engagement ──────────────────────────────────────────────────────────────

    /** Une ZUMRA est engagée quand elle a contribué (contribution enregistrée) dans la durée d'engagement (D-172). */
    public function isEngaged(?Zumra $zumra): bool
    {
        return $zumra !== null && $this->engagedZumraIds([$zumra->getKey()]) !== [];
    }

    /**
     * @param  array<int, string>|null  $among  limite la recherche (une seule requête pour une liste)
     * @return array<int, string>
     */
    public function engagedZumraIds(?array $among = null): array
    {
        return $this->engagedOwnerIds(Wallet::ZUMRA, $among);
    }

    /** Une personne est « Contributeur » selon la même règle ; son badge ne s'affiche que si elle l'a choisi (D-170). */
    public function isContributor(User $user): bool
    {
        return $this->engagedOwnerIds(Wallet::PERSON, [$user->getKey()]) !== [];
    }

    /** Badge « Contributeur » affiché sur le profil : contributeur actif ET choix de la personne. */
    public function showsBadge(User $user): bool
    {
        return ! Experience::initial() && $this->isContributor($user) && Wallet::query()->where(['owner_type' => Wallet::PERSON, 'owner_id' => $user->getKey(), 'show_badge' => true])->exists();
    }

    /** Finançable : engagée ET reconnue par GAMAD (D-167). */
    public function isFinanceable(?Zumra $zumra): bool
    {
        return $zumra !== null && $zumra->isRecognized() && $this->isEngaged($zumra);
    }

    /** Date de la dernière contribution enregistrée d'un portefeuille, dans le mode courant. */
    public function lastContributionAt(Wallet $wallet): ?Carbon
    {
        $at = Contribution::query()->where('wallet_id', $wallet->getKey())->where('status', Contribution::RECORDED)->where('environment', $this->environment())->max('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    /** Rappel mensuel demandé et aucune contribution ce mois-ci : la demande de paiement apparaît dans « À faire maintenant ». */
    public function reminderDue(Wallet $wallet): bool
    {
        if (! $wallet->monthly_reminder || $wallet->isClosed()) {
            return false;
        }

        $last = $this->lastContributionAt($wallet);

        return $last === null || $last->lt(now()->startOfMonth());
    }

    /**
     * @param  array<int, string>|null  $among
     * @return array<int, string>
     */
    private function engagedOwnerIds(string $type, ?array $among): array
    {
        // Portefeuilles fermés : aucun état ni badge, comme si le module n'existait pas.
        if (! $this->wallets->enabled()) {
            return [];
        }

        return Contribution::query()
            ->join('wallets', 'wallets.id', '=', 'contributions.wallet_id')
            ->where('wallets.owner_type', $type)
            ->when($among !== null, fn ($q) => $q->whereIn('wallets.owner_id', $among))
            ->where('contributions.status', Contribution::RECORDED)
            ->where('contributions.environment', $this->environment())
            ->where('contributions.created_at', '>=', now()->subDays($this->engagementDays()))
            ->distinct()->pluck('wallets.owner_id')->all();
    }

    // ─── Fonds, affectation, rapports ────────────────────────────────────────────

    /** @return array{received: int, refunded: int, allocated: int, paid: int, available: int, contributors: int} */
    public function fund(): array
    {
        $env = $this->environment();
        $recorded = Contribution::query()->where('environment', $env)->where('status', Contribution::RECORDED);
        $received = (int) (clone $recorded)->sum('amount');
        $allocated = (int) Allocation::query()->where('environment', $env)->sum('amount');

        return [
            'received' => $received,
            'refunded' => (int) Contribution::query()->where('environment', $env)->where('status', Contribution::REFUNDED)->sum('amount'),
            'allocated' => $allocated,
            'paid' => (int) Allocation::query()->where('environment', $env)->where('status', Allocation::PAID)->sum('amount'),
            'available' => $received - $allocated,
            'contributors' => (clone $recorded)->distinct('wallet_id')->count('wallet_id'),
        ];
    }

    /** Contributions enregistrées par mois (du plus récent au plus ancien) : [mois 'Y-m' => total]. @return array<string, int> */
    public function monthly(int $months = 12): array
    {
        return Contribution::query()->where('environment', $this->environment())->where('status', Contribution::RECORDED)
            ->selectRaw("to_char(created_at at time zone 'Africa/Abidjan', 'YYYY-MM') as month, sum(amount) as total")
            ->groupBy('month')->orderByDesc('month')->limit($months)->pluck('total', 'month')
            ->map(fn ($total) => (int) $total)->all();
    }

    /**
     * Orientations reçues, par ZUMRA ou projet, sans nom de personne : une aide à la décision du Président.
     *
     * @return array<int, array{type: string, name: string, total: int}>
     */
    public function orientations(): array
    {
        $rows = Contribution::query()->where('environment', $this->environment())->where('status', Contribution::RECORDED)->where('target_type', '!=', Contribution::FUND)
            ->selectRaw('target_type, target_id, sum(amount) as total')->groupBy('target_type', 'target_id')->orderByDesc('total')->get();

        $zumras = Zumra::query()->whereIn('id', $rows->where('target_type', Contribution::ZUMRA)->pluck('target_id'))->pluck('name', 'id');
        $projects = Project::query()->whereIn('id', $rows->where('target_type', Contribution::PROJECT)->pluck('target_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'type' => $row->target_type,
            'name' => ($row->target_type === Contribution::ZUMRA ? $zumras : $projects)[$row->target_id] ?? 'élément supprimé',
            'total' => (int) $row->total,
        ])->all();
    }

    /** Affecte une somme du fonds à un projet d'une ZUMRA engagée et reconnue (D-167, D-168). */
    public function allocate(User $admin, Project $project, int $amount, string $purpose): Allocation
    {
        return DB::transaction(function () use ($admin, $project, $amount, $purpose) {
            // Une seule affectation à la fois : le disponible est lu sous ce verrou.
            DB::select("select pg_advisory_xact_lock(hashtext('gamad-fund'))");

            $project->loadMissing('zumra');

            if (! $this->isFinanceable($project->zumra)) {
                throw ContributionException::notFinanceable();
            }

            $available = $this->fund()['available'];

            if ($amount < 1 || $amount > $available) {
                throw ContributionException::exceedsFund($available);
            }

            $allocation = Allocation::query()->create([
                'project_id' => $project->getKey(), 'zumra_id' => $project->zumra->getKey(),
                'project_name' => $project->name, 'zumra_name' => $project->zumra->name,
                'amount' => $amount, 'purpose' => $purpose, 'status' => Allocation::DECIDED, 'environment' => $this->environment(), 'decided_by' => $admin->getKey(),
            ]);

            AdminJournal::record($admin, 'allocation.decided', $project->zumra, reason: $purpose, payload: ['zahab' => $amount, 'projet' => $project->name]);

            return $allocation;
        });
    }

    /** Le versement hors de la plateforme est fait : le Président l'inscrit, avec une note libre. */
    public function markPaid(User $admin, Allocation $allocation, ?string $note = null): Allocation
    {
        return DB::transaction(function () use ($admin, $allocation, $note) {
            $locked = Allocation::query()->lockForUpdate()->findOrFail($allocation->getKey());

            if ($locked->isPaid()) {
                return $locked;
            }

            $locked->forceFill(['status' => Allocation::PAID, 'paid_at' => now(), 'payment_note' => $note])->save();
            AdminJournal::record($admin, 'allocation.paid', reason: $note, payload: ['zahab' => $locked->amount, 'projet' => $locked->project_name]);

            return $locked;
        });
    }

    /**
     * Rapport public, sans nom de personne : sommes, nombre de contributeurs (seulement à partir de cinq), affectations.
     *
     * @return array<string, mixed>
     */
    public function publicReport(): array
    {
        $fund = $this->fund();

        return [
            'received' => $fund['received'],
            'allocated' => $fund['allocated'],
            'paid' => $fund['paid'],
            'available' => $fund['available'],
            'contributors' => $fund['contributors'] >= self::PUBLIC_COUNT_THRESHOLD ? $fund['contributors'] : null,
            'monthly' => $this->monthly(),
            'allocations' => Allocation::query()->where('environment', $this->environment())->orderByDesc('created_at')->limit(50)->get(['zumra_name', 'project_name', 'purpose', 'amount', 'status', 'created_at', 'paid_at']),
            'sandbox' => $this->environment() === 'sandbox',
        ];
    }

    /** Chiffres réels d'un portefeuille, pour son rapport d'impact (D-166) : rien d'estimé. @return array{total: int, count: int, months: int, since: ?\Illuminate\Support\Carbon} */
    public function impact(Wallet $wallet): array
    {
        $base = Contribution::query()->where('wallet_id', $wallet->getKey())->where('status', Contribution::RECORDED)->where('environment', $this->environment());

        return [
            'total' => (int) (clone $base)->sum('amount'),
            'count' => (clone $base)->count(),
            'months' => (clone $base)->distinct()->count(DB::raw("to_char(created_at at time zone 'Africa/Abidjan', 'YYYY-MM')")),
            'since' => ($first = (clone $base)->min('created_at')) ? Carbon::parse($first) : null,
        ];
    }

    // ─── Interne ─────────────────────────────────────────────────────────────────

    private function guardAmount(int $amount): void
    {
        if ($amount < $this->minimum()) {
            throw ContributionException::minimum($this->minimum());
        }
    }

    /** @param  array{0: string, 1: ?string}  $target */
    private function create(Wallet $wallet, string $source, int $amount, array $target, string $status, ?User $actor, ?Payment $payment = null): Contribution
    {
        return Contribution::query()->create([
            'wallet_id' => $wallet->getKey(), 'source' => $source, 'amount' => $amount, 'status' => $status,
            'environment' => $this->environment(), 'target_type' => $target[0], 'target_id' => $target[1],
            'requested_by' => $actor?->getKey(), 'payment_id' => $payment?->getKey(),
        ]);
    }

    /** Le débit du portefeuille, au registre : « contribution:<id> » ne peut exister qu'une fois. */
    private function debit(Contribution $contribution, User $actor): Contribution
    {
        try {
            $entry = $this->wallets->post($contribution->wallet, LedgerKind::Contribution, $contribution->amount, 'contribution:'.$contribution->getKey(), $actor);
        } catch (WalletException $e) {
            throw $e->getMessage() === WalletException::insufficient()->getMessage() ? ContributionException::insufficient() : ContributionException::unavailable();
        }

        $contribution->forceFill(['ledger_entry_id' => $entry->getKey()])->save();

        return $contribution;
    }
}
