<?php

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
use App\Services\Memberships;
use App\Services\Projects;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    $this->membre = person('Simple Membre', []);
    $this->dehors = person('Personne Extérieure', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->membre);
});

function monde_projet(?User $qui, Project $projet)
{
    return $qui ? Livewire::actingAs($qui)->test(MondeProjet::class, ['project' => $projet]) : Livewire::test(MondeProjet::class, ['project' => $projet]);
}

// ─── Créer ───────────────────────────────────────────────────────────────────

it('laisse le fondateur et les responsables créer un projet, qui commence comme une idée et garde la trace de sa création', function () {
    foreach ([$this->yao, $this->resp] as $i => $qui) {
        $p = projet($qui, $this->zumra, "Projet numéro $i");

        expect($p->zumra_id)->toBe($this->zumra->id)->and($p->state)->toBe(ProjectState::Idea);

        $trace = ProjectStateChange::query()->where('project_id', $p->id)->sole();
        expect($trace->from_state)->toBeNull()->and($trace->to_state)->toBe(ProjectState::Idea)->and($trace->user_id)->toBe($qui->id);
    }
});

it('refuse la création à un membre, à une personne extérieure et à un visiteur', function () {
    foreach ([$this->membre, $this->dehors] as $qui) {
        expect(fn () => projet($qui, $this->zumra))->toThrow(AuthorizationException::class);
    }
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

it('garantit en base l’unicité du nom dans la ZUMRA et la liste des états', function () {
    $p = projet($this->yao, $this->zumra);

    $ligne = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'zumra_id' => $this->zumra->id, 'name' => 'x', 'name_key' => 'x', 'search' => 'x', 'goal' => 'x', 'state' => 'idea', 'state_changed_at' => now(), 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('projects')->insert($ligne(['name_key' => $p->name_key]))))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('projects')->insert($ligne(['state' => 'inconnu']))))->toThrow(QueryException::class);
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

it('ne laisse ni changer la ZUMRA ni l’état par la création', function () {
    $autre = zumra($this->dehors, 'Autre ZUMRA');
    $p = app(Projects::class)->create($this->yao, $this->zumra, ['name' => 'Projet X', 'goal' => 'But', 'zumra_id' => $autre->id, 'state' => 'done']);

    expect($p->zumra_id)->toBe($this->zumra->id)->and($p->state)->toBe(ProjectState::Idea);
});

// ─── Modifier ────────────────────────────────────────────────────────────────

it('laisse les gestionnaires modifier le nom et le but, pas un membre ni une personne extérieure', function () {
    $p = projet($this->yao, $this->zumra);

    app(Projects::class)->update($this->resp, $p, ['name' => 'Nouveau nom', 'goal' => 'Nouveau but']);
    expect($p->fresh()->name)->toBe('Nouveau nom')->and($p->fresh()->goal)->toBe('Nouveau but');

    foreach ([$this->membre, $this->dehors] as $qui) {
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
                app(Projects::class)->changeState($this->resp, $p, $vers);
                expect($p->fresh()->state)->toBe($vers);
            } else {
                expect(fn () => app(Projects::class)->changeState($this->resp, $p, $vers))->toThrow(ValidationException::class);
                expect($p->fresh()->state)->toBe($depuis);
            }
        }
    }
});

it('garde la trace de chaque changement d’état, avec la personne qui l’a fait', function () {
    $p = projet($this->yao, $this->zumra);
    app(Projects::class)->changeState($this->resp, $p, ProjectState::InProgress);
    app(Projects::class)->changeState($this->yao, $p, ProjectState::Done);

    $traces = ProjectStateChange::query()->where('project_id', $p->id)->orderBy('created_at')->get();
    expect($traces)->toHaveCount(3)
        ->and($traces[1]->from_state)->toBe(ProjectState::Idea)->and($traces[1]->to_state)->toBe(ProjectState::InProgress)->and($traces[1]->user_id)->toBe($this->resp->id)
        ->and($traces[2]->to_state)->toBe(ProjectState::Done)->and($traces[2]->user_id)->toBe($this->yao->id);
    expect($p->fresh()->state_changed_at)->not->toBeNull();
});

it('refuse le changement d’état à un membre, à une personne extérieure, et ne laisse aucune trace', function () {
    $p = projet($this->yao, $this->zumra);

    foreach ([$this->membre, $this->dehors] as $qui) {
        expect(fn () => app(Projects::class)->changeState($qui, $p, ProjectState::InProgress))->toThrow(AuthorizationException::class);
    }
    expect($p->fresh()->state)->toBe(ProjectState::Idea)->and(ProjectStateChange::count())->toBe(1);
});

it('garde la trace d’un changement d’état sans le nom d’une personne dont le compte a disparu', function () {
    $p = projet($this->resp, $this->zumra);
    $this->resp->delete();

    $trace = ProjectStateChange::query()->where('project_id', $p->id)->sole();
    expect($trace->user_id)->toBeNull();

    monde_projet(null, $p->fresh())->assertSeeText('Projet créé comme une idée')->assertDontSeeText('par ');
});

// ─── Monde du projet (public) ────────────────────────────────────────────────

it('est lisible sans compte : nom, ZUMRA, état, but, histoire, lien vers la ZUMRA', function () {
    $p = projet($this->yao, $this->zumra, 'Uniformes de la rentrée');
    app(Projects::class)->changeState($this->yao, $p, ProjectState::InProgress);

    $this->get(route('projets.show', $p))->assertOk()
        ->assertSeeText('Uniformes de la rentrée')->assertSeeText('Couture solidaire')->assertSeeText('En cours')
        ->assertSeeText('Coudre 200 uniformes')->assertSeeText('Projet créé comme une idée')->assertSeeText('Passé de « Idée » à « En cours »')
        ->assertSeeText('Économie et Finances')->assertSee(route('zumras.show', $this->zumra))
        ->assertDontSeeText('Modifier le projet')->assertDontSeeText('Changer l’état');
});

it('cache aux autres personnes les actions de gestion, et les montre aux gestionnaires', function () {
    $p = projet($this->yao, $this->zumra);

    foreach ([$this->yao, $this->resp] as $qui) {
        monde_projet($qui, $p)->assertSeeText('Modifier le projet')->assertSeeText('Changer l’état du projet')->assertSeeText('Démarrer le projet')->assertSeeText('Abandonner');
    }
    foreach ([$this->membre, $this->dehors, null] as $qui) {
        monde_projet($qui, $p)->assertDontSeeText('Modifier le projet')->assertDontSeeText('Changer l’état du projet');
    }
});

it('change l’état depuis l’écran, pour un gestionnaire seulement, et refuse un état inconnu', function () {
    $p = projet($this->yao, $this->zumra);

    monde_projet($this->resp, $p)->call('changeState', 'in_progress')->assertHasNoErrors()->assertSeeText('État du projet mis à jour')->assertSeeText('Marquer terminé');
    expect($p->fresh()->state)->toBe(ProjectState::InProgress);

    monde_projet($this->membre, $p)->call('changeState', 'done')->assertNotFound();
    monde_projet($this->resp, $p)->call('changeState', 'inconnu')->assertNotFound();
    expect($p->fresh()->state)->toBe(ProjectState::InProgress);
});

it('répond « page introuvable » pour un projet qui n’existe pas', function () {
    $this->get('/projets/'.Str::uuid())->assertNotFound();
    $this->get('/projets/pas-un-identifiant')->assertNotFound();
});

it('garde le texte brut : rien n’est interprété et les adresses ne sont pas cliquables', function () {
    $p = projet($this->yao, $this->zumra, 'Projet <i>net</i>', ['goal' => "<b>gras</b> https://exemple.org\nligne 2"]);

    $html = monde_projet(null, $p)->html();

    expect($html)->toContain('&lt;b&gt;gras&lt;/b&gt;')->toContain('Projet &lt;i&gt;net&lt;/i&gt;')->not->toContain('<a href="https://exemple.org');
});

// ─── Formulaire ──────────────────────────────────────────────────────────────

it('ouvre le formulaire de création aux gestionnaires, jamais aux autres', function () {
    foreach ([$this->yao, $this->resp] as $qui) {
        $this->actingAs($qui)->get(route('projets.create', $this->zumra))->assertOk()->assertSeeText('Projet de Couture solidaire')->assertSeeText('Créer le projet');
    }
    foreach ([$this->membre, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.create', $this->zumra))->assertNotFound();
    }
});

it('renvoie un visiteur sans compte vers la connexion pour créer ou modifier', function () {
    $p = projet($this->yao, $this->zumra);

    $this->get(route('projets.create', $this->zumra))->assertRedirect(route('login'));
    $this->get(route('projets.edit', $p))->assertRedirect(route('login'));
});

it('crée un projet depuis le formulaire, et affiche les erreurs sous chaque champ', function () {
    Livewire::actingAs($this->yao)->test(FormulaireProjet::class, ['zumra' => $this->zumra])
        ->set('name', '')->set('goal', '')->call('save')->assertHasErrors(['name', 'goal'])
        ->set('name', 'Jardin partagé')->set('goal', 'Cultiver ensemble.')->call('save')->assertHasNoErrors();

    $p = Project::sole();
    expect($p->name)->toBe('Jardin partagé');
    $this->actingAs($this->yao)->get(route('projets.show', $p))->assertSeeText('Projet créé');
});

it('modifie un projet depuis le formulaire, pour un gestionnaire seulement', function () {
    $p = projet($this->yao, $this->zumra);

    Livewire::actingAs($this->resp)->test(FormulaireProjet::class, ['project' => $p])
        ->assertSet('name', 'Uniformes de la rentrée')->set('name', 'Autre nom')->call('save')->assertHasNoErrors();
    expect($p->fresh()->name)->toBe('Autre nom');

    foreach ([$this->membre, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.edit', $p))->assertNotFound();
    }
});

it('verrouille la ZUMRA et le projet du formulaire, et revérifie le droit à l’enregistrement', function () {
    $autre = zumra($this->dehors, 'Autre ZUMRA');
    $p = projet($this->yao, $this->zumra);

    $c = Livewire::actingAs($this->yao)->test(FormulaireProjet::class, ['zumra' => $this->zumra]);
    expect(fn () => $c->set('zumraId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);
    $e = Livewire::actingAs($this->yao)->test(FormulaireProjet::class, ['project' => $p]);
    expect(fn () => $e->set('projectId', (string) Str::uuid()))->toThrow(CannotUpdateLockedPropertyException::class);

    // Un responsable rétrogradé ne crée plus rien, même avec l'écran déjà ouvert.
    $ouvert = Livewire::actingAs($this->resp)->test(FormulaireProjet::class, ['zumra' => $this->zumra])->set('name', 'Projet tardif')->set('goal', 'But');
    app(Memberships::class)->demote($this->yao, $this->zumra, ligne($this->zumra, $this->resp));
    $ouvert->call('save')->assertNotFound();
    expect(Project::count())->toBe(1);
});

// ─── Onglet Projets de la ZUMRA ──────────────────────────────────────────────

it('liste les projets de la ZUMRA par ordre alphabétique, publics, avec leur état', function () {
    projet($this->yao, $this->zumra, 'Zèbre');
    $a = projet($this->yao, $this->zumra, 'Atelier');
    app(Projects::class)->changeState($this->yao, $a, ProjectState::InProgress);
    projet($this->dehors, zumra($this->dehors, 'Autre ZUMRA'), 'Projet ailleurs');

    $html = monde($this->dehors, $this->zumra, 'projets')->assertSeeText('Atelier')->assertSeeText('En cours')->assertSeeText('Idée')->assertDontSeeText('Projet ailleurs')->html();
    expect(strpos($html, 'Atelier'))->toBeLessThan(strpos($html, 'Zèbre'));

    app('auth')->forgetGuards();
    $this->get(route('zumras.show', [$this->zumra, 'onglet' => 'projets']))->assertOk()->assertSeeText('Atelier')->assertSee(route('projets.show', $a));
});

it('propose « Créer un projet » aux gestionnaires seulement, et un état vide honnête', function () {
    foreach ([$this->yao, $this->resp] as $qui) {
        monde($qui, $this->zumra, 'projets')->assertSeeText('Aucun projet pour l’instant.')->assertSeeText('Créez le premier projet')->assertSee(route('projets.create', $this->zumra));
    }
    foreach ([$this->membre, $this->dehors] as $qui) {
        monde($qui, $this->zumra, 'projets')->assertSeeText('Aucun projet pour l’instant.')->assertDontSeeText('Créer un projet')->assertDontSee(route('projets.create', $this->zumra));
    }
});

it('affiche dans l’en-tête de la ZUMRA le nombre réel de projets', function () {
    monde($this->dehors, $this->zumra, 'formation')->assertSeeText('0')->assertSeeText('projet');

    projet($this->yao, $this->zumra, 'Projet A');
    monde($this->dehors, $this->zumra, 'formation')->assertSeeText('1')->assertSeeText('projet')->assertDontSeeText('projets');

    projet($this->yao, $this->zumra, 'Projet B');
    projet($this->dehors, zumra($this->dehors, 'Autre ZUMRA'), 'Projet ailleurs');
    monde($this->dehors, $this->zumra, 'formation')->assertSeeText('2')->assertSeeText('projets');
});

it('supprime les projets et leur histoire avec la ZUMRA', function () {
    $p = projet($this->yao, $this->zumra);

    $this->zumra->delete();

    expect(Project::count())->toBe(0)->and(ProjectStateChange::where('project_id', $p->id)->count())->toBe(0);
});

// ─── Carrefour Projets ───────────────────────────────────────────────────────

function carrefour_projets(?User $qui = null)
{
    return $qui ? Livewire::actingAs($qui)->test(CarrefourProjets::class) : Livewire::test(CarrefourProjets::class);
}

it('est ouvert aux visiteurs sans compte, avec un état vide qui mène aux ZUMRA', function () {
    $this->get(route('projets.index'))->assertOk()->assertSeeText('Aucun projet pour l’instant.')->assertSee(route('zumras.index'));

    projet($this->yao, $this->zumra);
    $this->get(route('projets.index'))->assertOk()->assertSeeText('CARREFOUR')->assertSeeText('Uniformes de la rentrée')->assertSeeText('Couture solidaire')->assertSeeText('Voir le projet');
});

it('affiche la carte d’un projet : état, nom, but, ZUMRA, domaine · ville, jamais de classement', function () {
    $ville = City::query()->where('name', 'Cocody')->firstOrFail();
    $z = zumra($this->dehors, 'Tech Cocody', ['city_id' => $ville->id]);
    projet($this->dehors, $z, 'Application locale', ['goal' => 'Une application pour le quartier.']);

    carrefour_projets()->assertSeeText('Idée')->assertSeeText('Application locale')->assertSeeText('Une application pour le quartier.')->assertSeeText('Tech Cocody')
        ->assertSeeText('Économie et Finances · '.$ville->label())->assertSeeText('Par ordre alphabétique')->assertSeeText('1 projet')
        ->assertDontSeeText('Les plus actifs')->assertDontSeeText('classement');
});

it('cherche par nom ou par but, sans tenir compte de la casse ni des accents', function () {
    projet($this->yao, $this->zumra, 'Jardin partagé', ['goal' => 'Cultiver des légumes']);
    projet($this->yao, $this->zumra, 'Atelier couture', ['goal' => 'Apprendre à coudre']);

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
    projet($this->dehors, $z2, 'Projet logiciel');

    carrefour_projets()->set('state', 'in_progress')->assertSeeText('Projet couture')->assertDontSeeText('Projet logiciel')
        ->set('state', '')->set('domain', $tech)->assertSeeText('Projet logiciel')->assertDontSeeText('Projet couture')
        ->set('domain', '')->set('city', $ville)->assertSeeText('Projet logiciel')->assertDontSeeText('Projet couture')
        ->set('city', '')->set('state', 'inconnu')->assertSeeText('Projet couture')->assertSeeText('Projet logiciel')
        ->set('state', '')->set('domain', 'pas-un-identifiant')->set('city', "' or 1=1 --")->assertSeeText('Projet couture')->assertSeeText('Projet logiciel');
});

it('trie par ordre alphabétique et pagine par 10', function () {
    foreach (range(1, 12) as $i) {
        projet($this->yao, $this->zumra, sprintf('Projet %02d', $i));
    }

    $c = carrefour_projets();
    $html = $c->assertSeeText('12 projets')->assertSeeText('Page 1 sur 2')->assertSeeText('Projet 01')->assertDontSeeText('Projet 11')->html();
    expect(strpos($html, 'Projet 01'))->toBeLessThan(strpos($html, 'Projet 02'));
    $c->call('nextPage')->assertSeeText('Projet 11')->assertSeeText('Projet 12')->assertDontSeeText('Projet 01');
});

it('mène à Projets depuis la barre d’ordinateur et le Carrefour ZUMRA', function () {
    $html = $this->actingAs($this->dehors)->get('/fil')->getContent();
    expect($html)->toContain('href="'.route('projets.index').'"');

    $this->get(route('zumras.index'))->assertSeeText('Voir les projets')->assertSee(route('projets.index'));
});
