<?php

use App\Enums\LedgerKind;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Livewire\PagePortefeuille;
use App\Livewire\PaiementsAdministration;
use App\Livewire\ParametresPaiement;
use App\Models\AdminAction;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\GeniusPay;
use App\Services\Payments;
use App\Services\Settings;
use App\Services\Wallets;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->awa = person('Awa Traoré');
    $this->admin = configurerPaiement();
    RateLimiter::clear('recharge:'.$this->awa->id);
    prestataire();
});

// ─── Lancer une recharge ─────────────────────────────────────────────────────

it('crée la recharge chez le prestataire sans donnée personnelle, avec une clé d’idempotence', function () {
    $p = lancer($this->awa, 5000);

    expect($p->status)->toBe(PaymentStatus::Pending)->and($p->provider_reference)->toBe($p->provider_reference)->and($p->checkout_url)->toStartWith('https://geniuspay.ci/checkout/')
        ->and($p->environment)->toBe('sandbox')->and($p->amount)->toBe(5000)->and($p->initiated_by)->toBe($this->awa->id);

    Http::assertSent(function (Request $r) use ($p) {
        if ($r->method() !== 'POST') {
            return false;
        }

        return $r->url() === GP_BASE.'/payments'
            && $r->header('X-API-Key') === ['pk_sandbox_abc123'] && $r->header('X-API-Secret') === ['sk_sandbox_def456']
            && $r->header('Idempotency-Key') === [$p->id]
            && $r['amount'] === 5000 && $r['currency'] === 'XOF' && $r['external_reference'] === $p->id
            && ! isset($r['customer']) && ! str_contains(json_encode($r->data()), $this->awa->email) && ! str_contains(json_encode($r->data()), 'Awa')
            && str_ends_with($r['success_url'], '?paiement='.$p->id);
    });
    expect(solde($p->wallet))->toBe(0); // rien n'est crédité avant la confirmation
});

it('refuse un montant hors des bornes réglées', function (int $montant) {
    expect(fn () => lancer($this->awa, $montant))->toThrow(PaymentException::class, 'entre 500 et 200');
    expect(Payment::query()->count())->toBe(0);
})->with([499, 200001, 0, -10]);

it('refuse une recharge qui dépasserait le plafond du portefeuille, en disant ce qui reste possible', function () {
    $w = app(Wallets::class)->forPerson($this->awa);
    app(Settings::class)->update(['wallet_max_balance_person' => 10000, 'wallet_recharge_max' => 100000], $this->admin);
    app(Wallets::class)->post($w, LedgerKind::Recharge, 8000);

    expect(fn () => lancer($this->awa, 2500))->toThrow(PaymentException::class, 'encore ajouter 2 000 ZAHAB');
    expect(lancer($this->awa, 2000)->amount)->toBe(2000);
});

it('limite le nombre de recharges lancées par heure', function () {
    config(['gamad.limits.recharges_per_hour' => 2]);
    lancer($this->awa, 1000);
    lancer($this->awa, 1000);

    expect(fn () => lancer($this->awa, 1000))->toThrow(PaymentException::class, 'Trop de recharges');
    expect(Payment::query()->count())->toBe(2);
});

it('ne laisse aucune recharge en attente si le prestataire refuse ou ne répond pas', function () {
    faux(['*' => Http::response(['success' => false, 'error' => ['code' => 'PAYMENT_INIT_FAILED']], 400)]);

    expect(fn () => lancer($this->awa, 1000))->toThrow(PaymentException::class, 'ne répond pas');
    $p = Payment::query()->sole();
    expect($p->status)->toBe(PaymentStatus::Failed)->and($p->provider_reference)->toBeNull();

    faux(fn () => throw new ConnectionException('timeout'));
    expect(fn () => lancer($this->awa, 1000))->toThrow(PaymentException::class);
});

it('refuse une adresse de paiement qui n’est pas en https, ou d’un autre mode', function () {
    faux(['*' => Http::response(['success' => true, 'data' => ['reference' => 'MTX-9', 'checkout_url' => 'http://evil.example/pay', 'environment' => 'sandbox']], 201)]);
    expect(fn () => lancer($this->awa, 1000))->toThrow(PaymentException::class);

    faux(['*' => Http::response(['success' => true, 'data' => ['reference' => 'MTX-9', 'checkout_url' => 'https://geniuspay.ci/checkout/MTX-9', 'environment' => 'live']], 201)]);
    expect(fn () => lancer($this->awa, 1000))->toThrow(PaymentException::class);
});

it('n’offre pas la recharge sans clés du bon mode, ou portefeuilles fermés', function () {
    $w = app(Wallets::class)->forPerson($this->awa);
    expect(app(Payments::class)->available())->toBeTrue();

    app(Settings::class)->update(['geniuspay_api_key' => 'pk_live_abc123'], $this->admin); // clé réelle en mode d'essai
    expect(app(Payments::class)->available())->toBeFalse();
    expect(fn () => lancer($this->awa, 1000, $w))->toThrow(PaymentException::class, 'pas disponible');

    app(Settings::class)->update(['geniuspay_api_key' => 'pk_sandbox_abc123', 'geniuspay_api_secret' => 'sk_live_def456'], $this->admin); // secrète réelle en mode d'essai
    expect(app(Payments::class)->available())->toBeFalse();

    app(Settings::class)->update(['geniuspay_api_secret' => 'sk_sandbox_def456', 'wallet_enabled' => '0'], $this->admin);
    expect(app(Payments::class)->available())->toBeFalse();
});

it('refuse de recharger un portefeuille fermé', function () {
    $w = app(Wallets::class)->forPerson($this->awa);
    app(Wallets::class)->closeFor(Wallet::PERSON, $this->awa->id);

    expect(fn () => lancer($this->awa, 1000, $w->fresh()))->toThrow(PaymentException::class);
});

// ─── Page du portefeuille ────────────────────────────────────────────────────

it('affiche le formulaire de recharge avec le mode d’essai annoncé', function () {
    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)
        ->assertSee('Recharger')->assertSee('Mode d’essai')->assertSee('Entre 500 et 200 000 ZAHAB');
});

it('masque la recharge quand elle n’est pas disponible', function () {
    app(Settings::class)->update(['geniuspay_api_secret' => 'sk_sandbox_autre'], $this->admin);
    app(Settings::class)->update(['geniuspay_mode' => 'live'], $this->admin); // clés d'essai en mode réel

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->assertDontSee('Recharger')->assertDontSee('Mode d’essai');
});

it('envoie la personne sur la page de paiement du prestataire', function () {
    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)
        ->set('amount', '5000')->call('recharge')->assertHasNoErrors()
        ->assertRedirect(Payment::query()->sole()->checkout_url);
});

it('affiche les refus de la recharge sous le champ', function () {
    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->set('amount', '10')->call('recharge')->assertHasErrors(['amount'])->assertSee('entre 500 et 200');
    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->set('amount', 'beaucoup')->call('recharge')->assertHasErrors(['amount']);
    expect(Payment::query()->count())->toBe(0);
});

it('permet aux responsables de recharger le portefeuille de leur ZUMRA, et à eux seuls', function () {
    $z = zumra($this->awa);
    $membre = person('Membre Simple');
    ajouteMembre($z, $membre);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class, ['zumra' => $z])->set('amount', '3000')->call('recharge')->assertHasNoErrors();
    $p = Payment::query()->sole();
    expect($p->wallet->owner_type)->toBe('zumra')->and($p->wallet->owner_id)->toBe($z->id);

    $this->actingAs($membre)->get(route('zumras.portefeuille', $z))->assertNotFound();
});

it('montre l’attente, puis la confirmation, au retour de la page de paiement', function () {
    $p = lancer($this->awa, 4000);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->assertSee('Paiement en attente de confirmation')->assertSee('4 000 ZAHAB')->assertSeeHtml('wire:poll.10s');

    prestataire(['MTX-'.substr($p->provider_reference, 4) => ['status' => 'completed']]);
    Livewire::withQueryParams(['paiement' => $p->id])->actingAs($this->awa)->test(PagePortefeuille::class)
        ->assertSee('Recharge confirmée : 4 000 ZAHAB ajoutés.')->assertSee('4 000 ZAHAB')->assertDontSee('Paiement en attente');

    expect(solde($p->wallet))->toBe(4000);
});

it('annonce un paiement qui n’a pas abouti', function () {
    $p = lancer($this->awa, 4000);
    prestataire([$p->provider_reference => ['status' => 'failed']]);

    Livewire::withQueryParams(['paiement' => $p->id])->actingAs($this->awa)->test(PagePortefeuille::class)->assertSee('Le paiement n’a pas abouti');
    expect($p->fresh()->status)->toBe(PaymentStatus::Failed)->and(solde($p->wallet))->toBe(0);
});

it('ne montre pas le paiement d’une autre personne au retour de la page', function () {
    $autre = person('Bob Kouassi');
    $p = lancer($autre, 4000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcile($p); // déjà confirmé et crédité à l'autre personne

    Livewire::withQueryParams(['paiement' => $p->id])->actingAs($this->awa)->test(PagePortefeuille::class)->assertDontSee('Recharge confirmée');
    expect(solde(app(Wallets::class)->forPerson($this->awa)))->toBe(0)->and(solde($p->wallet))->toBe(4000);
});

// ─── Webhook ─────────────────────────────────────────────────────────────────

it('refuse un webhook sans signature valide : mauvaise, absente, secret absent, horodatage trop ancien', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);

    webhook($p, options: ['signature' => 'faux'])->assertStatus(401);
    webhook($p, options: ['secret' => 'whsec_autre'])->assertStatus(401);
    webhook($p, options: ['timestamp' => time() - 3600])->assertStatus(401);
    $this->call('POST', route('webhooks.geniuspay'), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(401);
    expect(solde($p->wallet))->toBe(0);

    DB::table('settings')->where('key', 'geniuspay_webhook_secret')->delete();
    app()->forgetInstance(Settings::class);
    app()->forgetInstance(GeniusPay::class);
    webhook($p)->assertStatus(401);
    // Sans secret enregistré, une signature fabriquée avec une clé vide ne doit rien valoir non plus.
    webhook($p, options: ['secret' => ''])->assertStatus(401);
    expect(solde($p->wallet))->toBe(0);
});

it('crédite sur webhook valide après avoir demandé l’état au prestataire, une seule fois', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);

    webhook($p)->assertOk();
    webhook($p)->assertOk(); // rejeu
    webhook($p)->assertOk();

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Completed)->and($p->credit_entry_id)->not->toBeNull()->and(solde($p->wallet))->toBe(2000)
        ->and(LedgerEntry::query()->where('kind', 'recharge')->count())->toBe(1)
        ->and(LedgerEntry::query()->sole()->reference)->toBe($p->provider_reference);
    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), '/payments/'.$p->provider_reference));
});

it('ne croit jamais le contenu du webhook : le prestataire dit « échoué », rien n’est crédité', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'failed']]);

    webhook($p, 'payment.success')->assertOk();

    expect($p->fresh()->status)->toBe(PaymentStatus::Failed)->and(solde($p->wallet))->toBe(0);
});

it('ne crédite pas tant que le prestataire répond « en attente »', function () {
    $p = lancer($this->awa, 2000);

    webhook($p)->assertOk();

    expect($p->fresh()->status)->toBe(PaymentStatus::Pending)->and(solde($p->wallet))->toBe(0);
});

it('répond 503 quand le prestataire ne répond pas, pour qu’il renvoie la notification', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['_down' => true]]);

    webhook($p)->assertStatus(503);
    expect(solde($p->wallet))->toBe(0);
});

it('ignore un webhook d’un autre mode ou d’un paiement inconnu, sans rien créditer', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);

    webhook($p, options: ['env' => 'live'])->assertOk()->assertJson(['ignored' => 'environment']);
    webhook($p, options: ['reference' => 'MTX-INCONNU'])->assertOk(); // retrouvé par metadata.payment_id : traité
    expect(solde($p->wallet))->toBe(2000);

    $inconnu = new Payment(['id' => (string) Str::uuid(), 'amount' => 1, 'provider_reference' => 'MTX-NULLE-PART']);
    webhook($inconnu)->assertOk()->assertJson(['ignored' => 'unknown']);
});

it('ne crédite pas un montant confirmé différent du montant demandé', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed', 'amount' => 20]]);

    webhook($p)->assertOk();

    $p->refresh();
    expect($p->credit_entry_id)->toBeNull()->and(solde($p->wallet))->toBe(0)->and($p->note)->toContain('20 au lieu de 2000');
});

it('ne crédite pas une réponse d’un autre mode que celui du paiement', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed']], env: 'live');

    expect(fn () => app(Payments::class)->reconcile($p))->not->toThrow(Throwable::class);
    expect(solde($p->wallet))->toBe(0)->and($p->fresh()->note)->toContain('autre mode');
});

it('garde un paiement confirmé mais non crédité (plafond) puis le crédite quand c’est possible', function () {
    $w = app(Wallets::class)->forPerson($this->awa);
    $p = lancer($this->awa, 2000);
    app(Wallets::class)->post($w, LedgerKind::Recharge, 499000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);

    webhook($p)->assertOk();
    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Completed)->and($p->credit_entry_id)->toBeNull()->and($p->note)->toContain('Payée mais non créditée')->and(solde($w))->toBe(499000);

    app(Settings::class)->update(['wallet_max_balance_person' => 600000], $this->admin);
    expect(app(Payments::class)->reconcilePending())->toBe(1);
    $p->refresh();
    expect($p->credit_entry_id)->not->toBeNull()->and($p->note)->toBeNull()->and(solde($w))->toBe(501000);
});

it('débite un remboursement du prestataire, une seule fois', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    webhook($p)->assertOk();

    prestataire([$p->provider_reference => ['status' => 'refunded']]);
    webhook($p, 'payment.refunded')->assertOk();
    webhook($p, 'payment.refunded')->assertOk();

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Refunded)->and($p->refund_entry_id)->not->toBeNull()->and(solde($p->wallet))->toBe(0)
        ->and(LedgerEntry::query()->where('kind', 'refund')->count())->toBe(1)
        ->and(LedgerEntry::query()->where('kind', 'refund')->sole()->reference)->toBe('refund:'.$p->provider_reference)
        ->and($p->note)->toBeNull();
});

it('signale un remboursement que le solde ne permet pas de débiter', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    webhook($p)->assertOk();
    app(Wallets::class)->post($p->wallet, LedgerKind::Contribution, 1500);

    prestataire([$p->provider_reference => ['status' => 'refunded']]);
    webhook($p, 'payment.refunded')->assertOk();

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Refunded)->and($p->refund_entry_id)->toBeNull()->and($p->note)->toContain('à traiter')->and(solde($p->wallet))->toBe(500);
});

it('traite un remboursement arrivé avant tout crédit sans toucher au solde', function () {
    $p = lancer($this->awa, 2000);
    prestataire([$p->provider_reference => ['status' => 'refunded']]);

    webhook($p, 'payment.refunded')->assertOk();

    expect($p->fresh()->status)->toBe(PaymentStatus::Refunded)->and(solde($p->wallet))->toBe(0)->and(LedgerEntry::query()->count())->toBe(0)
        ->and($p->fresh()->note)->toContain('avant d’avoir été créditée');
});

it('referme un paiement annulé ou échoué, jamais un paiement déjà crédité', function () {
    $a = lancer($this->awa, 1000);
    prestataire([$a->provider_reference => ['status' => 'cancelled']]);
    webhook($a, 'payment.cancelled')->assertOk();
    expect($a->fresh()->status)->toBe(PaymentStatus::Cancelled);

    $b = lancer($this->awa, 1000);
    prestataire([$b->provider_reference => ['status' => 'completed']]);
    webhook($b)->assertOk();
    prestataire([$b->provider_reference => ['status' => 'failed']]);
    webhook($b, 'payment.failed')->assertOk();
    expect($b->fresh()->status)->toBe(PaymentStatus::Completed)->and(solde($b->wallet))->toBe(1000);
});

it('crédite un paiement confirmé même s’il avait été donné pour échoué ou expiré', function () {
    $p = lancer($this->awa, 1000);
    $p->forceFill(['status' => PaymentStatus::Expired])->save();
    prestataire([$p->provider_reference => ['status' => 'completed']]);

    app(Payments::class)->reconcile($p->fresh());

    expect(solde($p->wallet))->toBe(1000)->and($p->fresh()->status)->toBe(PaymentStatus::Completed);
});

it('refuse en base deux crédits du même paiement', function () {
    $w = app(Wallets::class)->forPerson($this->awa);
    app(Wallets::class)->post($w, LedgerKind::Recharge, 100, 'MTX-DOUBLE');

    expect(fn () => DB::transaction(fn () => DB::table('ledger_entries')->insert(['id' => (string) Str::uuid(), 'wallet_id' => $w->id, 'sequence' => 2, 'kind' => 'recharge', 'amount' => 100, 'balance_after' => 200, 'reference' => 'MTX-DOUBLE', 'created_at' => now()])))->toThrow(QueryException::class);
    expect(LedgerEntry::query()->count())->toBe(1);
});

// ─── Rapprochement ───────────────────────────────────────────────────────────

it('rapproche les paiements en attente par commande, et expire ceux qui ont plus d’un jour', function () {
    $a = lancer($this->awa, 1000);
    $vieux = lancer($this->awa, 1000);
    $vieux->forceFill(['created_at' => now()->subHours(30)])->save();
    prestataire([$a->provider_reference => ['status' => 'completed']]);

    $this->artisan('gamad:rapprocher-paiements')->expectsOutputToContain('2 paiement(s) mis à jour.')->assertSuccessful();

    expect($a->fresh()->status)->toBe(PaymentStatus::Completed)->and(solde($a->wallet))->toBe(1000)->and($vieux->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('ne rapproche que les paiements du mode courant', function () {
    $a = lancer($this->awa, 1000);
    $a->forceFill(['environment' => 'live'])->save();
    prestataire([$a->provider_reference => ['status' => 'completed']]);

    expect(app(Payments::class)->reconcilePending())->toBe(0)->and($a->fresh()->status)->toBe(PaymentStatus::Pending);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'GET');
});

it('laisse en attente les paiements quand le prestataire ne répond pas', function () {
    $a = lancer($this->awa, 1000);
    prestataire([$a->provider_reference => ['_down' => true]]);

    expect(app(Payments::class)->reconcilePending())->toBe(0)->and($a->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('établit le rapprochement du registre avec les paiements confirmés', function () {
    $a = lancer($this->awa, 1000);
    $b = lancer($this->awa, 3000);
    prestataire([$a->provider_reference => ['status' => 'completed'], $b->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcilePending();

    $r = app(Payments::class)->reconciliation('sandbox');
    expect($r)->toMatchArray(['confirmed' => 4000, 'credited' => 4000, 'ledger' => 4000, 'balanced' => true, 'pending' => 0, 'to_review' => 0]);

    prestataire([$a->provider_reference => ['status' => 'refunded']]);
    app(Payments::class)->reconcile($a->fresh());
    expect(app(Payments::class)->reconciliation('sandbox'))->toMatchArray(['refunded' => 1000, 'ledger_refunds' => 1000, 'balanced' => true]);
});

it('signale un écart entre le registre et les paiements', function () {
    $a = lancer($this->awa, 1000);
    prestataire([$a->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcile($a);
    $a->forceFill(['credit_entry_id' => null])->save(); // simule une anomalie

    expect(app(Payments::class)->reconciliation('sandbox')['balanced'])->toBeFalse();
});

// ─── Réglages du Président ───────────────────────────────────────────────────

function pageReglages(?User $qui = null)
{
    return Livewire::actingAs($qui ?? User::query()->where('platform_role', 'super_admin')->firstOrFail())->test(ParametresPaiement::class);
}

it('réserve les pages de paiement au Président', function () {
    foreach (['espace.parametres.paiement', 'espace.paiements'] as $route) {
        $this->actingAs($this->awa)->get(route($route))->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create())->get(route($route))->assertForbidden();
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }
});

it('enregistre les clés sans jamais les réafficher, et les journalise sans les valeurs', function () {
    DB::table('settings')->whereIn('key', ['geniuspay_api_key', 'geniuspay_api_secret', 'geniuspay_webhook_secret'])->delete();
    AdminAction::query()->getConnection()->table('admin_actions')->count();

    $page = Livewire::actingAs($this->admin)->test(ParametresPaiement::class)
        ->set('apiKey', 'pk_sandbox_PUBLIQUE1')->set('apiSecret', 'sk_sandbox_SECRETE_A1')->set('webhookSecret', 'whsec_SECRET_B2')
        ->call('save')->assertHasNoErrors()->assertSee('enregistrés')
        ->assertDontSee('SECRETE_A1')->assertDontSee('SECRET_B2')->assertSet('apiSecret', '')->assertSet('webhookSecret', '');

    expect(app(Settings::class)->secret('geniuspay_api_secret'))->toBe('sk_sandbox_SECRETE_A1');
    expect(DB::table('settings')->where('key', 'geniuspay_api_secret')->value('value'))->not->toContain('SECRETE');
    expect(json_encode(AdminAction::all()->pluck('payload')))->not->toContain('SECRETE_A1')->not->toContain('SECRET_B2');
    expect(AdminAction::query()->where('action', 'setting.changed')->pluck('subject_id')->all())->toContain('geniuspay_api_key', 'geniuspay_api_secret', 'geniuspay_webhook_secret');

    // Un champ secret laissé vide conserve la valeur enregistrée.
    $page->set('rechargeMin', '600')->call('save')->assertHasNoErrors();
    expect(app(Settings::class)->secret('geniuspay_api_secret'))->toBe('sk_sandbox_SECRETE_A1');
});

it('refuse des clés au mauvais format ou d’un autre mode', function (string $champ, string $valeur) {
    pageReglages($this->admin)->set($champ, $valeur)->call('save')->assertHasErrors([$champ]);
})->with([
    ['apiKey', 'abc'], ['apiKey', 'pk_live_zzz'], ['apiSecret', 'sk_live_zzz'], ['apiSecret', 'secret'], ['webhookSecret', 'abc'],
    ['baseUrl', 'http://geniuspay.ci/api/v1/merchant'], ['rechargeMin', '100'], ['rechargeMax', '0'],
]);

it('refuse un maximum inférieur au minimum', function () {
    pageReglages($this->admin)->set('rechargeMin', '1000')->set('rechargeMax', '900')->call('save')->assertHasErrors(['rechargeMax']);
});

it('exige la confirmation pour passer au mode réel, et la refuse tant que des ZAHAB d’essai existent', function () {
    $page = Livewire::actingAs($this->admin)->test(ParametresPaiement::class)->set('mode', 'live')->set('apiKey', 'pk_live_abc')->set('apiSecret', 'sk_live_abc');
    $page->call('save')->assertHasErrors(['confirmLive']);
    expect(app(Settings::class)->get('geniuspay_mode'))->toBe('sandbox');

    $p = lancer($this->awa, 1000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcile($p);

    $page->set('confirmLive', true)->call('save')->assertHasErrors(['mode']);
    expect(app(Settings::class)->get('geniuspay_mode'))->toBe('sandbox');
});

it('passe au mode réel une fois les essais terminés, puis refuse de revenir à l’essai après un paiement réel', function () {
    $p = lancer($this->awa, 1000);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcile($p);
    expect(solde($p->wallet))->toBe(1000);

    Livewire::actingAs($this->admin)->test(PaiementsAdministration::class)->call('endSandbox')->assertSee('Essais terminés : 1 portefeuille(s)');
    expect(solde($p->wallet))->toBe(0)->and(AdminAction::query()->where('action', 'wallet.sandbox_cleared')->count())->toBe(1);

    Livewire::actingAs($this->admin)->test(ParametresPaiement::class)->set('mode', 'live')->set('confirmLive', true)->set('apiKey', 'pk_live_abc')->set('apiSecret', 'sk_live_abc')->call('save')->assertHasNoErrors();
    expect(app(Settings::class)->get('geniuspay_mode'))->toBe('live');

    prestataire(env: 'live');
    $r = lancer($this->awa, 1000);
    prestataire([$r->provider_reference => ['status' => 'completed']], env: 'live');
    app(Payments::class)->reconcile($r);

    Livewire::actingAs($this->admin)->test(ParametresPaiement::class)->set('mode', 'sandbox')->set('apiKey', 'pk_sandbox_abc')->set('apiSecret', 'sk_sandbox_abc')->call('save')->assertHasErrors(['mode']);
    expect(fn () => app(Payments::class)->endSandbox($this->admin))->toThrow(PaymentException::class);
});

it('termine les essais de plusieurs portefeuilles, plusieurs fois, et le journalise', function () {
    $z = zumra($this->awa);
    foreach ([[$this->awa, null, 1000], [$this->awa, app(Wallets::class)->forZumra($z), 2500]] as [$qui, $wallet, $montant]) {
        $p = lancer($qui, $montant, $wallet);
        prestataire([$p->provider_reference => ['status' => 'completed']]);
        app(Payments::class)->reconcile($p);
    }
    $page = Livewire::actingAs($this->admin)->test(PaiementsAdministration::class)->assertSee('3 500 ZAHAB d’essai');

    $page->call('endSandbox')->assertSee('2 portefeuille(s) remis à zéro, 3 500 ZAHAB');
    expect(app(Payments::class)->totalBalance())->toBe(0);
    $acte = AdminAction::query()->where('action', 'wallet.sandbox_cleared')->sole();
    expect($acte->payload)->toMatchArray(['portefeuilles' => 2, 'zahab' => 3500])->and($acte->actor_id)->toBe($this->admin->id);

    $p = lancer($this->awa, 700);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcile($p);
    $page->call('endSandbox')->assertSee('1 portefeuille(s) remis à zéro, 700 ZAHAB');
    expect(app(Payments::class)->totalBalance())->toBe(0);
});

it('essaie la connexion avec les clés enregistrées', function () {
    pageReglages($this->admin)->call('testConnection')->assertSee('Connexion réussie : compte « GAMAD test », mode essai.');
    Http::assertSent(fn (Request $r) => $r->url() === GP_BASE.'/account' && $r->header('X-API-Key') === ['pk_sandbox_abc123']);

    faux(['*' => Http::response(['success' => false, 'error' => ['code' => 'INVALID_API_KEY']], 401)]);
    pageReglages($this->admin)->call('testConnection')->assertSee('n’a pas accepté la connexion');
});

it('annonce des clés incomplètes sans appeler le prestataire', function () {
    DB::table('settings')->where('key', 'geniuspay_api_secret')->delete();
    app()->forgetInstance(Settings::class);
    faux([]);

    pageReglages($this->admin)->call('testConnection')->assertSee('Les clés ne sont pas complètes');
    Http::assertNothingSent();
});

it('affiche l’adresse du webhook à déclarer', function () {
    pageReglages($this->admin)->assertSee(route('webhooks.geniuspay'));
});

// ─── Page des paiements ──────────────────────────────────────────────────────

it('liste les recharges sans aucun nom, avec les anomalies à examiner', function () {
    $z = zumra($this->awa);
    $a = lancer($this->awa, 1000);
    $b = lancer($this->awa, 2000, app(Wallets::class)->forZumra($z));
    prestataire([$a->provider_reference => ['status' => 'completed', 'amount' => 5]]);
    app(Payments::class)->reconcile($a);

    $page = Livewire::actingAs($this->admin)->test(PaiementsAdministration::class)
        ->assertSee('Personne')->assertSee('ZUMRA')->assertSee('À examiner')->assertSee('5 au lieu de 1000')
        ->assertDontSee('Awa')->assertDontSee($this->awa->email)->assertDontSee($z->name);
    $page->set('status', 'completed')->assertDontSee('2 000')->set('status', 'pending')->assertSee('2 000');
});

it('rapproche à la demande du Président', function () {
    $a = lancer($this->awa, 1000);
    prestataire([$a->provider_reference => ['status' => 'completed']]);

    Livewire::actingAs($this->admin)->test(PaiementsAdministration::class)->call('reconcile')->assertSee('1 paiement(s) mis à jour.')
        ->assertSeeHtml('data-testid="balanced"')->assertSee('Le registre correspond aux paiements confirmés.');
    expect(solde($a->wallet))->toBe(1000);
});

it('refuse les actions de la page des paiements à un autre que le Président', function () {
    Livewire::actingAs($this->awa)->test(PaiementsAdministration::class)->assertForbidden();
});

it('lit les clés dans la configuration enregistrée et jamais ailleurs', function () {
    expect(app(GeniusPay::class)->configured())->toBeTrue()->and(app(GeniusPay::class)->mode())->toBe('sandbox');
    DB::table('settings')->where('key', 'geniuspay_api_key')->delete();
    app()->forgetInstance(Settings::class);
    app()->forgetInstance(GeniusPay::class);
    expect(app(GeniusPay::class)->configured())->toBeFalse();
});
