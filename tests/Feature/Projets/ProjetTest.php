<?php

use App\Enums\ProjectHostingState;
use App\Enums\ProjectStage;
use App\Enums\ProjectState;
use App\Enums\ZumraRole;
use App\Livewire\CarrefourProjets;
use App\Livewire\FormulaireProjet;
use App\Livewire\MondeProjet;
use App\Models\City;
use App\Models\Domain;
use App\Models\Project;
use App\Models\ProjectStateChange;
use App\Models\User;
use App\Services\Projects;
use App\Services\ProjectShowcase;
use App\Services\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/** Projets (P1, D-206) : porté par une personne, privé par défaut, accueilli par une ZUMRA. */
beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    $this->membre = person('Simple Membre', []);
    $this->dehors = person('Personne Extérieure', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->membre);
});

function monde_projet(User $qui, Project $projet)
{
    return Livewire::actingAs($qui)->test(MondeProjet::class, ['project' => $projet]);
}

function presente(Project $projet, User $porteur, array $data = []): void
{
    app(ProjectShowcase::class)->publish($porteur, $projet, $data + ['summary' => 'Coudre 200 uniformes pour trois écoles.']);
}

// ─── Créer ───────────────────────────────────────────────────────────────────

it('laisse toute personne vérifiée créer un projet privé, dont elle est le porteur, accueilli tout de suite par sa propre ZUMRA', function () {
    foreach ([$this->yao, $this->resp, $this->membre] as $i => $qui) {
        $p = projet($qui, $this->zumra, "Projet numéro $i");

        expect($p->zumra_id)->toBe($this->zumra->id)->and($p->owner_id)->toBe($qui->id)->and($p->state)->toBe(ProjectState::Idea)
            ->and($p->stage)->toBe(ProjectStage::Idea)->and($p->hosting_state)->toBe(ProjectHostingState::Accepted)->and($p->origin_zumra_id)->toBe($this->zumra->id)
            ->and($p->showcase_published_at)->toBeNull()->and($p->privacyLabel())->toBe('Projet privé');

        $trace = ProjectStateChange::query()->where('project_id', $p->id)->sole();
        expect($trace->from_state)->toBeNull()->and($trace->to_state)->toBe(ProjectState::Idea)->and($trace->user_id)->toBe($qui->id);
    }
});

it('crée un brouillon privé pour une ZUMRA dont on n’est pas membre : l’accueil est demandé, pas confirmé', function () {
    $p = app(Projects::class)->create($this->dehors, $this->zumra, ['name' => 'Projet extérieur', 'goal' => 'Un but.', 'hosting_message' => 'Pouvez-vous nous accueillir ?']);

    expect($p->owner_id)->toBe($this->dehors->id)->and($p->hosting_state)->toBe(ProjectHostingState::Pending)->and($p->isHosted())->toBeFalse()
        ->and($p->hosting_message)->toBe('Pouvez-vous nous accueillir ?')->and($p->origin_zumra_id)->toBeNull()->and($this->zumra->roleOf($this->dehors))->toBeNull();
});

it('refuse la création à un visiteur et à une personne dont l’adresse n’est pas vérifiée', function () {
    $non = User::factory()->unverified()->create();

    expect(fn () => projet($non, $this->zumra))->toThrow(AuthorizationException::class);
    expect(Gate::forUser(null)->allows('create', [Project::class, $this->zumra]))->toBeFalse();
    expect(Project::count())->toBe(0);
});

it('vérifie le nom (3 à 80 caractères) et le but (obligatoire, 500 caractères au plus)', function () {
    $mauvais = [
        ['name' => 'ab'], ['name' => str_repeat('a', 81)], ['name' => '   '],
        ['goal' => ''], ['goal' => " \n "], ['goal' => str_repeat('é', 501)],
    ];
    foreach ($mauvais as $data) {
        expect(fn () => projet($this->yao, $this->zumra, 'Nom correct', $data))->toThrow(ValidationException::class);
    }

    expect(projet($this->yao, $this->zumra, str_repeat('a', 80), ['goal' => str_repeat('é', 500)])->goal)->toHaveLength(500);
    expect(projet($this->yao, $this->zumra, 'abc')->name)->toBe('abc');
    expect(Project::count())->toBe(2);
});

it('vérifie le domaine, le mot à la ZUMRA et reprend le domaine de la ZUMRA à défaut', function () {
    expect(fn () => projet($this->yao, $this->zumra, 'Projet A', ['domain_id' => (string) Str::uuid()]))->toThrow(ValidationException::class);
    expect(fn () => projet($this->yao, $this->zumra, 'Projet B', ['hosting_message' => str_repeat('a', 281)]))->toThrow(ValidationException::class);

    expect(projet($this->yao, $this->zumra, 'Projet C')->domain_id)->toBe($this->zumra->domain_id);
    $tech = Domain::query()->where('name', 'like', 'Technologie%')->value('id');
    expect(projet($this->yao, $this->zumra, 'Projet D', ['domain_id' => $tech])->domain_id)->toBe($tech);
});

it('remonte ensemble le nom et le but invalides, chacun sous son champ', function () {
    try {
        app(Projects::class)->create($this->yao, $this->zumra, ['name' => '', 'goal' => '']);
        $this->fail('Une erreur était attendue.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['name', 'goal']);
    }
});

it('refuse deux projets du même nom dans une ZUMRA, sans tenir compte de la casse, des accents ni des espaces, mais l’admet dans une autre', function () {
    projet($this->yao, $this->zumra, 'École Solidaire');

    foreach (['école solidaire', 'ECOLE  SOLIDAIRE', 'Ecole Solidaire'] as $doublon) {
        expect(fn () => projet($this->yao, $this->zumra, $doublon))->toThrow(ValidationException::class, 'déjà un projet de ce nom');
    }

    $autre = zumra($this->dehors, 'Autre ZUMRA');
    expect(projet($this->dehors, $autre, 'École Solidaire')->zumra_id)->toBe($autre->id);
    expect(Project::count())->toBe(2);
});

it('garantit en base l’unicité du nom dans la ZUMRA, la liste des états, des étapes de vie et des accueils', function () {
    $p = projet($this->yao, $this->zumra);

    $ligne = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'zumra_id' => $this->zumra->id, 'name' => 'x', 'name_key' => 'x', 'search' => 'x', 'goal' => 'x', 'state' => 'idea', 'state_changed_at' => now(), 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('projects')->insert($ligne(['name_key' => $p->name_key]))))->toThrow(QueryException::class);
    foreach ([['state' => 'inconnu'], ['stage' => 'inconnue'], ['hosting_state' => 'inconnu'], ['showcase_published_at' => now()]] as $mauvais) {
        expect(fn () => DB::transaction(fn () => DB::table('projects')->insert($ligne($mauvais))))->toThrow(QueryException::class);
    }
    expect(fn () => DB::transaction(fn () => DB::table('project_state_changes')->insert(['id' => (string) Str::uuid(), 'project_id' => $p->id, 'to_state' => 'inconnu'])))->toThrow(QueryException::class);
});

it('limite le nombre de créations de projets par personne et par jour', function () {
    config(['gamad.limits.projects_per_day' => 2]);

    projet($this->yao, $this->zumra, 'Projet A');
    projet($this->yao, $this->zumra, 'Projet B');
    expect(fn () => projet($this->yao, $this->zumra, 'Projet C'))->toThrow(ValidationException::class, 'limite');

    // La limite est par personne.
    expect(projet($this->resp, $this->zumra, 'Projet D')->name)->toBe('Projet D');
    expect(Project::count())->toBe(3);

    $this->travel(25)->hours();
    expect(projet($this->yao, $this->zumra, 'Projet C')->name)->toBe('Projet C');
});

it('ne laisse ni changer la ZUMRA, ni le porteur, ni l’état, ni l’accueil par la création', function () {
    $autre = zumra($this->dehors, 'Autre ZUMRA');
    $p = app(Projects::class)->create($this->yao, $this->zumra, ['name' => 'Projet X', 'goal' => 'But', 'zumra_id' => $autre->id, 'state' => 'done', 'owner_id' => $this->dehors->id, 'hosting_state' => 'pending', 'showcase_published_at' => now()]);

    expect($p->zumra_id)->toBe($this->zumra->id)->and($p->state)->toBe(ProjectState::Idea)->and($p->owner_id)->toBe($this->yao->id)
        ->and($p->hosting_state)->toBe(ProjectHostingState::Accepted)->and($p->showcase_published_at)->toBeNull();
});

// ─── Modifier ────────────────────────────────────────────────────────────────

it('laisse le porteur modifier le nom et le but, pas un responsable de la ZUMRA ni une autre personne', function () {
    $p = projet($this->membre, $this->zumra);

    app(Projects::class)->update($this->membre, $p, ['name' => 'Nouveau nom', 'goal' => 'Nouveau but']);
    expect($p->fresh()->name)->toBe('Nouveau nom')->and($p->fresh()->goal)->toBe('Nouveau but');

    foreach ([$this->yao, $this->resp, $this->dehors] as $qui) {
        expect(fn () => app(Projects::class)->update($qui, $p->fresh(), ['name' => 'Intrus', 'goal' => 'x']))->toThrow(AuthorizationException::class);
    }
    expect($p->fresh()->name)->toBe('Nouveau nom');
});

it('refuse le nom d’un autre projet de la ZUMRA, mais accepte de garder le sien', function () {
    $a = projet($this->yao, $this->zumra, 'Projet A');
    projet($this->yao, $this->zumra, 'Projet B');

    expect(fn () => app(Projects::class)->update($this->yao, $a, ['name' => 'projet b', 'goal' => 'But']))->toThrow(ValidationException::class);
    app(Projects::class)->update($this->yao, $a, ['name' => 'PROJET A', 'goal' => 'Autre but']);
    expect($a->fresh()->name)->toBe('PROJET A');
});

it('garde la clé de recherche à jour quand on modifie le projet', function () {
    $p = projet($this->yao, $this->zumra, 'Projet A');
    app(Projects::class)->update($this->yao, $p, ['name' => 'Jardin partagé', 'goal' => 'Cultiver des légumes']);

    expect($p->fresh()->search)->toContain('jardin partage')->toContain('legumes')->not->toContain('projet a');
});

it('change l’étape de vie du projet, dans les deux sens, pour le porteur seulement', function () {
    $p = projet($this->yao, $this->zumra);

    foreach ([ProjectStage::Preparation, ProjectStage::Launch, ProjectStage::Activity, ProjectStage::Idea] as $stage) {
        app(Projects::class)->setStage($this->yao, $p, $stage);
        expect($p->fresh()->stage)->toBe($stage);
    }
    foreach ([$this->resp, $this->membre] as $qui) {
        expect(fn () => app(Projects::class)->setStage($qui, $p, ProjectStage::Activity))->toThrow(AuthorizationException::class);
    }
});

// ─── État ────────────────────────────────────────────────────────────────────

it('permet exactement les changements d’état prévus', function () {
    $permis = [
        'idea' => ['in_progress', 'abandoned'],
        'in_progress' => ['done', 'abandoned'],
        'done' => ['in_progress'],
        'abandoned' => ['in_progress'],
    ];

    foreach (ProjectState::cases() as $depuis) {
        expect(collect($depuis->next())->map->value->all())->toBe($permis[$depuis->value]);

        foreach (ProjectState::cases() as $vers) {
            $p = projet($this->yao, $this->zumra, "De {$depuis->value} vers {$vers->value}");
            $p->forceFill(['state' => $depuis])->save();

            if (in_array($vers->value, $permis[$depuis->value], true)) {
                app(Projects::class)->changeState($this->yao, $p, $vers);
                expect($p->fresh()->state)->toBe($vers);
            } else {
                expect(fn () => app(Projects::class)->changeState($this->yao, $p, $vers))->toThrow(ValidationException::class);
                expect($p->fresh()->state)->toBe($depuis);
            }
        }
    }
});

it('garde la trace de chaque changement d’état, avec la personne qui l’a fait', function () {
    $p = projet($this->yao, $this->zumra);
    app(Projects::class)->changeState($this->yao, $p, ProjectState::InProgress);
    app(Projects::class)->changeState($this->yao, $p, ProjectState::Done);

    $traces = ProjectStateChange::query()->where('project_id', $p->id)->orderBy('created_at')->get();
    expect($traces)->toHaveCount(3)
        ->and($traces[1]->from_state)->toBe(ProjectState::Idea)->and($traces[1]->to_state)->toBe(ProjectState::InProgress)->and($traces[1]->user_id)->toBe($this->yao->id)
        ->and($traces[2]->to_state)->toBe(ProjectState::Done);
    expect($p->fresh()->state_changed_at)->not->toBeNull();
});

it('refuse le changement d’état à un responsable de la ZUMRA, à une autre personne, et ne laisse aucune trace', function () {
    $p = projet($this->yao, $this->zumra);

    foreach ([$this->resp, $this->membre, $this->dehors] as $qui) {
        expect(fn () => app(Projects::class)->changeState($qui, $p, ProjectState::InProgress))->toThrow(AuthorizationException::class);
    }
    expect($p->fresh()->state)->toBe(ProjectState::Idea)->and(ProjectStateChange::count())->toBe(1);
});

it('garde la trace d’un changement d’état sans le nom d’une personne dont le compte a disparu', function () {
    $p = projet($this->membre, $this->zumra);
    $this->membre->delete();

    $trace = ProjectStateChange::query()->where('project_id', $p->id)->sole();
    expect($trace->user_id)->toBeNull();
    expect($p->fresh()->owner_id)->toBeNull();
});

// ─── Confidentialité ─────────────────────────────────────────────────────────

it('garde le dossier privé : ni un visiteur, ni un membre de la ZUMRA, ni un responsable, ni un administrateur n’y entrent', function () {
    $p = projet($this->yao, $this->zumra, 'Uniformes de la rentrée');

    $this->actingAs($this->yao)->get(route('projets.show', $p))->assertOk()->assertSeeText('Uniformes de la rentrée')->assertSeeText('Projet privé');
    foreach ([$this->resp, $this->membre, $this->dehors, User::factory()->superAdmin()->create()] as $qui) {
        $this->actingAs($qui)->get(route('projets.show', $p))->assertNotFound();
        $this->actingAs($qui)->get(route('projets.edit', $p))->assertNotFound();
        expect(Gate::forUser($qui)->allows('view', $p))->toBeFalse();
    }
    app('auth')->forgetGuards();
    $this->get(route('projets.show', $p))->assertNotFound();
});

it('refuse toute action Livewire sur le dossier à qui n’en est pas le porteur ou un collaborateur', function () {
    $p = projet($this->yao, $this->zumra);

    foreach ([$this->resp, $this->membre, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.show', $p))->assertNotFound();
    }
    $ouvert = monde_projet($this->yao, $p);
    $p->forceFill(['owner_id' => $this->dehors->id])->save();
    $ouvert->call('setStage', 'launch')->assertNotFound();
    expect($p->fresh()->stage)->toBe(ProjectStage::Idea);
});

it('ferme tout, même au porteur, quand le module ZUMRA est fermé', function () {
    $p = projet($this->yao, $this->zumra);
    app(Settings::class)->update(['zumra_module' => 'off'], User::factory()->superAdmin()->create());
    config(['gamad.initial_experience' => true]);

    $this->actingAs($this->yao)->get(route('projets.show', $p))->assertNotFound();
    $this->actingAs($this->yao)->get(route('espace.projets'))->assertNotFound();
    expect(fn () => projet($this->yao, $this->zumra, 'Autre'))->toThrow(AuthorizationException::class);
});

it('répond « page introuvable » pour un projet qui n’existe pas', function () {
    $this->get('/projets/'.Str::uuid())->assertNotFound();
    $this->get('/projets/pas-un-identifiant')->assertNotFound();
});

it('garde le texte brut : rien n’est interprété et les adresses ne sont pas cliquables', function () {
    $p = projet($this->yao, $this->zumra, 'Projet <i>net</i>', ['goal' => "<b>gras</b> https://exemple.org\nligne 2"]);

    $html = monde_projet($this->yao, $p)->html();

    expect($html)->toContain('&lt;b&gt;gras&lt;/b&gt;')->toContain('Projet &lt;i&gt;net&lt;/i&gt;')->not->toContain('<a href="https://exemple.org');
});

// ─── Formulaire ──────────────────────────────────────────────────────────────

it('ouvre le formulaire de création en quatre questions à toute personne vérifiée', function () {
    foreach ([$this->yao, $this->membre, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.new'))->assertOk()->assertSeeText('Quel est le nom de votre projet ?')->assertSeeText('Que souhaitez-vous réaliser ?')
            ->assertSeeText('Dans quel domaine ?')->assertSeeText('Quelle ZUMRA accompagnera votre projet ?')->assertSeeText('Votre projet commence privé');
    }
    $this->actingAs($this->yao)->get(route('projets.create', $this->zumra))->assertOk()->assertSeeText('Créer le projet');
});

it('renvoie un visiteur sans compte vers la connexion pour créer ou modifier', function () {
    $p = projet($this->yao, $this->zumra);

    $this->get(route('projets.new'))->assertRedirect(route('login'));
    $this->get(route('projets.create', $this->zumra))->assertRedirect(route('login'));
    $this->get(route('projets.edit', $p))->assertRedirect(route('login'));
});

it('crée un projet depuis le formulaire, et affiche les erreurs sous chaque champ', function () {
    $domaine = Domain::query()->value('id');

    Livewire::actingAs($this->membre)->test(FormulaireProjet::class)
        ->set('name', '')->set('goal', '')->set('zumra_id', '')->call('save')->assertHasErrors(['name', 'goal', 'zumra_id', 'domain_id'])
        ->set('name', 'Jardin partagé')->set('goal', 'Cultiver ensemble.')->set('zumra_id', $this->zumra->id)->call('save')->assertHasErrors('domain_id')
        ->set('domain_id', $domaine)->call('save')->assertHasNoErrors();

    $p = Project::sole();
    expect($p->name)->toBe('Jardin partagé')->and($p->owner_id)->toBe($this->membre->id)->and($p->isHosted())->toBeTrue();
    $this->actingAs($this->membre)->get(route('projets.show', $p))->assertSeeText('Projet créé');
});

it('demande l’accueil d’une ZUMRA dont on n’est pas membre depuis le formulaire, avec un mot', function () {
    Livewire::actingAs($this->dehors)->test(FormulaireProjet::class)
        ->set('name', 'Académie numérique')->set('goal', 'Former des jeunes.')->set('domain_id', Domain::query()->value('id'))->set('zumra_id', $this->zumra->id)
        ->assertSeeText('Vous n’êtes pas membre de cette ZUMRA')->set('hosting_message', 'Pouvez-vous nous accueillir ?')->call('save')->assertHasNoErrors();

    $p = Project::sole();
    expect($p->hosting_state)->toBe(ProjectHostingState::Pending)->and($p->hosting_message)->toBe('Pouvez-vous nous accueillir ?')->and($p->zumra_id)->toBe($this->zumra->id);
});

it('modifie un projet depuis le formulaire, pour le porteur seulement', function () {
    $p = projet($this->membre, $this->zumra);

    Livewire::actingAs($this->membre)->test(FormulaireProjet::class, ['project' => $p])
        ->assertSet('name', 'Uniformes de la rentrée')->set('name', 'Autre nom')->call('save')->assertHasNoErrors();
    expect($p->fresh()->name)->toBe('Autre nom');

    foreach ([$this->yao, $this->resp, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.edit', $p))->assertNotFound();
    }
});

it('verrouille le projet du formulaire, et revérifie le droit à l’enregistrement', function () {
    $p = projet($this->yao, $this->zumra);

    $e = Livewire::actingAs($this->yao)->test(FormulaireProjet::class, ['project' => $p]);
    expect(fn () => $e->set('projectId', (string) Str::uuid()))->toThrow(CannotUpdateLockedPropertyException::class);

    // Le porteur change : l'ancien ne modifie plus rien, même avec l'écran déjà ouvert.
    $p->forceFill(['owner_id' => $this->dehors->id])->save();
    expect(fn () => $e->set('name', 'Tardif')->call('save'))->toThrow(Exception::class);
    expect($p->fresh()->name)->toBe('Uniformes de la rentrée');
});

// ─── Onglet Projets de la ZUMRA ──────────────────────────────────────────────

it('liste dans la ZUMRA les présentations publiées et mes propres projets, jamais les dossiers des autres', function () {
    $publie = projet($this->yao, $this->zumra, 'Atelier');
    presente($publie, $this->yao);
    projet($this->yao, $this->zumra, 'Zèbre privé');
    projet($this->membre, $this->zumra, 'Mon projet privé');
    projet($this->dehors, zumra($this->dehors, 'Autre ZUMRA'), 'Projet ailleurs');

    monde($this->resp, $this->zumra, 'projets')->assertSeeText('Atelier')->assertSeeText('Coudre 200 uniformes')->assertDontSeeText('Zèbre privé')->assertDontSeeText('Mon projet privé')->assertDontSeeText('Projet ailleurs');
    monde($this->membre, $this->zumra, 'projets')->assertSeeText('Atelier')->assertSeeText('Mon projet privé')->assertDontSeeText('Zèbre privé');
    monde($this->yao, $this->zumra, 'projets')->assertSeeText('Atelier')->assertSeeText('Zèbre privé')->assertDontSeeText('Mon projet privé');
});

it('compte dans l’en-tête de la ZUMRA les projets réellement visibles', function () {
    monde($this->resp, $this->zumra, 'formation')->assertSeeText('0')->assertSeeText('projet visible');

    $p = projet($this->yao, $this->zumra, 'Projet A');
    projet($this->yao, $this->zumra, 'Projet privé');
    presente($p, $this->yao);
    monde($this->resp, $this->zumra, 'formation')->assertSeeText('1')->assertSeeText('projet visible')->assertDontSeeText('projets visibles');
});

it('supprime les projets et leur histoire avec la ZUMRA', function () {
    $p = projet($this->yao, $this->zumra);

    $this->zumra->delete();

    expect(Project::count())->toBe(0)->and(ProjectStateChange::where('project_id', $p->id)->count())->toBe(0);
});

// ─── Carrefour Projets (conservé, mais jamais d'un projet privé) ─────────────

function carrefour_projets(?User $qui = null)
{
    return $qui ? Livewire::actingAs($qui)->test(CarrefourProjets::class) : Livewire::test(CarrefourProjets::class);
}

it('ne liste que les présentations publiées, jamais un projet privé', function () {
    projet($this->yao, $this->zumra, 'Projet privé');
    $this->get(route('projets.index'))->assertOk()->assertSeeText('Aucun projet pour l’instant.')->assertDontSeeText('Projet privé');

    $p = projet($this->yao, $this->zumra, 'Projet présenté');
    presente($p, $this->yao);
    carrefour_projets()->assertSeeText('Projet présenté')->assertDontSeeText('Projet privé');
});

it('cherche par nom ou par but, sans tenir compte de la casse ni des accents', function () {
    $a = projet($this->yao, $this->zumra, 'Jardin partagé', ['goal' => 'Cultiver des légumes']);
    $b = projet($this->yao, $this->zumra, 'Atelier couture', ['goal' => 'Apprendre à coudre']);
    presente($a, $this->yao);
    presente($b, $this->yao);

    carrefour_projets()->set('query', 'JARDIN')->assertSeeText('Jardin partagé')->assertDontSeeText('Atelier couture')
        ->set('query', 'legumes')->assertSeeText('Jardin partagé')->assertDontSeeText('Atelier couture')
        ->set('query', 'introuvable')->assertSeeText('Aucun projet ne correspond à cette recherche.')->call('clearFilters')->assertSeeText('Atelier couture')->assertSet('query', '');
});

it('filtre par état, par domaine et par ville de la ZUMRA, et ignore une valeur invalide', function () {
    $tech = Domain::query()->where('name', 'like', 'Technologie%')->value('id');
    $ville = City::query()->where('name', 'Cocody')->value('id');
    $z2 = zumra($this->dehors, 'Tech Cocody', ['domain_id' => $tech, 'city_id' => $ville]);
    $p1 = projet($this->yao, $this->zumra, 'Projet couture');
    app(Projects::class)->changeState($this->yao, $p1, ProjectState::InProgress);
    $p2 = projet($this->dehors, $z2, 'Projet logiciel');
    presente($p1, $this->yao);
    presente($p2, $this->dehors);

    carrefour_projets()->set('state', 'in_progress')->assertSeeText('Projet couture')->assertDontSeeText('Projet logiciel')
        ->set('state', '')->set('domain', $tech)->assertSeeText('Projet logiciel')->assertDontSeeText('Projet couture')
        ->set('domain', '')->set('city', $ville)->assertSeeText('Projet logiciel')->assertDontSeeText('Projet couture')
        ->set('city', '')->set('state', 'inconnu')->assertSeeText('Projet couture')->assertSeeText('Projet logiciel')
        ->set('state', '')->set('domain', 'pas-un-identifiant')->set('city', "' or 1=1 --")->assertSeeText('Projet couture')->assertSeeText('Projet logiciel');
});

it('trie par ordre alphabétique et pagine par 10', function () {
    foreach (range(1, 12) as $i) {
        presente(projet($this->yao, $this->zumra, sprintf('Projet %02d', $i)), $this->yao);
    }

    $c = carrefour_projets();
    $html = $c->assertSeeText('12 projets')->assertSeeText('Page 1 sur 2')->assertSeeText('Projet 01')->assertDontSeeText('Projet 11')->html();
    expect(strpos($html, 'Projet 01'))->toBeLessThan(strpos($html, 'Projet 02'));
    $c->call('nextPage')->assertSeeText('Projet 11')->assertSeeText('Projet 12')->assertDontSeeText('Projet 01');
});
