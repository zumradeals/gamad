<?php

use App\Enums\LedgerKind;
use App\Enums\PaymentStatus;
use App\Exceptions\ContributionException;
use App\Exceptions\PaymentException;
use App\Livewire\CarrefourZumras;
use App\Livewire\ContributionsAdministration;
use App\Livewire\Espace;
use App\Livewire\JournalAdministration;
use App\Livewire\PageContribution;
use App\Livewire\PagePortefeuille;
use App\Livewire\ParametresPortefeuille;
use App\Models\AdminAction;
use App\Models\Allocation;
use App\Models\Contribution;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\Contributions;
use App\Services\Payments;
use App\Services\Settings;
use App\Services\Todo;
use App\Services\ZumraPosts;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function contributions(): Contributions
{
    return app(Contributions::class);
}

/** Alimente un portefeuille par le registre (sans passer par le prestataire) puis contribue depuis lui. */
function contribuer(User $qui, ?Zumra $z, int $montant, array $target = ['fund', null]): Contribution
{
    $wallet = $z ? portefeuilles()->forZumra($z) : portefeuilles()->forPerson($qui);
    portefeuilles()->post($wallet, LedgerKind::Recharge, $montant);

    return contributions()->fromWallet($qui, $wallet, $montant, $target);
}

function reconnaitre(Zumra $z): void
{
    $z->forceFill(['recognized_at' => now()])->save();
}

beforeEach(function () {
    $this->awa = person('Awa Traoré');
    $this->bob = person('Bob Kouassi');
    $this->admin = configurerPaiement();
    prestataire();
});

// ─── Contribuer depuis le portefeuille ───────────────────────────────────────

it('débite le portefeuille au registre et enregistre la contribution au fonds', function () {
    $c = contribuer($this->awa, null, 1500);

    expect($c->status)->toBe(Contribution::RECORDED)->and($c->source)->toBe('wallet')->and($c->environment)->toBe('sandbox')->and($c->target_type)->toBe('fund')
        ->and($c->requested_by)->toBe($this->awa->id)->and(solde($c->wallet))->toBe(0);

    $entry = LedgerEntry::query()->where('kind', 'contribution')->sole();
    expect($entry->amount)->toBe(-1500)->and($entry->reference)->toBe('contribution:'.$c->id)->and($c->ledger_entry_id)->toBe($entry->id);
});

it('exige le minimum, réglable', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 5000);

    expect(fn () => contributions()->fromWallet($this->awa, $w, 499))->toThrow(ContributionException::class, 'au moins 500');
    expect(contributions()->fromWallet($this->awa, $w, 500)->amount)->toBe(500);

    app(Settings::class)->update(['contribution_min' => 1000], $this->admin);
    expect(fn () => contributions()->fromWallet($this->awa, $w, 999))->toThrow(ContributionException::class, 'au moins 1 000');
    expect(Contribution::query()->count())->toBe(1);
});

it('refuse une contribution que le solde ne couvre pas, sans rien laisser', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 800);

    expect(fn () => contributions()->fromWallet($this->awa, $w, 900))->toThrow(ContributionException::class, 'solde');
    expect(Contribution::query()->count())->toBe(0)->and(solde($w))->toBe(800)->and(LedgerEntry::query()->where('kind', 'contribution')->count())->toBe(0);
});

it('refuse de contribuer quand les portefeuilles sont fermés ou le portefeuille clos', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 5000);
    portefeuilles()->closeFor(Wallet::PERSON, $this->awa->id);
    expect(fn () => contributions()->fromWallet($this->awa, $w->fresh(), 600))->toThrow(ContributionException::class, 'pas disponible');

    $z = zumra($this->bob);
    $wz = portefeuilles()->forZumra($z);
    portefeuilles()->post($wz, LedgerKind::Recharge, 5000);
    app(Settings::class)->update(['wallet_enabled' => '0'], $this->admin);
    expect(fn () => contributions()->fromWallet($this->bob, $wz, 600))->toThrow(ContributionException::class, 'pas disponible');
});

it('met en attente la dépense d’une ZUMRA au-dessus du seuil, sans débit', function () {
    $z = zumra($this->awa);
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 90000);

    $petite = contributions()->fromWallet($this->awa, $w, 50000);
    $grande = contributions()->fromWallet($this->awa, $w, 50001);

    expect($petite->status)->toBe(Contribution::RECORDED)->and($grande->status)->toBe(Contribution::AWAITING)->and($grande->ledger_entry_id)->toBeNull()->and(solde($w))->toBe(40000);
});

it('applique le seuil réglé par le Président, et jamais à une personne', function () {
    app(Settings::class)->update(['wallet_zumra_validation_threshold' => 1000], $this->admin);
    $z = zumra($this->awa);
    $wz = portefeuilles()->forZumra($z);
    portefeuilles()->post($wz, LedgerKind::Recharge, 5000);
    expect(contributions()->fromWallet($this->awa, $wz, 1001)->status)->toBe(Contribution::AWAITING);

    $wp = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($wp, LedgerKind::Recharge, 200000);
    expect(contributions()->fromWallet($this->awa, $wp, 150000)->status)->toBe(Contribution::RECORDED);
});

it('valide une dépense de ZUMRA : débit à ce moment, au journal, une seule fois', function () {
    $z = zumra($this->awa);
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 80000);
    $c = contributions()->fromWallet($this->awa, $w, 60000);

    $c = contributions()->validate($this->admin, $c);

    expect($c->status)->toBe(Contribution::RECORDED)->and($c->decided_by)->toBe($this->admin->id)->and(solde($w))->toBe(20000)->and($c->ledger_entry_id)->not->toBeNull();
    expect(AdminAction::query()->where('action', 'contribution.validated')->sole()->payload)->toMatchArray(['zahab' => 60000]);
    expect(fn () => contributions()->validate($this->admin, $c))->toThrow(ContributionException::class, 'déjà été traitée');
    expect(LedgerEntry::query()->where('kind', 'contribution')->count())->toBe(1);
});

it('ne valide pas une dépense que le solde ne couvre plus, et la laisse en attente', function () {
    $z = zumra($this->awa);
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 60000);
    $c = contributions()->fromWallet($this->awa, $w, 60000);
    portefeuilles()->post($w, LedgerKind::Contribution, 40000); // le solde a été dépensé entre-temps

    expect(fn () => contributions()->validate($this->admin, $c))->toThrow(ContributionException::class, 'solde');
    expect($c->fresh()->status)->toBe(Contribution::AWAITING)->and(solde($w))->toBe(20000)->and(AdminAction::query()->where('action', 'contribution.validated')->count())->toBe(0);
});

it('refuse une dépense de ZUMRA au journal, sans débit, et permet de retirer sa demande', function () {
    $z = zumra($this->awa);
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 90000);
    $a = contributions()->fromWallet($this->awa, $w, 60000);
    $b = contributions()->fromWallet($this->awa, $w, 60000);

    contributions()->refuse($this->admin, $a, 'Pas ce mois-ci');
    contributions()->withdraw($b);

    expect($a->fresh()->status)->toBe(Contribution::REFUSED)->and($a->fresh()->note)->toBe('Pas ce mois-ci')->and($b->fresh()->status)->toBe(Contribution::WITHDRAWN)->and(solde($w))->toBe(90000);
    expect(AdminAction::query()->where('action', 'contribution.refused')->sole()->reason)->toBe('Pas ce mois-ci');
    expect(fn () => contributions()->refuse($this->admin, $a))->toThrow(ContributionException::class);
    expect(fn () => contributions()->withdraw($a))->toThrow(ContributionException::class);
    expect(fn () => contributions()->validate($this->admin, $b))->toThrow(ContributionException::class);
});

// ─── Orientation ─────────────────────────────────────────────────────────────

it('accepte le fonds commun, une ZUMRA engagée ou un projet d’une ZUMRA engagée, et rien d’autre', function () {
    $engagee = zumra($this->awa, 'Engagée');
    $autre = zumra($this->bob, 'Pas engagée');
    $projet = projet($this->awa, $engagee);
    $projetAutre = projet($this->bob, $autre);
    contribuer($this->awa, $engagee, 1000);

    expect(contributions()->validTarget(['fund', null]))->toBe(['fund', null])
        ->and(contributions()->validTarget(['zumra', $engagee->id]))->toBe(['zumra', $engagee->id])
        ->and(contributions()->validTarget(['project', $projet->id]))->toBe(['project', $projet->id]);

    foreach ([['zumra', $autre->id], ['project', $projetAutre->id], ['fund', $engagee->id], ['zumra', null], ['zumra', (string) Str::uuid()], ['project', (string) Str::uuid()], ['cadeau', $engagee->id]] as $mauvaise) {
        expect(fn () => contributions()->validTarget($mauvaise))->toThrow(ContributionException::class, 'orientation');
    }
});

it('refuse une contribution orientée vers une ZUMRA non engagée, sans débit', function () {
    $autre = zumra($this->bob);
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 2000);

    expect(fn () => contributions()->fromWallet($this->awa, $w, 1000, ['zumra', $autre->id]))->toThrow(ContributionException::class);
    expect(solde($w))->toBe(2000);
});

// ─── Engagement, badges, finançable ──────────────────────────────────────────

it('rend une ZUMRA engagée et une personne contributrice pendant la durée d’engagement', function () {
    $z = zumra($this->awa);
    expect(contributions()->isEngaged($z))->toBeFalse()->and(contributions()->isContributor($this->awa))->toBeFalse();

    contribuer($this->awa, $z, 1000);
    contribuer($this->awa, null, 1000);
    expect(contributions()->isEngaged($z))->toBeTrue()->and(contributions()->isContributor($this->awa))->toBeTrue()->and(contributions()->isContributor($this->bob))->toBeFalse();

    $this->travel(59)->days();
    expect(contributions()->isEngaged($z))->toBeTrue();
    $this->travel(2)->days();
    expect(contributions()->isEngaged($z))->toBeFalse()->and(contributions()->isContributor($this->awa))->toBeFalse();
});

it('règle la durée d’engagement', function () {
    $z = zumra($this->awa);
    contribuer($this->awa, $z, 1000);
    app(Settings::class)->update(['contribution_engagement_days' => 10], $this->admin);

    $this->travel(9)->days();
    expect(contributions()->isEngaged($z))->toBeTrue();
    $this->travel(2)->days();
    expect(contributions()->isEngaged($z))->toBeFalse();
});

it('ne compte ni les demandes en attente, ni les refus, ni les remboursées, ni le mode d’essai en mode réel', function () {
    $z = zumra($this->awa);
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 200000);
    $attente = contributions()->fromWallet($this->awa, $w, 60000);
    expect(contributions()->isEngaged($z))->toBeFalse();

    contributions()->refuse($this->admin, $attente);
    expect(contributions()->isEngaged($z))->toBeFalse();

    $ok = contributions()->fromWallet($this->awa, $w, 1000);
    expect(contributions()->isEngaged($z))->toBeTrue();
    $ok->forceFill(['status' => Contribution::REFUNDED])->save();
    expect(contributions()->isEngaged($z))->toBeFalse();

    $ok->forceFill(['status' => Contribution::RECORDED])->save();
    expect(contributions()->isEngaged($z))->toBeTrue();
    app(Settings::class)->update(['geniuspay_mode' => 'live', 'geniuspay_api_key' => 'pk_live_x', 'geniuspay_api_secret' => 'sk_live_x'], $this->admin);
    expect(contributions()->isEngaged($z))->toBeFalse(); // la contribution d'essai ne vaut rien en mode réel
});

it('ne donne ni état ni badge quand les portefeuilles sont fermés', function () {
    $z = zumra($this->awa);
    contribuer($this->awa, $z, 1000);
    contribuer($this->awa, null, 1000);
    app(Settings::class)->update(['wallet_enabled' => '0'], $this->admin);

    expect(contributions()->isEngaged($z))->toBeFalse()->and(contributions()->isContributor($this->awa))->toBeFalse()->and(contributions()->engagedZumraIds())->toBe([]);
});

it('ne rend finançable qu’une ZUMRA à la fois engagée et reconnue', function () {
    $z = zumra($this->awa, 'Première');
    expect(contributions()->isFinanceable($z))->toBeFalse();

    contribuer($this->awa, $z, 1000);
    expect(contributions()->isFinanceable($z))->toBeFalse(); // engagée, pas reconnue

    reconnaitre($z);
    expect(contributions()->isFinanceable($z->fresh()))->toBeTrue();

    $autre = zumra($this->bob, 'Seconde');
    reconnaitre($autre);
    expect(contributions()->isFinanceable($autre->fresh()))->toBeFalse(); // reconnue, pas engagée
    expect(contributions()->isFinanceable(null))->toBeFalse();
});

it('affiche le badge « ZUMRA engagée » sur le Monde et dans le Carrefour, pas sans contribution', function () {
    $z = zumra($this->awa, 'Couture solidaire');
    monde($this->bob, $z)->assertDontSee('ZUMRA engagée');

    contribuer($this->awa, $z, 1000);
    monde($this->bob, $z)->assertSee('ZUMRA engagée')->assertSeeHtml('badge-engagee')->assertDontSee('1 000');
    Livewire::actingAs($this->bob)->test(CarrefourZumras::class)->assertSee('ZUMRA engagée');
});

it('n’affiche le badge « Contributeur » que si la personne l’a choisi', function () {
    $profil = $this->awa->ensureProfile();
    contribuer($this->awa, null, 1000);

    $this->actingAs($this->bob)->get(route('personnes.show', $profil->public_id))->assertOk()->assertDontSee('Contributeur');

    portefeuilles()->forPerson($this->awa)->forceFill(['show_badge' => true])->save();
    $this->actingAs($this->bob)->get(route('personnes.show', $profil->public_id))->assertSee('Contributeur')->assertDontSee('1 000');

    $this->travel(61)->days(); // plus contributeur : le badge disparaît même choisi
    $this->actingAs($this->bob)->get(route('personnes.show', $profil->public_id))->assertDontSee('Contributeur');
});

it('n’affiche pas le badge à qui n’a jamais contribué, même avec le choix coché', function () {
    $profil = $this->awa->ensureProfile();
    portefeuilles()->forPerson($this->awa)->forceFill(['show_badge' => true])->save();

    $this->actingAs($this->bob)->get(route('personnes.show', $profil->public_id))->assertDontSee('Contributeur');
});

// ─── Plafonds relevés pour une ZUMRA engagée ─────────────────────────────────

it('laisse six messages par jour à un membre d’une ZUMRA engagée là où deux seulement sont permis ailleurs', function () {
    config(['gamad.limits.zumra_posts_per_day' => 2, 'gamad.engaged_limit_multiplier' => 3]);
    $z = zumra($this->awa, 'Engagée');
    $simple = zumra($this->bob, 'Simple');
    $posts = app(ZumraPosts::class);

    $posts->post($this->bob, $simple, 'un');
    $posts->post($this->bob, $simple, 'deux');
    expect(fn () => $posts->post($this->bob, $simple, 'trois'))->toThrow(ValidationException::class);

    contribuer($this->awa, $z, 1000);
    RateLimiter::clear("zumra-post:{$this->awa->id}:1");
    foreach (range(1, 6) as $i) {
        $posts->post($this->awa, $z, 'message '.$i);
    }
    expect(fn () => $posts->post($this->awa, $z, 'trop'))->toThrow(ValidationException::class);
});

it('relève le plafond de création de projets pour une ZUMRA engagée', function () {
    config(['gamad.limits.projects_per_day' => 1, 'gamad.engaged_limit_multiplier' => 3]);
    $z = zumra($this->awa, 'Engagée');
    contribuer($this->awa, $z, 1000);

    foreach (range(1, 3) as $i) {
        projet($this->awa, $z, 'Projet numéro '.$i);
    }
    expect(fn () => projet($this->awa, $z, 'Projet de trop'))->toThrow(ValidationException::class);

    $simple = zumra($this->bob, 'Simple');
    projet($this->bob, $simple, 'Premier');
    expect(fn () => projet($this->bob, $simple, 'Second'))->toThrow(ValidationException::class);
});

// ─── Contribution directe par Mobile Money ───────────────────────────────────

function contributionDirecte(User $qui, int $montant, ?Zumra $z = null, array $target = ['fund', null]): Payment
{
    $wallet = $z ? portefeuilles()->forZumra($z) : portefeuilles()->forPerson($qui);

    return app(Payments::class)->start($qui, $wallet, $montant, route('contribution'), route('contribution'), 'contribution', $target);
}

it('lance une contribution directe sans plafond de portefeuille, avec le minimum de contribution', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 500000); // portefeuille plein : la recharge serait refusée

    $p = contributionDirecte($this->awa, 5000);

    expect($p->purpose)->toBe('contribution')->and($p->status)->toBe(PaymentStatus::Pending)->and($p->target_type)->toBe('fund');
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['description'] === 'Contribution GAMAD' && $r['amount'] === 5000);
    expect(fn () => contributionDirecte($this->awa, 499))->toThrow(PaymentException::class);
    expect(fn () => lancer($this->awa, 5000))->toThrow(PaymentException::class, 'solde maximum');
});

it('refuse une orientation impossible pour une contribution directe, sans paiement créé', function () {
    $autre = zumra($this->bob);

    expect(fn () => contributionDirecte($this->awa, 1000, null, ['zumra', $autre->id]))->toThrow(ContributionException::class);
    expect(Payment::query()->count())->toBe(0);
});

it('enregistre la contribution au fonds sur confirmation, sans toucher au portefeuille, une seule fois', function () {
    $p = contributionDirecte($this->awa, 2500);
    prestataire([$p->provider_reference => ['status' => 'completed']]);

    webhook($p)->assertOk();
    webhook($p)->assertOk();

    $p->refresh();
    $c = Contribution::query()->sole();
    expect($p->status)->toBe(PaymentStatus::Completed)->and($p->contribution_id)->toBe($c->id)->and($c->source)->toBe('direct')->and($c->payment_id)->toBe($p->id)
        ->and($c->status)->toBe(Contribution::RECORDED)->and($c->amount)->toBe(2500)->and($c->wallet_id)->toBe($p->wallet_id)
        ->and(solde($p->wallet))->toBe(0)->and(LedgerEntry::query()->count())->toBe(0);
    expect(contributions()->isContributor($this->awa))->toBeTrue();
});

it('n’enregistre pas une contribution directe au montant différent', function () {
    $p = contributionDirecte($this->awa, 2500);
    prestataire([$p->provider_reference => ['status' => 'completed', 'amount' => 25]]);

    webhook($p)->assertOk();

    expect(Contribution::query()->count())->toBe(0)->and($p->fresh()->note)->toContain('rien n’a été enregistré');
});

it('n’enregistre rien tant que le prestataire ne confirme pas', function () {
    $p = contributionDirecte($this->awa, 2500);
    prestataire([$p->provider_reference => ['status' => 'failed']]);

    webhook($p)->assertOk();

    expect(Contribution::query()->count())->toBe(0)->and($p->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('sort du fonds une contribution directe remboursée, sans débit de portefeuille', function () {
    $p = contributionDirecte($this->awa, 2500);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    webhook($p)->assertOk();
    prestataire([$p->provider_reference => ['status' => 'refunded']]);
    webhook($p, 'payment.refunded')->assertOk();

    expect($p->fresh()->status)->toBe(PaymentStatus::Refunded)->and(Contribution::query()->sole()->status)->toBe(Contribution::REFUNDED)->and(LedgerEntry::query()->count())->toBe(0)
        ->and(contributions()->fund()['received'])->toBe(0)->and(contributions()->fund()['refunded'])->toBe(2500);
});

it('ne retente pas indéfiniment les contributions directes confirmées et ne fausse pas le rapprochement des recharges', function () {
    $p = contributionDirecte($this->awa, 2500);
    $r = lancer($this->awa, 1000);
    prestataire([$p->provider_reference => ['status' => 'completed'], $r->provider_reference => ['status' => 'completed']]);

    expect(app(Payments::class)->reconcilePending())->toBe(2);
    expect(app(Payments::class)->reconcilePending())->toBe(0);

    $rapport = app(Payments::class)->reconciliation('sandbox');
    expect($rapport)->toMatchArray(['confirmed' => 1000, 'credited' => 1000, 'ledger' => 1000, 'balanced' => true, 'contributions' => 2500, 'contributions_recorded' => 2500]);
});

it('fait respecter par la base la cohérence des contributions et des affectations', function () {
    $w = portefeuilles()->forPerson($this->awa);
    $ligne = fn (array $a) => DB::transaction(fn () => DB::table('contributions')->insert($a + ['id' => (string) Str::uuid(), 'wallet_id' => $w->id, 'source' => 'wallet', 'amount' => 500, 'status' => 'recorded', 'environment' => 'sandbox', 'target_type' => 'fund', 'created_at' => now(), 'updated_at' => now()]));

    $ligne([]);
    foreach ([['amount' => 0], ['source' => 'cheque'], ['status' => 'perdue'], ['environment' => 'test'], ['target_type' => 'zumra'], ['target_type' => 'fund', 'target_id' => (string) Str::uuid()], ['source' => 'direct']] as $mauvaise) {
        expect(fn () => $ligne($mauvaise))->toThrow(QueryException::class);
    }

    $alloc = fn (array $a) => DB::transaction(fn () => DB::table('allocations')->insert($a + ['id' => (string) Str::uuid(), 'project_id' => (string) Str::uuid(), 'zumra_id' => (string) Str::uuid(), 'project_name' => 'P', 'zumra_name' => 'Z', 'amount' => 10, 'purpose' => 'x', 'status' => 'decided', 'environment' => 'sandbox', 'created_at' => now(), 'updated_at' => now()]));
    $alloc([]);
    foreach ([['amount' => 0], ['status' => 'perdue'], ['environment' => 'test']] as $mauvaise) {
        expect(fn () => $alloc($mauvaise))->toThrow(QueryException::class);
    }
});

// ─── Fonds, affectation, rapport ─────────────────────────────────────────────

/** Une ZUMRA reconnue et engagée avec un projet : prête à être financée. */
function zumraFinancable(User $fondateur, string $nom = 'Financable'): array
{
    $z = zumra($fondateur, $nom);
    reconnaitre($z);
    contribuer($fondateur, $z, 1000);

    return [$z->fresh(), projet($fondateur, $z, 'Projet '.$nom)];
}

it('calcule le fonds : reçu, affecté, versé, disponible, contributeurs, par mois', function () {
    contribuer($this->awa, null, 4000);
    contribuer($this->bob, null, 6000);
    [, $projet] = zumraFinancable($this->awa);

    $a = contributions()->allocate($this->admin, $projet, 3000, 'Matériel');
    contributions()->markPaid($this->admin, $a);
    contributions()->allocate($this->admin, $projet, 2000, 'Formation');

    expect(contributions()->fund())->toMatchArray(['received' => 11000, 'allocated' => 5000, 'paid' => 3000, 'available' => 6000, 'contributors' => 3, 'refunded' => 0]);
    expect(contributions()->monthly())->toBe([now()->timezone('Africa/Abidjan')->format('Y-m') => 11000]);
});

it('range les orientations par ZUMRA et par projet, sans nom de personne', function () {
    [$z, $projet] = zumraFinancable($this->awa);
    contribuer($this->bob, null, 2000, ['zumra', $z->id]);
    contribuer($this->bob, null, 1000, ['project', $projet->id]);
    contribuer($this->bob, null, 700, ['fund', null]);

    $o = contributions()->orientations();
    expect($o)->toBe([['type' => 'zumra', 'name' => $z->name, 'total' => 2000], ['type' => 'project', 'name' => $projet->name, 'total' => 1000]]);
    expect(json_encode($o))->not->toContain('Bob');
});

it('affecte le fonds à un projet finançable seulement, dans la limite du disponible', function () {
    [, $projet] = zumraFinancable($this->awa);
    $nonFinancee = zumra($this->bob, 'Non financée');
    contribuer($this->bob, $nonFinancee, 1000);
    $autreProjet = projet($this->bob, $nonFinancee, 'Autre projet'); // engagée, pas reconnue

    expect(fn () => contributions()->allocate($this->admin, $autreProjet, 100, 'x'))->toThrow(ContributionException::class, 'engagée et reconnue');
    expect(fn () => contributions()->allocate($this->admin, $projet, 2001, 'x'))->toThrow(ContributionException::class, 'ne contient que 2 000');
    expect(fn () => contributions()->allocate($this->admin, $projet, 0, 'x'))->toThrow(ContributionException::class);

    $a = contributions()->allocate($this->admin, $projet, 2000, 'Matériel de couture');
    expect($a)->toMatchArray(['amount' => 2000, 'purpose' => 'Matériel de couture', 'status' => 'decided', 'environment' => 'sandbox', 'project_name' => $projet->name, 'decided_by' => $this->admin->id]);
    expect(fn () => contributions()->allocate($this->admin, $projet, 1, 'de trop'))->toThrow(ContributionException::class);
    expect(AdminAction::query()->where('action', 'allocation.decided')->sole()->payload)->toMatchArray(['zahab' => 2000, 'projet' => $projet->name]);
});

it('refuse d’affecter à une ZUMRA qui n’est plus engagée', function () {
    [, $projet] = zumraFinancable($this->awa);
    contribuer($this->bob, null, 5000);
    $this->travel(61)->days();

    expect(fn () => contributions()->allocate($this->admin, $projet, 100, 'x'))->toThrow(ContributionException::class, 'engagée et reconnue');
});

it('inscrit le versement une seule fois au journal', function () {
    [, $projet] = zumraFinancable($this->awa);
    $a = contributions()->allocate($this->admin, $projet, 500, 'Matériel');

    $a = contributions()->markPaid($this->admin, $a, 'Virement Wave du 12');
    contributions()->markPaid($this->admin, $a, 'autre note');

    expect($a->isPaid())->toBeTrue()->and($a->fresh()->payment_note)->toBe('Virement Wave du 12')->and(AdminAction::query()->where('action', 'allocation.paid')->count())->toBe(1);
});

it('compte le fonds par mode : l’essai ne se mêle pas au réel', function () {
    contribuer($this->awa, null, 4000);
    expect(contributions()->fund()['received'])->toBe(4000);

    app(Settings::class)->update(['geniuspay_mode' => 'live', 'geniuspay_api_key' => 'pk_live_x', 'geniuspay_api_secret' => 'sk_live_x'], $this->admin);
    expect(contributions()->fund())->toMatchArray(['received' => 0, 'available' => 0, 'contributors' => 0]);
});

// ─── Rapport public ──────────────────────────────────────────────────────────

it('publie un rapport sans aucun nom de personne ni montant individuel, ouvert aux visiteurs', function () {
    [$z, $projet] = zumraFinancable($this->awa, 'Atelier du quartier');
    contribuer($this->bob, null, 7777);
    contributions()->allocate($this->admin, $projet, 1234, 'Machines à coudre');

    $page = $this->get(route('rapport-contributions'));

    $page->assertOk()->assertSee('Rapport du fonds de contribution')->assertSee('Mode d’essai')->assertSee('8 777 ZAHAB')->assertSee('1 234 ZAHAB')
        ->assertSee('Machines à coudre')->assertSee($projet->name)->assertSee($z->name)
        ->assertDontSee('Awa')->assertDontSee('Bob')->assertDontSee($this->awa->email)->assertDontSee('7 777')->assertDontSee('1 000 ZAHAB');
});

it('ne publie le nombre de contributeurs qu’à partir de cinq', function () {
    foreach (range(1, 4) as $i) {
        contribuer(person('Contributeur '.$i), null, 1000);
    }
    $this->get(route('rapport-contributions'))->assertDontSeeHtml('data-testid="contributeurs"')->assertSee('qu’à partir de 5');

    contribuer(person('Contributeur 5'), null, 1000);
    $this->get(route('rapport-contributions'))->assertSeeHtml('data-testid="contributeurs"');
});

it('n’existe pas tant que les portefeuilles sont fermés', function () {
    app(Settings::class)->update(['wallet_enabled' => '0'], $this->admin);

    $this->get(route('rapport-contributions'))->assertNotFound();
});

it('annonce un rapport vide sans le truquer', function () {
    $this->get(route('rapport-contributions'))->assertOk()->assertSee('Aucune contribution pour l’instant.')->assertSee('Aucune affectation pour l’instant.')->assertSee('0 ZAHAB');
});

// ─── Page de contribution ────────────────────────────────────────────────────

it('cache la page de contribution tant que les portefeuilles sont fermés, et la réserve aux titulaires', function () {
    $z = zumra($this->awa);
    $this->actingAs($this->awa)->get(route('contribution'))->assertOk();
    $this->actingAs($this->awa)->get(route('zumras.contribution', $z))->assertOk();
    $this->actingAs($this->bob)->get(route('zumras.contribution', $z))->assertNotFound();

    app(Settings::class)->update(['wallet_enabled' => '0'], $this->admin);
    $this->actingAs($this->awa)->get(route('contribution'))->assertNotFound();
    $this->actingAs($this->awa)->get(route('zumras.contribution', $z))->assertNotFound();
});

it('ne crée aucun portefeuille pour une personne étrangère à la ZUMRA', function () {
    $z = zumra($this->awa);

    $this->actingAs($this->bob)->get(route('zumras.contribution', $z))->assertNotFound();
    expect(Wallet::query()->where('owner_type', 'zumra')->count())->toBe(0);
});

it('renvoie les visiteurs vers la connexion', function () {
    $this->get(route('contribution'))->assertRedirect(route('login'));
});

it('contribue depuis le portefeuille avec un message de remerciement', function () {
    portefeuilles()->post(portefeuilles()->forPerson($this->awa), LedgerKind::Recharge, 3000);

    Livewire::actingAs($this->awa)->test(PageContribution::class)
        ->assertSee('Pas de contribution récente')->assertSee('Au moins 500 ZAHAB')
        ->set('amount', '1200')->call('contribute')->assertHasNoErrors()->assertSee('Merci : votre contribution est enregistrée.')
        ->assertSee('Contributeur')->assertSee('1 200 ZAHAB')->assertSet('amount', '');

    expect(solde(portefeuilles()->forPerson($this->awa)))->toBe(1800);
});

it('montre les refus de contribution sous le champ', function () {
    Livewire::actingAs($this->awa)->test(PageContribution::class)
        ->set('amount', '100')->call('contribute')->assertHasErrors(['amount'])->assertSee('au moins 500')
        ->set('amount', 'beaucoup')->call('contribute')->assertHasErrors(['amount'])
        ->set('amount', '900')->call('contribute')->assertHasErrors(['amount'])->assertSee('solde');
    expect(Contribution::query()->count())->toBe(0);
});

it('propose le paiement direct seulement quand le prestataire est configuré, et y envoie la personne', function () {
    $page = Livewire::actingAs($this->awa)->test(PageContribution::class)->assertSee('Par Mobile Money, directement')->assertSee('Mode d’essai');
    $page->set('source', 'direct')->set('amount', '2000')->call('contribute')->assertHasNoErrors()->assertRedirect(Payment::query()->sole()->checkout_url);
    expect(Payment::query()->sole()->purpose)->toBe('contribution');

    app(Settings::class)->update(['geniuspay_api_key' => 'pk_live_zzz'], $this->admin); // clé du mauvais mode : plus de paiement direct
    Livewire::actingAs($this->awa)->test(PageContribution::class)->assertDontSee('Par Mobile Money, directement');
});

it('propose pour orientation le fonds commun et seulement les ZUMRA et projets engagés', function () {
    $engagee = zumra($this->bob, 'Zumra engagée visible');
    $autre = zumra($this->bob, 'Zumra discrète');
    projet($this->bob, $engagee, 'Projet visible');
    projet($this->bob, $autre, 'Projet discret');
    contribuer($this->bob, $engagee, 1000);

    Livewire::actingAs($this->awa)->test(PageContribution::class)
        ->assertSee('Le fonds commun')->assertSee('ZUMRA engagée : Zumra engagée visible')->assertSee('Projet : Projet visible')
        ->assertDontSee('Zumra discrète')->assertDontSee('Projet discret');
});

it('enregistre l’orientation choisie et refuse une orientation falsifiée', function () {
    $engagee = zumra($this->bob, 'Engagée');
    $autre = zumra($this->bob, 'Autre');
    contribuer($this->bob, $engagee, 1000);
    portefeuilles()->post(portefeuilles()->forPerson($this->awa), LedgerKind::Recharge, 5000);

    $page = Livewire::actingAs($this->awa)->test(PageContribution::class);
    $page->set('orientation', 'zumra:'.$autre->id)->set('amount', '600')->call('contribute')->assertHasErrors(['amount'])->assertSee('orientation');
    $page->set('orientation', 'zumra:'.$engagee->id)->call('contribute')->assertHasNoErrors();

    $c = Contribution::query()->where('requested_by', $this->awa->id)->sole();
    expect($c->target_type)->toBe('zumra')->and($c->target_id)->toBe($engagee->id);
});

it('permet aux responsables de contribuer pour la ZUMRA, avec validation au-dessus du seuil', function () {
    $z = zumra($this->awa);
    $membre = person('Membre Simple');
    ajouteMembre($z, $membre);
    portefeuilles()->post(portefeuilles()->forZumra($z), LedgerKind::Recharge, 90000);

    $page = Livewire::actingAs($this->awa)->test(PageContribution::class, ['zumra' => $z])->assertSee('Contribution de la ZUMRA')->assertSee('Pas encore engagée');
    $page->set('amount', '60000')->call('contribute')->assertHasNoErrors()->assertSee('attend la validation du Président')->assertSee('En attente de validation');
    $page->set('amount', '1000')->call('contribute')->assertSee('Merci')->assertSee('ZUMRA engagée');

    $this->actingAs($membre)->get(route('zumras.contribution', $z))->assertNotFound();
});

it('retire une demande en attente, de son propre portefeuille seulement', function () {
    $z = zumra($this->awa, 'La nôtre');
    portefeuilles()->post(portefeuilles()->forZumra($z), LedgerKind::Recharge, 90000);
    $c = contributions()->fromWallet($this->awa, portefeuilles()->forZumra($z), 60000);
    $etrangere = zumra($this->bob, 'La leur');
    portefeuilles()->post(portefeuilles()->forZumra($etrangere), LedgerKind::Recharge, 90000);
    $autre = contributions()->fromWallet($this->bob, portefeuilles()->forZumra($etrangere), 60000);

    Livewire::actingAs($this->awa)->test(PageContribution::class, ['zumra' => $z])->call('withdraw', $c->id)->assertSee('Demande retirée.');
    expect($c->fresh()->status)->toBe(Contribution::WITHDRAWN);

    expect(fn () => Livewire::actingAs($this->awa)->test(PageContribution::class, ['zumra' => $z])->call('withdraw', $autre->id))->toThrow(ModelNotFoundException::class);
    expect($autre->fresh()->status)->toBe(Contribution::AWAITING);
});

it('enregistre le rappel mensuel et le badge, ce dernier pour une personne seulement', function () {
    Livewire::actingAs($this->awa)->test(PageContribution::class)->assertSee('Afficher le badge')->call('setReminder', true)->call('setBadge', true);
    $w = portefeuilles()->forPerson($this->awa)->fresh();
    expect($w->monthly_reminder)->toBeTrue()->and($w->show_badge)->toBeTrue();

    Livewire::actingAs($this->awa)->test(PageContribution::class)->call('setReminder', false)->call('setBadge', false);
    expect($w->fresh()->monthly_reminder)->toBeFalse()->and($w->fresh()->show_badge)->toBeFalse();

    $z = zumra($this->awa);
    Livewire::actingAs($this->awa)->test(PageContribution::class, ['zumra' => $z])->assertDontSee('Afficher le badge')->call('setReminder', true)->call('setBadge', true);
    $wz = portefeuilles()->forZumra($z)->fresh();
    expect($wz->monthly_reminder)->toBeTrue()->and($wz->show_badge)->toBeFalse();
});

it('ne montre à chaque titulaire que ses propres contributions et son propre rapport', function () {
    contribuer($this->awa, null, 4321);
    contribuer($this->bob, null, 1111);

    Livewire::actingAs($this->awa)->test(PageContribution::class)->assertSee('4 321')->assertDontSee('1 111')->assertSeeHtml('data-testid="impact"');
    Livewire::actingAs($this->bob)->test(PageContribution::class)->assertSee('1 111')->assertDontSee('4 321');
});

it('établit le rapport d’impact avec des chiffres réels seulement', function () {
    Livewire::actingAs($this->awa)->test(PageContribution::class)->assertSee('Aucune contribution pour l’instant.');

    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 5000);
    contributions()->fromWallet($this->awa, $w, 1000);
    contributions()->fromWallet($this->awa, $w, 2000);

    expect(contributions()->impact($w))->toMatchArray(['total' => 3000, 'count' => 2, 'months' => 1]);
    Livewire::actingAs($this->awa)->test(PageContribution::class)->assertSee('3 000 ZAHAB')->assertSee('2 contributions')->assertSee('sur 1 mois');
});

it('annonce l’attente puis l’enregistrement d’une contribution directe au retour du paiement', function () {
    $p = contributionDirecte($this->awa, 2500);

    Livewire::actingAs($this->awa)->test(PageContribution::class)->assertSee('Paiement en attente de confirmation')->assertSeeHtml('wire:poll.10s');

    prestataire([$p->provider_reference => ['status' => 'completed']]);
    Livewire::withQueryParams(['paiement' => $p->id])->actingAs($this->awa)->test(PageContribution::class)->assertSee('Merci : votre contribution de 2 500 ZAHAB est enregistrée.')->assertDontSee('Paiement en attente');
    expect(contributions()->isContributor($this->awa))->toBeTrue();

    $echec = contributionDirecte($this->awa, 700);
    prestataire([$echec->provider_reference => ['status' => 'cancelled']]);
    Livewire::withQueryParams(['paiement' => $echec->id])->actingAs($this->awa)->test(PageContribution::class)->assertSee('Le paiement n’a pas abouti');
});

it('ne montre pas au retour le paiement d’une autre personne', function () {
    $p = contributionDirecte($this->bob, 2500);
    prestataire([$p->provider_reference => ['status' => 'completed']]);
    app(Payments::class)->reconcile($p);

    Livewire::withQueryParams(['paiement' => $p->id])->actingAs($this->awa)->test(PageContribution::class)->assertDontSee('Merci : votre contribution de');
    expect(contributions()->isContributor($this->awa))->toBeFalse();
});

it('relie les pages : portefeuille, Espace, Monde de la ZUMRA', function () {
    $z = zumra($this->awa);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->assertSee('Contribuer');
    Livewire::actingAs($this->awa)->test(Espace::class)->assertSee('Ma contribution');
    monde($this->awa, $z)->assertSee('Contribution de la ZUMRA');
    $membre = person('Membre Simple');
    ajouteMembre($z, $membre);
    monde($membre, $z)->assertDontSee('Contribution de la ZUMRA');
});

// ─── Rappel mensuel dans « À faire maintenant » ──────────────────────────────

it('demande la contribution du mois à qui l’a demandé, jusqu’à ce qu’elle soit faite', function () {
    $w = portefeuilles()->forPerson($this->awa);
    expect(app(Todo::class)->for($this->awa))->toBe([]);

    $w->forceFill(['monthly_reminder' => true])->save();
    $lignes = app(Todo::class)->for($this->awa);
    expect($lignes)->toHaveCount(1)->and($lignes[0])->toMatchArray(['title' => 'Votre contribution du mois', 'action' => 'Contribuer', 'url' => route('contribution')]);
    expect(app(Todo::class)->for($this->bob))->toBe([]);

    portefeuilles()->post($w, LedgerKind::Recharge, 1000);
    contributions()->fromWallet($this->awa, $w, 600);
    expect(app(Todo::class)->for($this->awa))->toBe([]);

    $this->travel(1)->months();
    expect(app(Todo::class)->for($this->awa))->toHaveCount(1);
});

it('demande la contribution du mois de la ZUMRA à ses responsables seulement', function () {
    $z = zumra($this->awa, 'Couture solidaire');
    $membre = person('Membre Simple');
    ajouteMembre($z, $membre);
    portefeuilles()->forZumra($z)->forceFill(['monthly_reminder' => true])->save();

    $lignes = app(Todo::class)->for($this->awa);
    expect($lignes)->toHaveCount(1)->and($lignes[0]['title'])->toBe('Contribution du mois de Couture solidaire')->and($lignes[0]['url'])->toBe(route('zumras.contribution', $z));
    expect(app(Todo::class)->for($membre))->toBe([]);
});

it('ne rappelle ni un portefeuille sans demande de rappel, ni un portefeuille clos', function () {
    $w = portefeuilles()->forPerson($this->awa);
    expect(contributions()->reminderDue($w))->toBeFalse();

    $w->forceFill(['monthly_reminder' => true])->save();
    expect(contributions()->reminderDue($w))->toBeTrue();

    portefeuilles()->closeFor(Wallet::PERSON, $this->awa->id);
    expect(contributions()->reminderDue($w->fresh()))->toBeFalse();
});

it('enregistre une contribution directe une seule fois, même rappelée', function () {
    $p = contributionDirecte($this->awa, 2500);

    $a = contributions()->recordDirect($p);
    $b = contributions()->recordDirect($p);

    expect($b->id)->toBe($a->id)->and(Contribution::query()->count())->toBe(1);
});

it('ne demande rien quand les portefeuilles sont fermés', function () {
    portefeuilles()->forPerson($this->awa)->forceFill(['monthly_reminder' => true])->save();
    app(Settings::class)->update(['wallet_enabled' => '0'], $this->admin);

    expect(app(Todo::class)->for($this->awa))->toBe([]);
});

// ─── Tableau du Président ────────────────────────────────────────────────────

it('réserve le tableau au Président', function () {
    $this->actingAs($this->awa)->get(route('espace.contributions'))->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get(route('espace.contributions'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('espace.contributions'))->assertOk();
    Livewire::actingAs($this->awa)->test(ContributionsAdministration::class)->assertForbidden();
});

it('montre au Président le fonds, les orientations et les noms des contributeurs', function () {
    [$z] = zumraFinancable($this->awa);
    contribuer($this->bob, null, 2500, ['zumra', $z->id]);
    contribuer($this->awa, null, 800);

    Livewire::actingAs($this->admin)->test(ContributionsAdministration::class)
        ->assertSee('4 300')->assertSee('Bob Kouassi')->assertSee('Awa Traoré')->assertSeeHtml('data-testid="orientations"')->assertSee('ZUMRA : '.$z->name);
});

it('valide et refuse les dépenses de ZUMRA depuis le tableau', function () {
    $z = zumra($this->awa, 'Atelier');
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 200000);
    $a = contributions()->fromWallet($this->awa, $w, 60000);
    $b = contributions()->fromWallet($this->awa, $w, 70000);

    $page = Livewire::actingAs($this->admin)->test(ContributionsAdministration::class)->assertSee('Dépenses de ZUMRA à valider')->assertSee('60 000 ZAHAB — Atelier');
    $page->call('validateContribution', $a->id)->assertSee('Dépense validée et débitée');
    $page->call('refuseContribution', $b->id)->assertSee('Demande refusée');
    $page->call('validateContribution', $a->id)->assertSee('déjà été traitée');

    expect($a->fresh()->status)->toBe(Contribution::RECORDED)->and($b->fresh()->status)->toBe(Contribution::REFUSED)->and(solde($w))->toBe(140000);
});

it('annonce qu’une validation ne peut pas être faite faute de solde', function () {
    $z = zumra($this->awa);
    $w = portefeuilles()->forZumra($z);
    portefeuilles()->post($w, LedgerKind::Recharge, 60000);
    $c = contributions()->fromWallet($this->awa, $w, 60000);
    portefeuilles()->post($w, LedgerKind::Contribution, 50000);

    Livewire::actingAs($this->admin)->test(ContributionsAdministration::class)->call('validateContribution', $c->id)->assertSee('Le solde du portefeuille ne suffit pas');
    expect($c->fresh()->status)->toBe(Contribution::AWAITING);
});

it('affecte le fonds depuis le tableau et inscrit le versement', function () {
    [$z, $projet] = zumraFinancable($this->awa);
    contribuer($this->bob, null, 9000);

    $page = Livewire::actingAs($this->admin)->test(ContributionsAdministration::class)->assertSee($projet->name.' — '.$z->name);
    $page->set('projectId', $projet->id)->set('amount', '4000')->set('purpose', 'Matériel')->call('allocate')->assertHasNoErrors()->assertSee('Affectation décidée')
        ->assertSee('4 000 ZAHAB — '.$projet->name)->assertSee('à verser')->assertSet('amount', '');

    $a = Allocation::query()->sole();
    $page->call('markPaid', $a->id)->assertSee('Versement inscrit.')->assertSee('versée le');
    expect($a->fresh()->isPaid())->toBeTrue();
});

it('refuse depuis le tableau une affectation trop grande ou mal formée', function () {
    [, $projet] = zumraFinancable($this->awa);

    $page = Livewire::actingAs($this->admin)->test(ContributionsAdministration::class);
    $page->set('projectId', $projet->id)->set('amount', '999999')->set('purpose', 'Trop')->call('allocate')->assertSee('ne contient que 1 000');
    $page->set('amount', '0')->call('allocate')->assertHasErrors(['amount']);
    $page->set('amount', '10')->set('purpose', '')->call('allocate')->assertHasErrors(['purpose']);
    $page->set('projectId', 'pas-un-projet')->set('purpose', 'ok')->call('allocate')->assertHasErrors(['projectId']);
    expect(Allocation::query()->count())->toBe(0);
});

it('ne propose que les projets de ZUMRA engagées et reconnues', function () {
    [, $financable] = zumraFinancable($this->awa, 'Alpha');
    $seulementReconnue = zumra($this->bob, 'Beta');
    reconnaitre($seulementReconnue);
    projet($this->bob, $seulementReconnue, 'Projet Beta');
    $seulementEngagee = zumra($this->bob, 'Gamma');
    contribuer($this->bob, $seulementEngagee, 1000);
    projet($this->bob, $seulementEngagee, 'Projet Gamma');

    Livewire::actingAs($this->admin)->test(ContributionsAdministration::class)->assertSee($financable->name)->assertDontSee('Projet Beta')->assertDontSee('Projet Gamma');
});

it('écarte une affectation à un projet non finançable même forgée', function () {
    $z = zumra($this->bob, 'Beta');
    reconnaitre($z);
    $p = projet($this->bob, $z, 'Projet Beta');
    contribuer($this->awa, null, 5000);

    Livewire::actingAs($this->admin)->test(ContributionsAdministration::class)->set('projectId', $p->id)->set('amount', '100')->set('purpose', 'x')->call('allocate')->assertSee('engagée et reconnue');
    expect(Allocation::query()->count())->toBe(0);
});

it('lit les actes du fonds dans le journal d’administration, réservé au Président', function () {
    [, $projet] = zumraFinancable($this->awa);
    contribuer($this->bob, null, 5000);
    contributions()->allocate($this->admin, $projet, 700, 'Matériel');

    Livewire::actingAs($this->admin)->test(JournalAdministration::class)->assertSee('a affecté une somme du fonds de contribution à un projet de la ZUMRA')->assertSee('Projet : '.$projet->name.' — 700 ZAHAB');
    Livewire::actingAs(User::factory()->moderator()->create())->test(JournalAdministration::class)->assertDontSee('fonds de contribution');
});

// ─── Réglages ────────────────────────────────────────────────────────────────

it('règle contribution minimum, durée d’engagement et seuil de validation, au journal', function () {
    Livewire::actingAs($this->admin)->test(ParametresPortefeuille::class)
        ->set('contributionMin', '1000')->set('engagementDays', '90')->set('validationThreshold', '20000')->call('save')->assertHasNoErrors();

    $s = app(Settings::class);
    expect($s->get('contribution_min'))->toBe(1000)->and($s->get('contribution_engagement_days'))->toBe(90)->and($s->get('wallet_zumra_validation_threshold'))->toBe(20000);
    expect(AdminAction::query()->where('action', 'setting.changed')->pluck('subject_id')->all())->toContain('contribution_min', 'contribution_engagement_days', 'wallet_zumra_validation_threshold');
});

it('refuse des réglages de contribution invalides', function (string $champ, string $valeur) {
    Livewire::actingAs($this->admin)->test(ParametresPortefeuille::class)->set($champ, $valeur)->call('save')->assertHasErrors([$champ]);
})->with([['contributionMin', '0'], ['engagementDays', '0'], ['engagementDays', '400'], ['validationThreshold', '0'], ['validationThreshold', 'x']]);
