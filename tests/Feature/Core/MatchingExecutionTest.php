<?php

use App\Core\Execution\BackgroundRunner;
use App\Core\Execution\ExecutionRun;
use App\Enums\NeedState;
use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Livewire\MatchSuggestions;
use App\Livewire\PagePublication;
use App\Livewire\SearchStatus;
use App\Models\City;
use App\Models\MatchingSearch;
use App\Models\MatchSuggestion;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Matching;
use App\Services\MatchingConditions;
use App\Services\MatchingSearches;
use App\Services\Moderation;
use App\Services\People;
use App\Services\ProfileEditor;
use App\Services\Publications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Matching confié à l'exécution : recherches reprenables, états fidèles, retraits immédiats (sans traitement différé).

function publierSansTraiter(User $user, PublicationType $type = PublicationType::Need, array $changes = [], array $data = []): Publication
{
    return app(Publications::class)->publish($user, $type, ['body' => 'Une occasion pour la couture', 'skills' => ['Couture'], 'matching' => array_replace(MatchingConditions::publicationDefaults($type), ['enabled' => true, 'remote' => true, 'terms' => 'volunteer'], $changes), ...$data])->fresh();
}

function piste(User $user): ?MatchSuggestion
{
    return MatchSuggestion::query()->where('user_id', $user->id)->first();
}

function rechercheDe(Publication $post): MatchingSearch
{
    return MatchingSearch::query()->where('publication_id', $post->id)->orderByDesc('id')->firstOrFail();
}

function pistesVisibles(User $user): int
{
    return app(Matching::class)->suggestions($user)->count();
}

beforeEach(function () {
    $this->author = person('Atelier de couture', ['Couture']);
    $this->helper = person('Personne disponible', ['Couture'], ['Couture']);
    matchingPreferences($this->author, ['notify' => true]);
    matchingPreferences($this->helper, ['notify' => true]);
    $this->engine = app(Matching::class);
});

it('publie sans balayer les profils dans la requête : la recherche attend dans la file', function () {
    foreach (range(1, 12) as $i) {
        matchingPreferences(person("Personne $i", ['Couture']));
    }

    DB::enableQueryLog();
    $post = publierSansTraiter($this->author);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(MatchSuggestion::count())->toBe(0)->and($queries)->toBeLessThan(150)
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(rechercheDe($post)->state)->toBe(MatchingSearch::QUEUED);

    drainMatching();

    $search = MatchingSearch::query()->where('publication_id', $post->id)->sole();
    expect($search->state)->toBe(MatchingSearch::DONE)->and($search->produced)->toBe(13)
        ->and(MatchSuggestion::query()->where('publication_id', $post->id)->count())->toBe(26);
});

it('découpe une grande recherche en tranches reprenables au lieu de monopoliser un processus', function () {
    foreach (range(1, 54) as $i) {
        matchingPreferences(person("Personne $i", ['Couture']));
    }
    $runsBefore = ExecutionRun::query()->where('job', 'RunMatchingSearch')->count();
    $post = publierSansTraiter($this->author);

    expect(app(BackgroundRunner::class)->run(1, 30))->toBe(1);

    $search = rechercheDe($post);
    expect($search->state)->toBe(MatchingSearch::RUNNING)->and($search->cursor)->not->toBeNull()->and($search->produced)->toBe(50)
        ->and(DB::table('jobs')->count())->toBe(1);

    drainMatching();

    expect($search->fresh()->state)->toBe(MatchingSearch::DONE)->and($search->fresh()->produced)->toBe(55)
        ->and(ExecutionRun::query()->where('job', 'RunMatchingSearch')->count() - $runsBefore)->toBe(2);
});

it('regroupe les demandes en double et relance une fois après une recherche en cours', function () {
    $post = publierSansTraiter($this->author);
    $searches = app(MatchingSearches::class);

    $first = $searches->request(MatchingSearches::FOR_PUBLICATION, $post->id);
    $second = $searches->request(MatchingSearches::FOR_PUBLICATION, $post->id);

    $forPost = fn () => MatchingSearch::query()->where('publication_id', $post->id);
    expect($second->is($first))->toBeTrue()->and($forPost()->count())->toBe(1)->and(DB::table('jobs')->count())->toBe(1);

    $searches->begin($first->id);
    $searches->request(MatchingSearches::FOR_PUBLICATION, $post->id);
    $searches->request(MatchingSearches::FOR_PUBLICATION, $post->id);
    expect($first->fresh()->rerun)->toBeTrue()->and($forPost()->count())->toBe(1);

    $searches->finish($first->id, 0);

    expect($forPost()->where('state', 'queued')->count())->toBe(1)->and($forPost()->count())->toBe(2)->and(DB::table('jobs')->count())->toBe(2);
});

it('ne crée pas de doublon quand la même recherche est traitée deux fois', function () {
    $post = publierSansTraiter($this->author);
    drainMatching();
    $rows = MatchSuggestion::count();

    app(Matching::class)->searchPublication($post->fresh());
    $cursor = null;
    do {
        $cursor = app(Matching::class)->searchPerson($this->helper->fresh(), $cursor)['cursor'];
    } while ($cursor !== null);

    expect(MatchSuggestion::count())->toBe($rows)->and($rows)->toBe(2);
});

it('affiche l’état réel : en attente, en cours, à reprendre, indisponible, terminée', function () {
    $post = publierSansTraiter($this->author);
    $page = fn () => Livewire::actingAs($this->author)->test(PagePublication::class, ['publication' => $post]);

    $page()->assertSeeText('Recherche en attente')->assertDontSeeText('Recherche terminée');

    $search = rechercheDe($post);
    app(MatchingSearches::class)->begin($search->id);
    $page()->assertSeeText('Recherche en cours')->assertDontSeeText('Recherche terminée');

    // Une exécution interrompue qui ne donne plus signe de vie n'est pas présentée comme « en cours ».
    $search->forceFill(['heartbeat_at' => now()->subMinutes(10)])->save();
    $page()->assertDontSeeText('Recherche en cours')->assertSeeText('Recherche en attente');

    app(MatchingSearches::class)->retrying($search->id, 'RuntimeException');
    $page()->assertSeeText('Recherche à reprendre')->assertDontSeeText('Recherche terminée');

    app(MatchingSearches::class)->fail($search->id, 'RuntimeException');
    $page()->assertSeeText('Recherche momentanément indisponible')->assertSeeText('aucune conclusion ne peut être tirée')->assertDontSeeText('Aucune piste pour le moment')->assertDontSeeText('Recherche terminée');
});

it('annonce « terminée » seulement une fois la recherche faite, avec le nombre réel de pistes', function () {
    $post = publierSansTraiter($this->author);
    drainMatching();

    Livewire::actingAs($this->author)->test(PagePublication::class, ['publication' => $post])->assertSeeText('Recherche terminée')->assertSeeText('1 piste à explorer');
});

it('ne transforme pas un échec technique en « aucune correspondance » : reprises avec délai puis échec affiché', function () {
    $post = publierSansTraiter($this->author);
    $this->mock(Matching::class, fn ($mock) => $mock->shouldReceive('isSearchable')->andReturn(true)->shouldReceive('searchPublication')->andThrow(new RuntimeException('panne')));

    drainMatching();
    $search = rechercheDe($post);
    expect($search->state)->toBe(MatchingSearch::QUEUED)->and($search->attempts)->toBe(1)->and($search->status())->toBe('retrying');

    foreach ([31, 121, 601, 1801] as $wait) {
        $this->travel($wait)->seconds();
        drainMatching();
    }

    $search = $search->fresh();
    expect($search->state)->toBe(MatchingSearch::FAILED)->and($search->status())->toBe('unavailable')->and($search->error_code)->toBe('RuntimeException')
        ->and(DB::table('failed_jobs')->count())->toBe(1)->and(MatchSuggestion::count())->toBe(0);
});

it('reprend une recherche après une erreur passagère et aboutit', function () {
    $post = publierSansTraiter($this->author);
    $real = app(Matching::class);
    $calls = 0;
    $this->mock(Matching::class, function ($mock) use ($real, &$calls) {
        $mock->shouldReceive('isSearchable')->andReturn(true);
        $mock->shouldReceive('searchPublication')->andReturnUsing(function (...$args) use ($real, &$calls) {
            if ($calls++ === 0) {
                throw new RuntimeException('incident passager');
            }

            return $real->searchPublication(...$args);
        });
    });

    drainMatching();
    expect(MatchSuggestion::count())->toBe(0)->and(rechercheDe($post)->status())->toBe('retrying');

    $this->travel(31)->seconds();
    drainMatching();

    expect(rechercheDe($post)->state)->toBe(MatchingSearch::DONE)->and(MatchSuggestion::count())->toBe(2)
        ->and(ExecutionRun::query()->where('job', 'RunMatchingSearch')->where('outcome', 'failure')->count())->toBe(1);
});

it('annule une recherche devenue obsolète avant son exécution, sans piste ni alerte', function () {
    $post = publierSansTraiter($this->author);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $post, 'Motif de recette');

    expect(rechercheDe($post)->state)->toBe(MatchingSearch::CANCELLED);

    drainMatching();

    expect(MatchSuggestion::count())->toBe(0)->and(UserNotification::where('kind', NotificationKind::MatchFound)->count())->toBe(0);
});

// --- Retraits immédiats : jamais en attente d'un traitement différé ------------------------------------------------------------

function pisteActive(object $test): Publication
{
    $post = matchingPublication($test->author);
    expect(pistesVisibles($test->helper))->toBe(1)->and(pistesVisibles($test->author))->toBe(1);
    DB::table('jobs')->delete();

    return $post;
}

it('retire tout de suite les pistes d’une publication masquée', function () {
    $post = pisteActive($this);

    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $post, 'Motif de recette');

    expect(DB::table('jobs')->count())->toBe(0)->and(pistesVisibles($this->helper))->toBe(0)
        ->and(MatchSuggestion::query()->whereNull('withdrawn_at')->count())->toBe(0)
        ->and(MatchSuggestion::query()->pluck('withdrawn_reason')->unique()->all())->toBe(['hidden']);
});

it('retire tout de suite les pistes d’un besoin résolu ou fermé', function () {
    $post = pisteActive($this);

    app(Publications::class)->resolve($this->author, $post);

    expect(pistesVisibles($this->helper))->toBe(0)->and(MatchSuggestion::query()->pluck('withdrawn_reason')->unique()->all())->toBe(['resolved']);

    $autre = matchingPublication($this->author, data: ['body' => 'Un second besoin']);
    expect(pistesVisibles($this->helper))->toBe(1);
    app(Publications::class)->close($this->author, $autre);

    expect(pistesVisibles($this->helper))->toBe(0)->and(MatchSuggestion::query()->where('publication_id', $autre->id)->pluck('withdrawn_reason')->unique()->all())->toBe(['closed']);
});

it('retire tout de suite les pistes d’une publication fermée au matching', function () {
    $post = pisteActive($this);

    Livewire::actingAs($this->author)->test(PagePublication::class, ['publication' => $post])->call('closeMatching');

    expect(pistesVisibles($this->helper))->toBe(0)->and(MatchSuggestion::query()->pluck('withdrawn_reason')->unique()->all())->toBe(['closed']);
});

it('retire tout de suite les pistes entre deux personnes dès qu’une bloque l’autre, des deux côtés', function () {
    $post = pisteActive($this);
    $tiers = person('Tiers', ['Couture']);
    matchingPreferences($tiers);
    $autrePiste = matchingPublication($this->author, data: ['body' => 'Un autre besoin']);
    DB::table('jobs')->delete();
    $tiersRows = MatchSuggestion::query()->where('user_id', $tiers->id)->count();
    UserNotification::query()->update(['read_at' => null]);

    app(People::class)->block($this->helper, $this->author, true);

    expect(pistesVisibles($this->helper))->toBe(0)->and(pistesVisibles($this->author))->toBe(2) // restent les pistes avec le tiers
        ->and(pistesVisibles($tiers))->toBe(2)
        ->and(MatchSuggestion::query()->where('user_id', $tiers->id)->whereNull('withdrawn_at')->count())->toBe($tiersRows)
        ->and(MatchSuggestion::query()->whereNotNull('withdrawn_at')->pluck('withdrawn_reason')->unique()->all())->toBe(['blocked'])
        ->and(UserNotification::where('user_id', $this->helper->id)->where('kind', NotificationKind::MatchFound)->whereNull('read_at')->count())->toBe(0);
});

it('retire tout de suite toutes les pistes d’une personne qui retire son consentement, chez elle et chez les autres', function () {
    $post = pisteActive($this);

    $this->helper->profile->update(['matching_preferences' => array_replace(MatchingConditions::defaults(), ['enabled' => false])]);

    expect(pistesVisibles($this->helper))->toBe(0)->and(pistesVisibles($this->author))->toBe(0)
        ->and(MatchSuggestion::query()->whereNull('withdrawn_at')->count())->toBe(0)
        ->and(MatchSuggestion::query()->pluck('withdrawn_reason')->unique()->all())->toBe(['consent']);
});

it('retire tout de suite les pistes d’une personne qui se déclare indisponible', function () {
    pisteActive($this);

    $this->helper->profile->update(['matching_preferences' => array_replace($this->helper->profile->matching_preferences, ['available' => false])]);

    expect(pistesVisibles($this->helper))->toBe(0)->and(pistesVisibles($this->author))->toBe(0)
        ->and(MatchSuggestion::query()->pluck('withdrawn_reason')->unique()->all())->toBe(['consent']);
});

it('retire tout de suite les pistes d’une personne suspendue, et les rend après la levée de la suspension', function () {
    pisteActive($this);

    $this->helper->forceFill(['suspended_at' => now(), 'suspended_by' => User::factory()->moderator()->create()->id, 'suspension_reason' => 'Propos répétés.'])->save();

    expect(pistesVisibles($this->author))->toBe(0)->and(MatchSuggestion::query()->pluck('withdrawn_reason')->unique()->all())->toBe(['suspended']);

    $this->helper->forceFill(['suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
    drainMatching();

    expect(pistesVisibles($this->author))->toBe(1)->and(pistesVisibles($this->helper))->toBe(1);
});

it('ne produit aucune nouvelle alerte pour une piste retirée puis revenue, et efface l’alerte devenue vaine', function () {
    $post = matchingPublication($this->author);
    $alerts = fn () => UserNotification::where('user_id', $this->helper->id)->where('kind', NotificationKind::MatchFound);
    expect($alerts()->count())->toBe(1)->and($alerts()->whereNull('read_at')->count())->toBe(1);

    $moderator = User::factory()->superAdmin()->create();
    app(Moderation::class)->hide($moderator, $post, 'Motif de recette');

    expect($alerts()->whereNull('read_at')->count())->toBe(0);

    app(Moderation::class)->restore($moderator, $post->fresh());
    drainMatching();

    expect(pistesVisibles($this->helper))->toBe(1)->and($alerts()->count())->toBe(1)->and($alerts()->whereNull('read_at')->count())->toBe(0);
})->skip(fn () => ! method_exists(Moderation::class, 'restore'));

it('applique les droits à chaque lecture même si l’état persistant n’a pas été mis à jour', function () {
    pisteActive($this);
    // Retrait « oublié » : la ligne reste active en base, mais la personne a bloqué l'autre par un autre chemin.
    DB::table('person_blocks')->insert(['user_id' => $this->helper->id, 'blocked_id' => $this->author->id, 'created_at' => now()]);
    request()->attributes->remove('gamad.blocks.'.$this->helper->id);
    request()->attributes->remove('gamad.blocks.'.$this->author->id);

    expect(MatchSuggestion::query()->whereNull('withdrawn_at')->count())->toBe(2)->and(pistesVisibles($this->helper))->toBe(0);
});

// --- Lecture des résultats, compétences, première visite ---------------------------------------------------------------------

it('lit des résultats sans lancer de calcul : une publication sans recherche faite ne produit rien à la lecture', function () {
    publierSansTraiter($this->author);

    expect(pistesVisibles($this->helper))->toBe(0)->and(MatchSuggestion::count())->toBe(0);
});

it('relance la recherche d’une personne quand ses compétences changent', function () {
    $newcomer = person('Nouvelle personne', ['Cuisine']);
    matchingPreferences($newcomer);
    matchingPublication($this->author);
    expect(pistesVisibles($newcomer))->toBe(0);

    (new ProfileEditor)->save($newcomer, [
        'name' => $newcomer->name, 'bio' => null, 'country_id' => null, 'city_id' => null, 'visibility' => 'public',
        'offered' => ['Cuisine', 'Couture'], 'wanted' => [], 'domain_ids' => [],
    ]);
    $search = MatchingSearch::query()->where('user_id', $newcomer->id)->where('state', 'queued')->first();

    expect($search)->not->toBeNull()->and(pistesVisibles($newcomer))->toBe(0);

    drainMatching();

    expect(pistesVisibles($newcomer->fresh()))->toBe(1);
});

it('demande la recherche d’une personne dès sa première visite de « Mes pistes » et le dit honnêtement', function () {
    MatchingSearch::query()->delete();
    DB::table('jobs')->delete();

    Livewire::actingAs($this->helper)->test(MatchSuggestions::class)->assertSeeText('Recherche en attente')->assertDontSeeText('Recherche terminée');

    expect(MatchingSearch::query()->where('user_id', $this->helper->id)->where('state', 'queued')->count())->toBe(1);
});

it('permet de relancer une recherche indisponible, avec une limite', function () {
    $search = app(MatchingSearches::class)->request(MatchingSearches::FOR_PERSON, $this->helper->id);
    app(MatchingSearches::class)->fail($search->id, 'RuntimeException');

    Livewire::actingAs($this->helper)->test(MatchSuggestions::class)->assertSeeText('Recherche momentanément indisponible')->assertSeeText('Relancer la recherche');
    $page = Livewire::actingAs($this->helper)->test(SearchStatus::class);
    $page->call('retry')->assertSeeText('relancée');
    expect(MatchingSearch::query()->where('user_id', $this->helper->id)->where('state', 'queued')->count())->toBe(1);

    foreach (range(1, 3) as $_) {
        $page->call('retry');
    }
    $page->assertSeeText('patienter');
});

it('garde les opportunités dans leur fonctionnement actuel : lues à la demande, sans alerte', function () {
    expect(app(Matching::class)->suggestions($this->helper))->toBeEmpty()->and(UserNotification::where('kind', NotificationKind::MatchFound)->count())->toBe(0);
});

it('ne relance pas de recherche quand un besoin passe seulement « en cours » après un geste accepté', function () {
    $post = matchingPublication($this->author);
    $searches = MatchingSearch::query()->where('publication_id', $post->id)->count();

    $post->update(['need_state' => NeedState::InProgress]);

    expect(MatchingSearch::query()->where('publication_id', $post->id)->count())->toBe($searches)->and(DB::table('jobs')->count())->toBe(0)
        ->and(pistesVisibles($this->helper))->toBe(1);
});

it('relance la recherche d’un besoin rouvert, sans réalerter', function () {
    $post = matchingPublication($this->author);
    app(Publications::class)->close($this->author, $post);
    expect(pistesVisibles($this->helper))->toBe(0);

    $post->fresh()->update(['need_state' => NeedState::Open]);
    expect(DB::table('jobs')->count())->toBe(1);
    drainMatching();

    expect(pistesVisibles($this->helper))->toBe(1)->and(UserNotification::where('user_id', $this->helper->id)->where('kind', NotificationKind::MatchFound)->count())->toBe(1);
});

// --- Recherches bornées : candidats examinés, temps, curseur -------------------------------------------------------------------

function examinesBeaucoupTrouvePeu(object $test, int $posts = 130): void
{
    $cocody = City::where('name', 'Cocody')->firstOrFail();
    $ailleurs = City::where('name', 'Bouaké')->firstOrFail();
    matchingPreferences($test->helper, ['zones' => [$cocody->id], 'remote' => false]);
    foreach (range(1, $posts) as $i) {
        // Passe le tri SQL (compétence et conditions communes) mais pas les raisons : autre localité, pas à distance.
        publierSansTraiter($test->author, changes: ['remote' => false], data: ['body' => "Besoin lointain $i", 'city_id' => $ailleurs->id]);
    }
    DB::table('jobs')->delete();
    MatchingSearch::query()->delete();
}

it('borne la recherche d’une personne : beaucoup de candidats examinés, très peu de correspondances, par tranches reprises au curseur', function () {
    examinesBeaucoupTrouvePeu($this);
    $searches = app(MatchingSearches::class);
    $runsBefore = ExecutionRun::query()->where('job', 'RunMatchingSearch')->count();

    $search = $searches->request(MatchingSearches::FOR_PERSON, $this->helper->id);
    $slices = 0;
    while ($search->fresh()->isOpen() && $slices < 20) {
        expect(app(BackgroundRunner::class)->run(1, 30))->toBe(1);
        $slices++;
    }

    $examined = ExecutionRun::query()->where('job', 'RunMatchingSearch')->orderBy('ran_at')->get()->slice($runsBefore)->map(fn ($run) => $run->metrics['examined'] ?? 0);
    expect($search->fresh()->state)->toBe(MatchingSearch::DONE)->and($search->fresh()->produced)->toBe(0)
        ->and($examined->sum())->toBe(130)->and($examined->max())->toBeLessThanOrEqual(50)
        ->and($slices)->toBeGreaterThanOrEqual(3)
        ->and(MatchSuggestion::query()->where('user_id', $this->helper->id)->count())->toBe(0);
});

it('rend la main à temps : une tranche sans budget de temps examine un candidat puis reprend au curseur, sans jamais bloquer', function () {
    examinesBeaucoupTrouvePeu($this, 12);
    $engine = app(Matching::class);

    $cursor = null;
    $examined = 0;
    $calls = 0;
    do {
        $slice = $engine->searchPerson($this->helper->fresh(), $cursor, 50, 0.0);
        $examined += $slice['examined'];
        $cursor = $slice['cursor'];
        $calls++;
    } while ($cursor !== null && $calls < 60);

    // Chaque tranche progresse d'un candidat au moins ; 12 publications + les temps « mes publications » et « opportunités ».
    expect($cursor)->toBeNull()->and($examined)->toBe(12)->and($calls)->toBeGreaterThanOrEqual(12)->and($calls)->toBeLessThanOrEqual(15);

    $post = publierSansTraiter($this->author);
    $cursor = null;
    $calls = 0;
    do {
        $slice = $engine->searchPublication($post->fresh(), $cursor, 50, 0.0);
        $cursor = $slice['cursor'];
        $calls++;
        expect($slice['examined'])->toBeLessThanOrEqual(1);
    } while ($cursor !== null && $calls < 60);

    expect($cursor)->toBeNull();
});

it('lit le temps restant du passage : celui du passage en cours, jamais plus que la tranche', function () {
    expect(BackgroundRunner::remaining(3.0))->toBe(3.0);
});

it('délègue la recherche de personnes pour ses propres publications à des recherches découpées à leur tour', function () {
    $own = publierSansTraiter($this->helper);
    DB::table('jobs')->delete();
    MatchingSearch::query()->delete();

    app(MatchingSearches::class)->request(MatchingSearches::FOR_PERSON, $this->helper->id);
    drainMatching();

    expect(MatchingSearch::query()->where('publication_id', $own->id)->where('state', 'done')->count())->toBe(1)
        ->and(MatchingSearch::query()->where('user_id', $this->helper->id)->where('state', 'done')->count())->toBe(1);
});

// --- États vivants : rafraîchissement léger, seulement tant que la recherche n'est pas terminée ------------------------------

it('suit une recherche de « en attente » à « terminée » sans recharger la page, avec un rafraîchissement seulement pendant l’attente', function () {
    $post = publierSansTraiter($this->author);
    $searches = app(MatchingSearches::class);
    $status = Livewire::actingAs($this->author)->test(SearchStatus::class, ['kind' => 'publication', 'subjectId' => $post->id]);

    $status->assertSeeText('Recherche en attente')->assertSeeHtml('wire:poll.5s.visible')->assertNotDispatched('matching-search-updated');

    $searches->begin(rechercheDe($post)->id);
    $status->call('$refresh')->assertSeeText('Recherche en cours')->assertSeeHtml('wire:poll.5s.visible')->assertDispatched('matching-search-updated');

    drainMatching();
    $status->call('$refresh')->assertSeeText('Recherche terminée')->assertSeeText('1 piste à explorer')->assertDontSeeHtml('wire:poll');
});

it('suit les transitions vers « à reprendre » (rafraîchissement lent) puis « indisponible » (plus aucun rafraîchissement)', function () {
    $post = publierSansTraiter($this->author);
    $search = rechercheDe($post);
    $searches = app(MatchingSearches::class);
    $status = Livewire::actingAs($this->author)->test(SearchStatus::class, ['kind' => 'publication', 'subjectId' => $post->id]);

    $searches->begin($search->id);
    $searches->retrying($search->id, 'RuntimeException');
    $status->call('$refresh')->assertSeeText('Recherche à reprendre')->assertSeeHtml('wire:poll.30s.visible')->assertDontSeeHtml('wire:poll.5s');

    $searches->fail($search->id, 'RuntimeException');
    $status->call('$refresh')->assertSeeText('Recherche momentanément indisponible')->assertDontSeeHtml('wire:poll')->assertSeeText('Relancer la recherche');
});

it('ne rafraîchit rien quand il n’y a aucune recherche ou qu’elle a été annulée', function () {
    $post = publierSansTraiter($this->author);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $post, 'Motif de recette');

    Livewire::actingAs($this->author)->test(SearchStatus::class, ['kind' => 'publication', 'subjectId' => $post->id])->assertDontSeeHtml('wire:poll')->assertDontSeeHtml('data-testid="search-status"');
    Livewire::actingAs($this->helper)->test(SearchStatus::class, ['kind' => 'person'])->assertDontSeeHtml('wire:poll');
});

it('recharge la liste de « Mes pistes » quand la recherche change d’état', function () {
    $page = Livewire::actingAs($this->helper)->test(MatchSuggestions::class)->assertDontSeeText('Pourquoi cette suggestion');
    matchingPublication($this->author);

    $page->dispatch('matching-search-updated')->assertSeeText('Pourquoi cette suggestion');
});

it('montre l’état dans la page de la publication, vivant, pour l’auteur seulement', function () {
    $post = publierSansTraiter($this->author);

    Livewire::actingAs($this->author)->test(PagePublication::class, ['publication' => $post])->assertSeeText('Recherche en attente')->assertSeeHtml('wire:poll.5s.visible');
    Livewire::actingAs($this->helper)->test(PagePublication::class, ['publication' => $post])->assertDontSeeText('Recherche en attente');
});

it('protège le composant : sujet verrouillé, publication d’autrui introuvable', function () {
    $post = publierSansTraiter($this->author);
    $component = Livewire::actingAs($this->author)->test(SearchStatus::class, ['kind' => 'publication', 'subjectId' => $post->id]);

    expect(fn () => $component->set('subjectId', (string) Str::uuid()))->toThrow(Exception::class, 'Cannot update locked property');
    expect(fn () => $component->set('kind', 'person'))->toThrow(Exception::class, 'Cannot update locked property');

    Livewire::actingAs($this->helper)->test(SearchStatus::class, ['kind' => 'publication', 'subjectId' => $post->id])->assertNotFound();
});
