<?php

use App\Exceptions\DiscoveryLimitReached;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\ModifierProfil;
use App\Livewire\OpportunityPeople;
use App\Models\City;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\MatchSuggestion;
use App\Models\Opportunity;
use App\Models\PersonConnection;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CompanyDiscovery;
use App\Services\ComplementaryPeople;
use App\Services\Matching;
use App\Services\MatchingConditions;
use App\Services\People;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Lot D3 : découverte de personnes par une entreprise vérifiée. Consentement distinct, profils visibles, rotation neutre, plafonds.

function entrepriseD3(string $name = 'Atelier Soleil', string $status = 'verified'): Company
{
    $company = new Company(['name' => $name, 'legal_name' => $name.' SARL', 'registration_country' => 'CI',
        'registration_number' => 'RCCM-'.Str::random(10), 'sector' => 'Énergie', 'city' => 'Abidjan', 'summary' => 'Une entreprise de test.']);
    $company->slug = Str::slug($name).'-'.strtolower(Str::random(8));
    $company->forceFill(['owner_id' => User::factory()->create()->id, 'status' => $status, 'verified_at' => $status === 'verified' ? now() : null])->save();

    return $company;
}

/** Choix de la personne : consentement et, facultativement, d'autres préférences privées. */
function consentement(User $user, bool $consent = true, array $autres = []): void
{
    $user->profile->update(['matching_preferences' => array_replace(MatchingConditions::defaults(), ['discoverable' => $consent], $autres)]);
    $user->unsetRelation('profile');
}

function personnesTrouvees(User $owner, Opportunity $opportunity, ?string $cursor = null): array
{
    $page = app(CompanyDiscovery::class)->page($owner, $opportunity, $cursor);

    return ['noms' => $page['people']->map(fn ($p) => $p->user->name)->all(), 'next' => $page['next'], 'people' => $page['people']];
}

beforeEach(function () {
    $this->pays = Country::query()->firstOrCreate(['name' => 'Pays D3']);
    $this->ville = City::query()->create(['name' => 'Ville Atelier', 'country_id' => $this->pays->id]);
    $this->company = entrepriseD3();
    $this->owner = $this->company->owner;
    $this->offre = opportunite($this->company, ['title' => 'Technicien réseau', 'city_id' => $this->ville->id, 'skills' => ['Électricité', 'Réseaux']]);
});

// ─── Consentement ───────────────────────────────────────────────────────────

it('ne montre personne par défaut : le consentement est désactivé tant que la personne ne l’a pas donné', function () {
    person('Awa Électricienne', ['Électricité']);

    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBeEmpty()
        ->and(MatchingConditions::defaults()['discoverable'])->toBeFalse();
});

it('montre la personne qui consent, la retire dès qu’elle retire son consentement, indépendamment de « Mes pistes »', function () {
    $awa = person('Awa Électricienne', ['Électricité']);

    consentement($awa, true, ['enabled' => false]);
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBe(['Awa Électricienne']);

    consentement($awa, true, ['enabled' => true, 'intentions' => ['learn'], 'terms' => ['paid'], 'remote' => true]);
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBe(['Awa Électricienne']);

    consentement($awa, false, ['enabled' => true, 'intentions' => ['offer'], 'terms' => ['paid'], 'remote' => true]);
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBeEmpty();
});

it('enregistre le choix depuis les préférences du profil, désactivé par défaut et sans lien avec « Mes pistes »', function () {
    $awa = person('Awa Électricienne', ['Électricité']);

    $page = Livewire::actingAs($awa)->test(ModifierProfil::class)
        ->assertSeeText('Permettre aux entreprises vérifiées de me découvrir pour leurs opportunités')->assertSet('matching_preferences.discoverable', false);
    $page->set('matching_preferences.discoverable', true)->call('save')->assertHasNoErrors();

    expect($awa->fresh()->profile->matching_preferences['discoverable'])->toBeTrue()->and($awa->fresh()->profile->matching_preferences['enabled'])->toBeFalse();
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBe(['Awa Électricienne']);

    Livewire::actingAs($awa)->test(ModifierProfil::class)->assertSet('matching_preferences.discoverable', true)->set('matching_preferences.discoverable', false)->call('save')->assertHasNoErrors();
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBeEmpty();
});

it('ne change pas la visibilité du profil dans l’annuaire Personnes', function () {
    $awa = person('Awa Électricienne', ['Électricité']);
    $voir = fn () => Livewire::actingAs(User::factory()->create())->test(CarrefourPersonnes::class)->viewData('people')->getCollection()->pluck('user.name')->all();
    $avant = $voir();

    consentement($awa, true);

    expect($voir())->toBe($avant)->and($avant)->toContain('Awa Électricienne');
});

// ─── Autorisations ──────────────────────────────────────────────────────────

it('réserve la découverte au propriétaire d’une entreprise vérifiée et non suspendue, pour une opportunité publiée et ouverte', function () {
    $this->get(route('opportunites.people', $this->offre))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('opportunites.people', $this->offre))->assertForbidden();
    $this->actingAs($this->owner)->get(route('opportunites.people', $this->offre))->assertOk()->assertSeeText('Découvrir des personnes');

    foreach ([Opportunity::DRAFT, Opportunity::CLOSED, Opportunity::ARCHIVED] as $status) {
        $this->offre->forceFill(['status' => $status])->save();
        $this->actingAs($this->owner)->get(route('opportunites.people', $this->offre))->assertForbidden();
    }
    $this->offre->forceFill(['status' => Opportunity::PUBLISHED, 'deadline' => now()->subDay()->toDateString()])->save();
    $this->actingAs($this->owner)->get(route('opportunites.people', $this->offre))->assertForbidden();
    $this->offre->forceFill(['deadline' => null])->save();

    foreach (['suspended', 'pending', 'declined'] as $status) {
        $this->company->forceFill(['status' => $status, 'verified_at' => null, 'review_note' => 'Motif.'])->save();
        $this->actingAs($this->owner)->get(route('opportunites.people', $this->offre))->assertForbidden();
    }
    $this->company->forceFill(['status' => 'verified', 'verified_at' => now()])->save();
    $this->actingAs($this->owner)->get(route('opportunites.people', $this->offre))->assertOk();
    expect(fn () => app(CompanyDiscovery::class)->page(User::factory()->create(), $this->offre))->toThrow(AuthorizationException::class);
});

it('propose l’action « Découvrir des personnes » seulement au propriétaire, pour une opportunité publiée et ouverte', function () {
    $lien = route('opportunites.people', $this->offre);

    $this->actingAs($this->owner)->get(route('entreprises.opportunities', $this->company))->assertOk()->assertSeeText('Découvrir des personnes')->assertSee($lien, false);
    $this->get(route('opportunites.show', $this->offre))->assertSee($lien, false);
    $this->actingAs(User::factory()->create())->get(route('opportunites.show', $this->offre))->assertDontSee($lien, false);
    $this->get(route('opportunites.show', $this->offre))->assertDontSee($lien, false);

    $this->offre->forceFill(['status' => Opportunity::CLOSED])->save();
    $this->actingAs($this->owner)->get(route('entreprises.opportunities', $this->company))->assertDontSee($lien, false);
    $this->get(route('opportunites.show', $this->offre))->assertDontSee($lien, false);
});

// ─── Visibilité et filtres ──────────────────────────────────────────────────

it('ne montre que les profils visibles du propriétaire, avec une capacité proposée recherchée par l’opportunité', function () {
    $visible = person('Awa Visible', ['Électricité']);
    $membres = person('Yao Membres', ['Réseaux'], visibility: 'members');
    $sans = person('Zoé Sans lien', ['Comptabilité']);
    $apprend = person('Koffi Apprend', [], ['Électricité']);
    $suspendu = person('Moussa Suspendu', ['Électricité']);
    $bloque = person('Fatou Bloquée', ['Électricité']);
    $bloqueuse = person('Ibrahim Bloqueur', ['Électricité']);
    foreach ([$visible, $membres, $sans, $apprend, $suspendu, $bloque, $bloqueuse] as $personne) {
        consentement($personne);
    }
    $suspendu->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Test.'])->save();
    app(People::class)->block($this->owner, $bloque, true);
    app(People::class)->block($bloqueuse, $this->owner, true);

    $noms = personnesTrouvees($this->owner, $this->offre)['noms'];

    expect($noms)->toContain('Awa Visible', 'Yao Membres')->not->toContain('Zoé Sans lien', 'Koffi Apprend', 'Moussa Suspendu', 'Fatou Bloquée', 'Ibrahim Bloqueur')->toHaveCount(2);
    // Un profil « membres » obéit aux règles actuelles : le propriétaire est connecté avec un e-mail vérifié.
    $inconnu = User::factory()->unverified()->create();
    expect(fn () => app(CompanyDiscovery::class)->page($inconnu, $this->offre))->toThrow(AuthorizationException::class);
});

it('prend en compte immédiatement un retrait de consentement, une suspension ou un blocage', function () {
    $awa = person('Awa Visible', ['Électricité']);
    $yao = person('Yao Visible', ['Réseaux']);
    consentement($awa);
    consentement($yao);
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toHaveCount(2);

    consentement($awa, false);
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBe(['Yao Visible']);
    $yao->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Test.'])->save();
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBeEmpty();
});

// ─── Présentation, confidentialité ──────────────────────────────────────────

it('affiche seulement ce que le profil rend visible, et des raisons tirées de données publiques', function () {
    $awa = person('Awa Électricienne', ['Électricité', 'Menuiserie'], ['Comptabilité'], 'Ville Atelier', bio: 'Une biographie privée trop longue pour la carte.');
    $awa->profile->update(['headline' => 'Électricienne du bâtiment']);
    consentement($awa);

    $page = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre]);
    $html = $page->html();

    $page->assertSeeText('Awa Électricienne')->assertSeeText('Électricienne du bâtiment')->assertSeeText('Ville Atelier')
        ->assertSeeText('Capacité proposée en commun : Électricité')->assertSeeText('Même localité déclarée : Ville Atelier')->assertSee(route('personnes.show', $awa->profile->public_id), false);
    // Seules les capacités pertinentes (en commun) sont montrées : ni les autres capacités, ni ce que la personne veut apprendre, ni sa biographie.
    expect($html)->not->toContain('Menuiserie')->not->toContain('Comptabilité')->not->toContain('biographie');
});

it('n’expose jamais les préférences privées de découverte, même indirectement', function () {
    $zone = City::query()->create(['name' => 'Zone Secrète', 'country_id' => $this->pays->id]);
    $awa = person('Awa Électricienne', ['Électricité'], city: 'Ville Atelier');
    consentement($awa, true, ['enabled' => true, 'intentions' => ['offer', 'teach'], 'terms' => ['paid'], 'zones' => [$zone->id], 'remote' => true, 'note' => 'Note privée de disponibilité', 'from' => '2027-01-01', 'days' => [1, 2]]);

    $html = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre])->html();

    foreach (['Zone Secrète', 'Note privée', '2027', 'Rémunéré', 'Transmettre', 'Proposer mes compétences', 'À distance', 'discoverable', 'matching', 'Lundi'] as $secret) {
        expect($html)->not->toContain($secret);
    }
    // La localité d'une raison vient du profil, jamais des localités choisies en préférences.
    $loin = person('Yao Ailleurs', ['Réseaux']);
    consentement($loin, true, ['zones' => [$this->ville->id]]);
    $raisons = collect(personnesTrouvees($this->owner, $this->offre)['people'])->firstWhere('user.name', 'Yao Ailleurs')->reasons;
    expect($raisons)->toBe(['Capacité proposée en commun : Réseaux']);
});

it('n’emploie jamais les mots d’un classement ou d’un candidat présumé, ni score, pourcentage ou total', function () {
    foreach (['Awa', 'Yao', 'Koffi'] as $nom) {
        consentement(person($nom.' Électricien', ['Électricité']));
    }

    $texte = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre])->html();
    $lisible = mb_strtolower(strip_tags($texte));

    foreach (['candidat', 'recommandé', 'intéressé', 'présum', 'meilleur', 'score', 'compatibilité', '%', 'résultats', 'personnes trouvées', 'au total'] as $mot) {
        expect($lisible)->not->toContain($mot);
    }
});

it('ne crée aucun canal de contact, aucune notification, aucune piste ni aucune relation', function () {
    consentement(person('Awa Électricienne', ['Électricité']));
    $avant = UserNotification::count();

    $page = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre]);

    expect(UserNotification::count())->toBe($avant)->and(MatchSuggestion::count())->toBe(0)->and(Conversation::count())->toBe(0)->and(PersonConnection::count())->toBe(0);
    expect($page->html())->not->toContain('<form')->not->toContain('wire:submit');
    // Le seul pas vers la personne est son profil, où la demande de relation manuelle existante reste soumise à ses règles.
    expect(DB::table('company_discovery_views')->count())->toBe(1);
    $colonnes = DB::getSchemaBuilder()->getColumnListing('company_discovery_views');
    expect($colonnes)->toBe(['company_id', 'day', 'views']);
});

// ─── Pagination et rotation ─────────────────────────────────────────────────

it('pagine par dix sans répéter un profil, sans total, et garde le même ordre pendant la journée', function () {
    foreach (range(1, 25) as $i) {
        consentement(person(sprintf('Personne %02d', $i), ['Électricité']));
    }

    $un = personnesTrouvees($this->owner, $this->offre);
    $deux = personnesTrouvees($this->owner, $this->offre, $un['next']);
    $trois = personnesTrouvees($this->owner, $this->offre, $deux['next']);

    expect($un['noms'])->toHaveCount(10)->and($deux['noms'])->toHaveCount(10)->and($trois['noms'])->toHaveCount(5)->and($trois['next'])->toBeNull();
    $tous = array_merge($un['noms'], $deux['noms'], $trois['noms']);
    expect($tous)->toHaveCount(25)->and(array_unique($tous))->toHaveCount(25);
    // Stable pendant la journée : la même première page à chaque fois.
    expect(personnesTrouvees($this->owner, $this->offre)['noms'])->toBe($un['noms']);
    // Neutre : l'ordre n'est pas l'ordre alphabétique.
    $alphabetique = $tous;
    sort($alphabetique);
    expect($tous)->not->toBe($alphabetique);
});

it('change l’ordre d’un jour à l’autre et d’une opportunité à l’autre', function () {
    foreach (range(1, 25) as $i) {
        consentement(person(sprintf('Personne %02d', $i), ['Électricité']));
    }
    $autre = opportunite($this->company, ['title' => 'Autre poste', 'skills' => ['Électricité']]);

    $aujourdhui = personnesTrouvees($this->owner, $this->offre)['noms'];
    $autrePoste = personnesTrouvees($this->owner, $autre)['noms'];
    Carbon::setTestNow(now()->addDay());
    $demain = personnesTrouvees($this->owner, $this->offre)['noms'];
    Carbon::setTestNow();

    expect($demain)->not->toBe($aujourdhui)->and($autrePoste)->not->toBe($aujourdhui);
});

it('parcourt les pages dans l’interface sans répétition, et ignore un repère falsifié', function () {
    foreach (range(1, 14) as $i) {
        consentement(person(sprintf('Personne %02d', $i), ['Électricité']));
    }
    $noms = fn ($page) => collect($page->viewData('people'))->map(fn ($p) => $p->user->name)->all();

    $page = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre])->assertSeeText('Page suivante')->assertDontSeeText('Page précédente');
    $premiere = $noms($page);
    $page->call('nextPage')->assertSeeText('Page précédente')->assertDontSeeText('Page suivante');
    $seconde = $noms($page);

    expect($premiere)->toHaveCount(10)->and($seconde)->toHaveCount(4)->and(array_intersect($premiere, $seconde))->toBeEmpty();
    expect($noms($page->call('previousPage')))->toBe($premiere);

    $falsifie = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre])->set('cursors', ["x'; drop table users;--"]);
    expect($noms($falsifie))->toBe($premiere);
});

it('prend en compte un retrait de consentement pendant la pagination, sans répéter de profil', function () {
    foreach (range(1, 14) as $i) {
        $personnes[$i] = person(sprintf('Personne %02d', $i), ['Électricité']);
        consentement($personnes[$i]);
    }
    $noms = fn ($page) => collect($page->viewData('people'))->map(fn ($p) => $p->user->name)->all();
    $page = Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre]);
    $premiere = $noms($page);
    $suivantes = array_values(array_diff(array_map(fn ($p) => $p->name, $personnes), $premiere));
    $retiree = $personnes[(int) substr($suivantes[0], -2)];

    consentement($retiree, false);
    $seconde = $noms($page->call('nextPage'));

    expect($seconde)->not->toContain($retiree->name)->and($seconde)->toHaveCount(3)->and(array_intersect($premiere, $seconde))->toBeEmpty();
});

// ─── Plafond quotidien ──────────────────────────────────────────────────────

it('limite à vingt pages par jour et par entreprise, côté serveur', function () {
    consentement(person('Awa Électricienne', ['Électricité']));
    $service = app(CompanyDiscovery::class);

    foreach (range(1, 20) as $i) {
        expect($service->page($this->owner, $this->offre)['people'])->toHaveCount(1);
    }
    expect(fn () => $service->page($this->owner, $this->offre))->toThrow(DiscoveryLimitReached::class);
    expect(DB::table('company_discovery_views')->where('company_id', $this->company->id)->value('views'))->toBe(20);

    Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $this->offre])->assertSeeText('limite quotidienne')->assertDontSeeText('Awa Électricienne');
    expect(DB::table('company_discovery_views')->where('company_id', $this->company->id)->value('views'))->toBe(20);

    // Une autre entreprise a son propre plafond ; le lendemain, le plafond repart.
    $autre = entrepriseD3('Autre Société');
    $offreAutre = opportunite($autre, ['title' => 'Poste', 'skills' => ['Électricité']]);
    expect($service->page($autre->owner, $offreAutre)['people'])->toHaveCount(1);
    Carbon::setTestNow(now()->addDay());
    expect($service->page($this->owner, $this->offre)['people'])->toHaveCount(1);
    Carbon::setTestNow();
});

it('consomme le plafond par une seule instruction atomique, jamais en lisant puis en écrivant', function () {
    $service = app(CompanyDiscovery::class);
    DB::enableQueryLog();

    $accordees = collect(range(1, 30))->filter(fn () => $service->consume($this->company))->count();
    $requetes = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'company_discovery_views'))->values();

    expect($accordees)->toBe(20)->and(DB::table('company_discovery_views')->value('views'))->toBe(20);
    $consommation = $requetes->filter(fn ($q) => str_contains($q, 'on conflict'));
    expect($consommation)->toHaveCount(30)->and($consommation->every(fn ($q) => str_contains($q, 'where "company_discovery_views"."views" <') || str_contains($q, 'company_discovery_views.views <')))->toBeTrue();
    expect($requetes->filter(fn ($q) => str_starts_with(strtolower($q), 'select')))->toBeEmpty();
});

it('réutilise les services existants : sélection par ComplementaryPeople, raisons par Matching', function () {
    $awa = person('Awa Électricienne', ['Électricité'], city: 'Ville Atelier');
    consentement($awa);

    $viaService = app(ComplementaryPeople::class)->discoverableFor($this->owner, $this->offre)->get();
    $raisons = app(Matching::class)->companyReasons($this->offre, $viaService->first());

    expect($viaService->pluck('user.name')->all())->toBe(['Awa Électricienne'])->and($raisons)->toBe(['Capacité proposée en commun : Électricité', 'Même localité déclarée : Ville Atelier']);
});

it('ne montre rien pour une opportunité sans compétence recherchée', function () {
    consentement(person('Awa Électricienne', ['Électricité']));
    $sans = opportunite($this->company, ['title' => 'Sans compétence', 'skills' => []]);

    expect(personnesTrouvees($this->owner, $sans)['noms'])->toBeEmpty();
    Livewire::actingAs($this->owner)->test(OpportunityPeople::class, ['opportunity' => $sans])->assertSeeText('Aucune personne visible pour le moment.');
});
