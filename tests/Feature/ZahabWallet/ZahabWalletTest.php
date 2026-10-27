<?php

use App\Enums\LedgerKind;
use App\Enums\NotificationKind;
use App\Enums\PayoutStatus;
use App\Exceptions\WalletException;
use App\Livewire\PagePortefeuille;
use App\Livewire\ParametresPaiement;
use App\Livewire\ParametresPortefeuille;
use App\Livewire\ReversementsAdministration;
use App\Models\AdminAction;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Services\GeniusPay;
use App\Services\Memberships;
use App\Services\Payments;
use App\Services\Payouts;
use App\Services\ServicesGamad;
use App\Services\Settings;
use App\Services\Wallets;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** D-218 : Zahab Wallet, service transversal en parcours actif : désactivé par défaut, activation volontaire, recharge, reversement. */
function ouvrirZahab(string $etat = 'public', string $testeurs = ''): User
{
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['wallet_module' => $etat, 'wallet_testers' => $testeurs], $admin);

    return $admin;
}

function destination(array $surcharge = []): array
{
    return $surcharge + ['operator' => 'wave', 'phone' => '+225 07 00 00 00 00', 'holder' => 'Awa Traoré'];
}

function reverser(User $qui, Wallet $wallet, int $montant, array $destination = []): Payout
{
    $d = destination($destination);

    return app(Payouts::class)->request($qui, $wallet, $montant, $d['operator'], $d['phone'], $d['holder']);
}

beforeEach(function () {
    $this->awa = person('Awa Traoré');
    $this->ibrahim = person('Ibrahim Konaté');
});

it('reste désactivé par défaut : aucune page, aucun service, même pour le super administrateur', function () {
    $admin = User::factory()->superAdmin()->create();

    expect(app(Settings::class)->get('wallet_module'))->toBe('off')
        ->and(app(Wallets::class)->enabled())->toBeFalse()
        ->and(ServicesGamad::availableFor($this->awa))->not->toHaveKey('zahab-wallet');
    $this->actingAs($this->awa)->get(route('portefeuille'))->assertNotFound();
    $this->actingAs($admin)->get(route('portefeuille'))->assertNotFound();
});

it('s’ouvre aux seuls testeurs en test privé, puis à tous en public, et figure dans Services GAMAD', function () {
    ouvrirZahab('test', $this->awa->email);

    $this->actingAs($this->awa)->get(route('portefeuille'))->assertOk()->assertSee('Activer mon Zahab Wallet');
    $this->actingAs($this->ibrahim)->get(route('portefeuille'))->assertNotFound();
    expect(ServicesGamad::availableFor($this->awa))->toHaveKey('zahab-wallet')->and(ServicesGamad::availableFor($this->ibrahim))->not->toHaveKey('zahab-wallet');

    ouvrirZahab('public');
    $this->actingAs($this->ibrahim)->get(route('portefeuille'))->assertOk();
});

it('ne crée jamais un portefeuille tout seul : l’activation est volontaire, vérifiée et sans effet répété', function () {
    ouvrirZahab('public');
    $this->actingAs($this->awa)->get(route('portefeuille'))->assertOk();
    expect(Wallet::query()->count())->toBe(0);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->call('activate')->assertSee('Votre Zahab Wallet est activé');
    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->call('activate');
    expect(Wallet::query()->count())->toBe(1)->and(Wallet::query()->first()->owner_id)->toBe($this->awa->id);

    $nonVerifiee = User::factory()->unverified()->create();
    expect(fn () => app(Wallets::class)->activate($nonVerifiee))->toThrow(HttpException::class);
});

it('fait du portefeuille d’une ZUMRA un bien commun de ses responsables : les membres n’y ont aucun accès', function () {
    c1ModuleZumra('public');
    ouvrirZahab('public');
    $zumra = zumra($this->awa);
    ajouteMembre($zumra, $this->ibrahim);
    $membre = ligne($zumra, $this->ibrahim);
    $dehors = person('Yao Brou');

    $this->actingAs($this->ibrahim)->get(route('zumras.portefeuille', $zumra))->assertNotFound();
    $this->actingAs($dehors)->get(route('zumras.portefeuille', $zumra))->assertNotFound();
    expect(fn () => app(Wallets::class)->activate($this->ibrahim, $zumra))->toThrow(HttpException::class);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class, ['zumra' => $zumra])->call('activate')->assertSee('Le Zahab Wallet de la ZUMRA est activé');
    $wallet = Wallet::query()->where('owner_type', Wallet::ZUMRA)->firstOrFail();
    expect(app(Gate::class)->forUser($this->ibrahim)->allows('view', $wallet))->toBeFalse();
    $this->actingAs($this->ibrahim)->get(route('zumras.portefeuille', $zumra))->assertNotFound();

    // Un responsable nommé y accède.
    app(Memberships::class)->promote($this->awa, $zumra, $membre);
    $this->actingAs($this->ibrahim)->get(route('zumras.portefeuille', $zumra))->assertOk();
});

it('propose la recharge dans le parcours actif seulement quand le service est ouvert et les clés renseignées', function () {
    $this->awa->forceFill([])->save();
    ouvrirZahab('public');
    app(Wallets::class)->activate($this->awa);
    expect(app(Payments::class)->available())->toBeFalse();

    configurerPaiement();
    app(Settings::class)->update(['wallet_module' => 'public'], User::factory()->superAdmin()->create());
    prestataire();
    expect(app(Payments::class)->available())->toBeTrue();

    $paiement = lancer($this->awa, 5000);
    prestataire([$paiement->provider_reference => ['status' => 'completed', 'amount' => 5000]]);
    app(Payments::class)->reconcile($paiement->fresh());
    expect(solde(Wallet::query()->first()))->toBe(5000);

    app(Settings::class)->update(['wallet_module' => 'off'], User::factory()->superAdmin()->create());
    expect(app(Payments::class)->available())->toBeFalse();
});

it('enregistre une demande de reversement : la somme est mise de côté tout de suite, le numéro est chiffré', function () {
    ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 10000);

    $payout = reverser($this->awa, $wallet, 4000);

    expect($payout->status)->toBe(PayoutStatus::Requested)->and(solde($wallet))->toBe(6000)
        ->and(LedgerEntry::query()->where('wallet_id', $wallet->id)->orderByDesc('sequence')->first()->kind)->toBe(LedgerKind::Payout)
        ->and($payout->phone)->toBe('+2250700000000')->and($payout->maskedPhone())->not->toContain('0700000000');
    expect(DB::table('payouts')->where('id', $payout->id)->value('phone'))->not->toContain('2250700000000');
});

it('refuse un reversement trop petit, trop grand, ou vers une destination invalide', function () {
    ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 5000);

    expect(fn () => reverser($this->awa, $wallet, 500))->toThrow(WalletException::class);
    expect(fn () => reverser($this->awa, $wallet, 9000))->toThrow(WalletException::class);
    expect(fn () => reverser($this->awa, $wallet, 2000, ['operator' => 'inconnu']))->toThrow(ValidationException::class);
    expect(fn () => reverser($this->awa, $wallet, 2000, ['phone' => 'abc']))->toThrow(ValidationException::class);
    expect(fn () => reverser($this->awa, $wallet, 2000, ['holder' => '']))->toThrow(ValidationException::class);
    expect(solde($wallet))->toBe(5000)->and(Payout::query()->count())->toBe(0);
});

it('limite le nombre de demandes de reversement par jour', function () {
    ouvrirZahab('public');
    config(['gamad.limits.payouts_per_day' => 2]);
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 50000);

    reverser($this->awa, $wallet, 1000);
    reverser($this->awa, $wallet, 1000);
    expect(fn () => reverser($this->awa, $wallet, 1000))->toThrow(WalletException::class);
});

it('rend la somme quand la personne annule sa demande, une seule fois', function () {
    ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 8000);
    $payout = reverser($this->awa, $wallet, 3000);

    expect(fn () => app(Payouts::class)->cancel($this->ibrahim, $payout))->toThrow(AuthorizationException::class);
    app(Payouts::class)->cancel($this->awa, $payout);

    expect($payout->fresh()->status)->toBe(PayoutStatus::Cancelled)->and(solde($wallet))->toBe(8000);
    expect(fn () => app(Payouts::class)->cancel($this->awa, $payout->fresh()))->toThrow(ValidationException::class);
    expect(solde($wallet))->toBe(8000);
});

it('réserve le versement au super administrateur, le journalise sans nom ni numéro et prévient la personne', function () {
    $admin = ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 8000);
    $payout = reverser($this->awa, $wallet, 3000);

    expect(fn () => app(Payouts::class)->markPaid($this->awa, $payout))->toThrow(AuthorizationException::class);
    expect(fn () => app(Payouts::class)->revealPhone(User::factory()->moderator()->create(), $payout))->toThrow(AuthorizationException::class);

    expect(app(Payouts::class)->revealPhone($admin, $payout))->toBe('+2250700000000');
    app(Payouts::class)->markPaid($admin, $payout, 'WAVE-123');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid)->and($payout->fresh()->provider_reference)->toBe('WAVE-123')->and(solde($wallet))->toBe(5000)
        ->and(UserNotification::query()->where('user_id', $this->awa->id)->where('kind', NotificationKind::PayoutPaid->value)->exists())->toBeTrue();
    $journal = AdminAction::query()->whereIn('action', ['wallet.payout_paid', 'wallet.payout_number_viewed'])->get();
    expect($journal)->toHaveCount(2)->and(json_encode($journal->pluck('payload')))->not->toContain('2250700000000')->not->toContain('Awa');
    expect(fn () => app(Payouts::class)->markPaid($admin, $payout->fresh()))->toThrow(ValidationException::class);
});

it('rend la somme au portefeuille quand le Président refuse, avec un motif, même au-delà du plafond', function () {
    $admin = ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 490000);
    $payout = reverser($this->awa, $wallet, 20000);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 25000);

    expect(fn () => app(Payouts::class)->decline($admin, $payout, ''))->toThrow(ValidationException::class);
    app(Payouts::class)->decline($admin, $payout, 'Numéro injoignable.');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Declined)->and(solde($wallet))->toBe(515000)
        ->and(UserNotification::query()->where('kind', NotificationKind::PayoutDeclined->value)->exists())->toBeTrue();
});

it('réserve les reversements d’une ZUMRA à ses responsables', function () {
    c1ModuleZumra('public');
    ouvrirZahab('public');
    $zumra = zumra($this->awa);
    ajouteMembre($zumra, $this->ibrahim);
    $wallet = app(Wallets::class)->activate($this->awa, $zumra);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 9000);

    expect(fn () => reverser($this->ibrahim, $wallet, 2000))->toThrow(AuthorizationException::class);
    expect(reverser($this->awa, $wallet, 2000)->status)->toBe(PayoutStatus::Requested)->and(solde($wallet))->toBe(7000);
});

it('permet la demande de reversement depuis la page, avec ses erreurs sous les champs', function () {
    ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 6000);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->assertSee('Reversement')
        ->set('payoutAmount', '2000')->set('payoutOperator', '')->set('payoutPhone', '0700')->set('payoutHolder', '')->call('requestPayout')
        ->assertHasErrors(['payoutOperator', 'payoutPhone', 'payoutHolder'])
        ->set('payoutOperator', 'orange')->set('payoutPhone', '+2250100000000')->set('payoutHolder', 'Awa Traoré')->call('requestPayout')->assertHasNoErrors()
        ->assertSee('Votre demande de reversement est enregistrée')->assertSee('Demandé')->assertDontSee('2250100000000');
    expect(Payout::query()->count())->toBe(1);
});

it('montre au Président les reversements sans nommer de compte, lit le numéro au journal et traite la demande', function () {
    $admin = ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 6000);
    $payout = reverser($this->awa, $wallet, 2500, ['holder' => 'Fatou Diallo']);

    $this->actingAs($this->awa)->get(route('espace.reversements'))->assertForbidden();
    Livewire::actingAs($admin)->test(ReversementsAdministration::class)->assertSee('2 500 ZAHAB')->assertSee('Fatou Diallo')->assertDontSee('Awa Traoré')->assertDontSee('2250700000000')
        ->call('reveal', $payout->id)->assertSee('+2250700000000')
        ->set('references.'.$payout->id, 'WAVE-9')->call('markPaid', $payout->id)->assertSee('marqué comme versé')
        ->call('setTab', 'traites')->assertSee('Versé');
    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid);
});

it('laisse le Président régler le service, ses testeurs et le reversement minimum depuis Paramètres, au journal', function () {
    $admin = User::factory()->superAdmin()->create();

    Livewire::actingAs($admin)->test(ParametresPortefeuille::class)->set('moduleState', 'test')->set('testers', "awa@exemple.test\nibrahim@exemple.test")->set('payoutMin', '2500')->call('save')->assertHasNoErrors();
    expect(app(Settings::class)->get('wallet_module'))->toBe('test')->and((int) app(Settings::class)->get('wallet_payout_min'))->toBe(2500);
    Livewire::actingAs($admin)->test(ParametresPortefeuille::class)->set('testers', 'pas-un-email')->call('save')->assertHasErrors(['testers']);
    expect(AdminAction::query()->where('action', 'setting.changed')->where('subject_id', 'wallet_module')->exists())->toBeTrue();
    $this->actingAs($this->awa)->get(route('espace.parametres.portefeuille'))->assertForbidden();
});

it('laisse le Président passer au mode réel depuis Paramètres en saisissant les clés, après sa confirmation', function () {
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['wallet_module' => 'public'], $admin);

    $page = Livewire::actingAs($admin)->test(ParametresPaiement::class);
    $page->set('mode', 'live')->set('apiKey', 'pk_live_abc123')->set('apiSecret', 'sk_live_def456')->call('save')->assertHasErrors(['confirmLive']);
    expect(app(Settings::class)->get('geniuspay_mode'))->toBe('sandbox');

    $page->set('confirmLive', true)->call('save')->assertHasNoErrors();
    expect(app(Settings::class)->get('geniuspay_mode'))->toBe('live')->and(app(GeniusPay::class)->configured())->toBeTrue();

    // Une clé d'essai ne passe pas en mode réel.
    Livewire::actingAs($admin)->test(ParametresPaiement::class)->set('apiKey', 'pk_sandbox_abc')->call('save')->assertHasErrors(['apiKey']);
});

it('refuse au registre toute somme au mauvais signe pour les nouveaux mouvements', function () {
    ouvrirZahab('public');
    $wallet = app(Wallets::class)->activate($this->awa);
    app(Wallets::class)->post($wallet, LedgerKind::Recharge, 1000);

    expect(fn () => DB::table('ledger_entries')->insert(['id' => (string) Str::uuid(), 'wallet_id' => $wallet->id, 'sequence' => 2, 'kind' => 'payout', 'amount' => 500, 'balance_after' => 1500]))
        ->toThrow(QueryException::class);
});
