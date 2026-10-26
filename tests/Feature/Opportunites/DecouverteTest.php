<?php

use App\Enums\PublicationType;
use App\Livewire\MatchSuggestions;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\MatchSuggestion;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Matching;
use App\Services\MatchingConditions;
use App\Services\People;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Lot D2 : « Mes pistes » propose aussi les opportunités qui correspondent, sans notification, sans score, avec des raisons.

function entrepriseDecouverte(string $name = 'Atelier Soleil', string $status = 'verified'): Company
{
    $company = new Company(['name' => $name, 'legal_name' => $name.' SARL', 'registration_country' => 'CI',
        'registration_number' => 'RCCM-'.Str::random(10), 'sector' => 'Énergie', 'city' => 'Abidjan', 'summary' => 'Une entreprise de test.']);
    $company->slug = Str::slug($name).'-'.strtolower(Str::random(6));
    $company->forceFill(['owner_id' => User::factory()->create()->id, 'status' => $status, 'verified_at' => $status === 'verified' ? now() : null])->save();

    return $company;
}

function preferencesDecouverte(User $user, array $changes = []): void
{
    $user->profile->update(['matching_preferences' => array_replace(MatchingConditions::defaults(), ['enabled' => true, 'intentions' => ['offer'], 'terms' => ['paid'], 'remote' => false], $changes)]);
    $user->unsetRelation('profile');
}

function pistes(User $user, int $limit = 10)
{
    return app(Matching::class)->suggestions($user, $limit);
}

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    $this->ville = City::query()->create(['name' => 'Ville Décou', 'country_id' => Country::query()->firstOrCreate(['name' => 'Pays Décou'])->id]);
    $this->autreVille = City::query()->create(['name' => 'Autre Ville', 'country_id' => $this->ville->country_id]);
    $this->company = entrepriseDecouverte();
    $this->moi = person('Moi Électricien', ['Électricité', 'Soudure']);
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id]]);
    $this->offre = fn (array $attributes = []) => opportunite($this->company, $attributes + ['title' => 'Technicien réseau', 'city_id' => $this->ville->id, 'skills' => ['Électricité', 'Réseaux']]);
});

it('propose une opportunité qui partage une compétence, avec des raisons compréhensibles, sans score ni notification', function () {
    $o = ($this->offre)();

    $items = pistes($this->moi);

    expect($items)->toHaveCount(1)->and($items[0]['title'])->toBe('Technicien réseau')->and($items[0]['url'])->toBe(route('opportunites.show', $o))
        ->and($items[0]['reasons'])->toContain('Capacité commune : Électricité', 'Localité choisie : '.$this->ville->label())
        ->and(implode(' ', $items[0]['reasons']))->toContain('Emploi proposé par Atelier Soleil')->not->toContain('%')
        ->and($items[0])->not->toHaveKey('score');
    expect(UserNotification::count())->toBe(0)->and(MatchSuggestion::sole()->alerted_at)->toBeNull();
});

it('ne notifie jamais, même quand la personne a activé les alertes', function () {
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'notify' => true]);
    ($this->offre)();
    ($this->offre)(['title' => 'Autre offre']);

    pistes($this->moi);
    app(Matching::class)->profileChanged($this->moi->id);

    expect(UserNotification::count())->toBe(0)->and(MatchSuggestion::whereNotNull('alerted_at')->count())->toBe(0)->and(MatchSuggestion::count())->toBe(2);
});

it('couvre les quatre catégories : emploi, stage, mission et collaboration', function () {
    foreach (['job', 'internship', 'mission', 'collaboration'] as $kind) {
        ($this->offre)(['kind' => $kind, 'title' => 'Offre '.$kind]);
    }

    expect(pistes($this->moi)->pluck('title')->sort()->values()->all())->toBe(['Offre collaboration', 'Offre internship', 'Offre job', 'Offre mission']);
});

it('respecte les préférences : intention, activation, disponibilité', function () {
    ($this->offre)();

    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'intentions' => ['learn']]);
    expect(pistes($this->moi))->toBeEmpty();
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'intentions' => ['teach', 'learn']]);
    expect(pistes($this->moi))->toBeEmpty();
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'available' => false]);
    expect(pistes($this->moi))->toBeEmpty();
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'enabled' => false]);
    expect(pistes($this->moi))->toBeEmpty();
    // Sans aucune préférence enregistrée : rien.
    $neutre = person('Neutre', ['Électricité']);
    expect(pistes($neutre))->toBeEmpty();
    // Les préférences rétablies, la piste revient.
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id]]);
    expect(pistes($this->moi))->toHaveCount(1);
});

it('n’exige qu’une compétence commune, et aucune sinon', function () {
    ($this->offre)(['skills' => ['Soudure', 'Menuiserie', 'Peinture']]);
    ($this->offre)(['title' => 'Sans lien', 'skills' => ['Comptabilité']]);
    ($this->offre)(['title' => 'Sans compétence', 'skills' => []]);

    expect(pistes($this->moi)->pluck('title')->all())->toBe(['Technicien réseau']);
});

it('exige une localité choisie, ou la distance des deux côtés', function () {
    ($this->offre)(['title' => 'Ailleurs', 'city_id' => $this->autreVille->id]);
    ($this->offre)(['title' => 'Sans lieu', 'city_id' => null]);
    expect(pistes($this->moi))->toBeEmpty();

    ($this->offre)(['title' => 'À distance', 'city_id' => null, 'work_mode' => 'remote']);
    expect(pistes($this->moi))->toBeEmpty();

    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'remote' => true]);
    $items = pistes($this->moi);
    expect($items->pluck('title')->all())->toBe(['À distance'])->and($items[0]['reasons'])->toContain('À distance possible des deux côtés');
});

it('écarte une opportunité dont la date limite précède ma disponibilité', function () {
    ($this->offre)(['deadline' => now()->addDays(5)->toDateString()]);
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'from' => now()->addDays(10)->toDateString()]);

    expect(pistes($this->moi))->toBeEmpty();
    preferencesDecouverte($this->moi, ['zones' => [$this->ville->id], 'from' => now()->addDays(2)->toDateString()]);
    expect(pistes($this->moi))->toHaveCount(1);
});

it('n’affiche plus une opportunité clôturée, archivée, expirée, en brouillon, supprimée ou d’une entreprise suspendue ou non vérifiée', function () {
    $o = ($this->offre)();
    expect(pistes($this->moi))->toHaveCount(1);

    $o->forceFill(['status' => Opportunity::CLOSED, 'closed_at' => now()])->save();
    expect(pistes($this->moi))->toBeEmpty();
    $o->forceFill(['status' => Opportunity::PUBLISHED, 'closed_at' => null])->save();
    expect(pistes($this->moi))->toHaveCount(1);
    $o->forceFill(['status' => Opportunity::ARCHIVED])->save();
    expect(pistes($this->moi))->toBeEmpty();
    $o->forceFill(['status' => Opportunity::DRAFT])->save();
    expect(pistes($this->moi))->toBeEmpty();
    $o->forceFill(['status' => Opportunity::PUBLISHED, 'deadline' => now()->subDay()->toDateString()])->save();
    expect(pistes($this->moi))->toBeEmpty();
    $o->forceFill(['deadline' => null])->save();
    expect(pistes($this->moi))->toHaveCount(1);

    $this->company->forceFill(['status' => 'suspended', 'verified_at' => null, 'review_note' => 'Motif.'])->save();
    expect(pistes($this->moi))->toBeEmpty();
    $this->company->forceFill(['status' => 'pending'])->save();
    expect(pistes($this->moi))->toBeEmpty();
    $this->company->forceFill(['status' => 'verified', 'verified_at' => now()])->save();
    expect(pistes($this->moi))->toHaveCount(1);

    $o->delete();
    expect(pistes($this->moi))->toBeEmpty()->and(MatchSuggestion::count())->toBe(0);
});

it('garde « ne m’intéresse pas » : la piste ignorée ne revient pas', function () {
    ($this->offre)();
    $row = pistes($this->moi)[0]['suggestion'];

    app(Matching::class)->ignore($this->moi, $row);

    expect(pistes($this->moi))->toBeEmpty()->and(MatchSuggestion::count())->toBe(1)->and($row->fresh()->ignored_at)->not->toBeNull();
    // Un autre compte ne peut ni voir ni ignorer cette piste.
    $autre = person('Autre', ['Électricité']);
    expect(fn () => app(Matching::class)->ignore($autre, $row))->toThrow(AuthorizationException::class);
});

it('ne propose pas à une entreprise ses propres opportunités, ni de liste de personnes correspondantes', function () {
    ($this->offre)();
    $proprietaire = $this->company->owner;
    preferencesDecouverte($proprietaire, ['zones' => [$this->ville->id]]);

    expect(pistes($proprietaire))->toBeEmpty();
    pistes($this->moi);
    // La piste n'existe que pour la personne : aucune ligne ni page ne désigne cette personne à l'entreprise.
    expect(MatchSuggestion::where('user_id', $proprietaire->id)->count())->toBe(0)->and(MatchSuggestion::where('user_id', '!=', $this->moi->id)->count())->toBe(0);
    $this->actingAs($proprietaire)->get(route('opportunites.responses', Opportunity::sole()))->assertOk()->assertDontSeeText('Moi Électricien');
});

it('ne tient pas compte d’un blocage avec le propriétaire de l’entreprise (D-199, D-200)', function () {
    ($this->offre)();
    app(People::class)->block($this->company->owner, $this->moi, true);
    expect(pistes($this->moi))->toHaveCount(1);
    app(People::class)->block($this->company->owner, $this->moi, false);
    app(People::class)->block($this->moi, $this->company->owner, true);

    expect(pistes($this->moi))->toHaveCount(1);
});

it('borne le volume : au plus une piste de plus que demandé par calcul', function () {
    foreach (range(1, 4) as $i) {
        ($this->offre)(['title' => 'Offre '.$i]);
    }

    app(Matching::class)->suggestions($this->moi, 2);

    // Comme pour les publications, on cherche une piste de plus que demandé pour savoir s'il en reste.
    expect(MatchSuggestion::count())->toBe(3);
});

it('affiche la piste dans « Mes pistes » et permet de l’ignorer', function () {
    ($this->offre)();

    $page = Livewire::actingAs($this->moi)->test(MatchSuggestions::class)
        ->assertSeeText('Technicien réseau')->assertSeeText('Opportunité de Atelier Soleil')->assertSeeText('Pourquoi cette suggestion ?')->assertSeeText('Capacité commune : Électricité')
        ->assertSee(route('opportunites.show', Opportunity::sole()), false);
    $page->call('ignore', MatchSuggestion::sole()->id)->assertDontSeeText('Technicien réseau')->assertSeeText('Cette piste a été retirée');
});

it('préserve les pistes existantes de personnes et d’offres, et refuse une piste sans cible ou à deux cibles', function () {
    $auteur = person('Atelier de couture', ['Couture']);
    $aidant = person('Personne disponible', ['Couture'], ['Couture']);
    preferencesDecouverte($aidant, ['intentions' => ['offer', 'teach', 'learn'], 'terms' => ['volunteer', 'paid', 'discuss'], 'remote' => true]);
    preferencesDecouverte($auteur, ['intentions' => ['offer', 'teach', 'learn'], 'terms' => ['volunteer', 'paid', 'discuss'], 'remote' => true]);
    app(Publications::class)->publish($auteur, PublicationType::Need, ['body' => 'Un besoin de couture', 'skills' => ['Couture'],
        'matching' => array_replace(MatchingConditions::publicationDefaults(PublicationType::Need), ['enabled' => true, 'remote' => true, 'terms' => 'volunteer'])]);

    expect(pistes($aidant))->toHaveCount(1)->and(MatchSuggestion::whereNull('opportunity_id')->count())->toBeGreaterThan(0);

    $existante = MatchSuggestion::first();
    $base = ['id' => (string) Str::uuid(), 'user_id' => $aidant->id, 'created_at' => now(), 'updated_at' => now()];
    $o = ($this->offre)();
    $essai = fn (array $colonnes) => DB::transaction(fn () => DB::table('match_suggestions')->insert($base + ['signature' => Str::random(20)] + $colonnes));
    expect(fn () => $essai(['publication_id' => $existante->publication_id]))->toThrow(QueryException::class)
        ->and(fn () => $essai(['opportunity_id' => $o->id, 'publication_id' => $existante->publication_id]))->toThrow(QueryException::class)
        ->and(fn () => $essai(['opportunity_id' => $o->id, 'profile_id' => $aidant->profile->id]))->toThrow(QueryException::class);
    $essai(['opportunity_id' => $o->id]);
    expect(MatchSuggestion::where('opportunity_id', $o->id)->count())->toBe(1);
});
