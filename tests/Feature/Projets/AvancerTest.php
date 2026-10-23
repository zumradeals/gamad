<?php

use App\Enums\ProjectStepState;
use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Livewire\Fil;
use App\Livewire\MondeProjet;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\Publication;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Memberships;
use App\Services\Moderation;
use App\Services\ProjectSteps;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
    $this->projet = projet($this->yao, $this->zumra, 'Uniformes de la rentrée');
});

function etape(User $qui, Project $projet, string $titre = 'Commander le tissu'): ProjectStep
{
    return app(ProjectSteps::class)->add($qui, $projet, $titre);
}

function avancee(User $qui, Project $projet, array $data = [], PublicationType $type = PublicationType::ProjectUpdate): Publication
{
    return app(Publications::class)->publish($qui, $type, $data + ['body' => 'Où en est le projet.'], project: $projet);
}

function projet_monde(?User $qui, Project $projet, string $onglet = 'avancement')
{
    return ($qui ? Livewire::actingAs($qui) : new class
    {
        public function __call($m, $a)
        {
            return Livewire::$m(...$a);
        }
    })->test(MondeProjet::class, ['project' => $projet])->set('tab', $onglet);
}

// ─── Étapes ──────────────────────────────────────────────────────────────────

it('laisse les gestionnaires ajouter des étapes, à faire, dans l’ordre', function () {
    $a = etape($this->yao, $this->projet, 'Commander le tissu');
    $b = etape($this->resp, $this->projet, 'Couper');
    $c = etape($this->yao, $this->projet, 'Coudre');

    expect($this->projet->steps()->pluck('title')->all())->toBe(['Commander le tissu', 'Couper', 'Coudre'])
        ->and($this->projet->steps()->pluck('position')->all())->toBe([1, 2, 3])
        ->and($a->fresh()->state)->toBe(ProjectStepState::Todo);
});

it('refuse les étapes à un membre, à une personne extérieure et à un visiteur', function () {
    foreach ([$this->membre, $this->dehors] as $qui) {
        expect(fn () => etape($qui, $this->projet))->toThrow(AuthorizationException::class);
    }
    expect(ProjectStep::count())->toBe(0);
});

it('vérifie le titre d’une étape (2 à 80 caractères) et limite à 20 étapes par projet', function () {
    foreach (['a', '   ', str_repeat('a', 81)] as $mauvais) {
        expect(fn () => etape($this->yao, $this->projet, $mauvais))->toThrow(ValidationException::class);
    }
    expect(etape($this->yao, $this->projet, 'ab')->title)->toBe('ab');
    expect(etape($this->yao, $this->projet, str_repeat('é', 80))->title)->toHaveLength(80);

    foreach (range(3, 20) as $i) {
        etape($this->yao, $this->projet, "Étape $i");
    }
    expect(fn () => etape($this->yao, $this->projet, 'Une de trop'))->toThrow(ValidationException::class, '20 étapes au plus');
    expect(ProjectStep::count())->toBe(20);

    // La limite est par projet.
    expect(etape($this->yao, projet($this->yao, $this->zumra, 'Autre projet'), 'Première')->position)->toBe(1);
});

it('renomme une étape, pour un gestionnaire seulement', function () {
    $e = etape($this->yao, $this->projet);

    app(ProjectSteps::class)->rename($this->resp, $e, 'Acheter le tissu');
    expect($e->fresh()->title)->toBe('Acheter le tissu');

    expect(fn () => app(ProjectSteps::class)->rename($this->membre, $e->fresh(), 'Intrus'))->toThrow(AuthorizationException::class);
    expect(fn () => app(ProjectSteps::class)->rename($this->yao, $e->fresh(), 'x'))->toThrow(ValidationException::class);
    expect($e->fresh()->title)->toBe('Acheter le tissu');
});

it('change l’état d’une étape, pour un gestionnaire seulement, et date le changement', function () {
    $e = etape($this->yao, $this->projet);
    $avant = $e->fresh()->state_changed_at;

    $this->travel(2)->minutes();
    app(ProjectSteps::class)->setState($this->resp, $e, ProjectStepState::InProgress);
    expect($e->fresh()->state)->toBe(ProjectStepState::InProgress)->and($e->fresh()->state_changed_at->gt($avant))->toBeTrue();

    $date = $e->fresh()->state_changed_at;
    $this->travel(2)->minutes();
    app(ProjectSteps::class)->setState($this->resp, $e->fresh(), ProjectStepState::InProgress);
    expect($e->fresh()->state_changed_at->equalTo($date))->toBeTrue();

    foreach ([$this->membre, $this->dehors] as $qui) {
        expect(fn () => app(ProjectSteps::class)->setState($qui, $e->fresh(), ProjectStepState::Done))->toThrow(AuthorizationException::class);
    }
    expect($e->fresh()->state)->toBe(ProjectStepState::InProgress);
});

it('déplace une étape d’un cran, sans effet aux extrémités, et garde des positions consécutives', function () {
    $a = etape($this->yao, $this->projet, 'Étape A');
    $b = etape($this->yao, $this->projet, 'Étape B');
    $c = etape($this->yao, $this->projet, 'Étape C');
    $titres = fn () => $this->projet->steps()->pluck('title')->all();

    app(ProjectSteps::class)->move($this->yao, $c, -1);
    expect($titres())->toBe(['Étape A', 'Étape C', 'Étape B']);
    app(ProjectSteps::class)->move($this->yao, $c->fresh(), -1);
    expect($titres())->toBe(['Étape C', 'Étape A', 'Étape B']);
    app(ProjectSteps::class)->move($this->yao, $c->fresh(), -1);
    expect($titres())->toBe(['Étape C', 'Étape A', 'Étape B']);
    app(ProjectSteps::class)->move($this->yao, $b->fresh(), 1);
    expect($titres())->toBe(['Étape C', 'Étape A', 'Étape B']);
    app(ProjectSteps::class)->move($this->resp, $c->fresh(), 1);
    expect($titres())->toBe(['Étape A', 'Étape C', 'Étape B'])->and($this->projet->steps()->pluck('position')->all())->toBe([1, 2, 3]);

    expect(fn () => app(ProjectSteps::class)->move($this->membre, $a->fresh(), 1))->toThrow(AuthorizationException::class);
    expect($titres())->toBe(['Étape A', 'Étape C', 'Étape B']);
});

it('retire une étape sans laisser de trou dans l’ordre, pour un gestionnaire seulement', function () {
    $a = etape($this->yao, $this->projet, 'Étape A');
    $b = etape($this->yao, $this->projet, 'Étape B');
    etape($this->yao, $this->projet, 'Étape C');

    expect(fn () => app(ProjectSteps::class)->remove($this->dehors, $b))->toThrow(AuthorizationException::class);
    app(ProjectSteps::class)->remove($this->resp, $b);

    expect($this->projet->steps()->pluck('title')->all())->toBe(['Étape A', 'Étape C'])->and($this->projet->steps()->pluck('position')->all())->toBe([1, 2]);

    // On peut ajouter après un retrait sans heurter l'unicité des positions.
    expect(etape($this->yao, $this->projet, 'Étape D')->position)->toBe(3);
});

it('garantit en base une position unique par projet et la liste des états d’étape', function () {
    $e = etape($this->yao, $this->projet);
    $ligne = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'project_id' => $this->projet->id, 'title' => 'x', 'state' => 'todo', 'position' => 9, 'state_changed_at' => now(), 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('project_steps')->insert($ligne(['position' => $e->position]))))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('project_steps')->insert($ligne(['state' => 'inconnu']))))->toThrow(QueryException::class);
});

it('supprime les étapes avec le projet', function () {
    etape($this->yao, $this->projet);

    $this->projet->delete();

    expect(ProjectStep::count())->toBe(0);
});

// ─── Avancement affiché ──────────────────────────────────────────────────────

it('affiche l’avancement à partir des étapes réelles : étapes franchies sur le total, et rien sans étape', function () {
    projet_monde(null, $this->projet)->assertDontSeeText('étape franchie')->assertDontSeeText('étapes franchies')->assertDontSee('role="progressbar"', false)->assertSeeText('Aucune étape pour l’instant.');

    $a = etape($this->yao, $this->projet, 'Commander');
    etape($this->yao, $this->projet, 'Couper');
    etape($this->yao, $this->projet, 'Coudre');
    projet_monde(null, $this->projet)->assertSeeText('0 étape franchie sur 3');

    app(ProjectSteps::class)->setState($this->yao, $a, ProjectStepState::Done);
    $html = projet_monde(null, $this->projet)->assertSeeText('1 étape franchie sur 3')->html();
    expect($html)->toContain('aria-valuenow="1"')->toContain('aria-valuemax="3"')->toContain('width: 33%');

    app(ProjectSteps::class)->setState($this->yao, ProjectStep::where('title', 'Couper')->first(), ProjectStepState::Done);
    projet_monde(null, $this->projet)->assertSeeText('2 étapes franchies sur 3');
});

it('montre les étapes à tous, dans l’ordre, avec leur état, et les commandes aux gestionnaires seulement', function () {
    etape($this->yao, $this->projet, 'Commander');
    etape($this->yao, $this->projet, 'Couper');

    $html = projet_monde(null, $this->projet)->assertSeeText('1. Commander')->assertSeeText('2. Couper')->assertSeeText('À faire')
        ->assertDontSeeText('Nouvelle étape')->assertDontSeeText('Monter')->assertDontSeeText('Retirer')->html();
    expect(strpos($html, 'Commander'))->toBeLessThan(strpos($html, 'Couper'));

    foreach ([$this->membre, $this->dehors] as $qui) {
        projet_monde($qui, $this->projet)->assertDontSeeText('Nouvelle étape')->assertDontSeeText('Monter');
    }
    foreach ([$this->yao, $this->resp] as $qui) {
        projet_monde($qui, $this->projet)->assertSeeText('Nouvelle étape')->assertSeeText('Monter')->assertSeeText('Descendre')->assertSeeText('Retirer');
    }
});

it('gère les étapes depuis l’écran : ajouter, changer l’état, déplacer, retirer', function () {
    $c = projet_monde($this->resp, $this->projet)->set('stepTitle', 'Commander le tissu')->call('addStep')->assertHasNoErrors()->assertSet('stepTitle', '')->assertSeeText('1. Commander le tissu')
        ->set('stepTitle', 'Couper')->call('addStep');

    $premier = $this->projet->steps()->first();
    $second = $this->projet->steps()->skip(1)->first();

    $c->call('setStepState', $premier->id, 'done')->assertSeeText('1 étape franchie sur 2')
        ->call('moveStep', $second->id, -1)->assertSeeTextInOrder(['1. Couper', '2. Commander le tissu'])
        ->call('removeStep', $premier->id)->assertDontSeeText('Commander le tissu')->assertSeeText('1. Couper');

    expect($this->projet->steps()->count())->toBe(1);
});

it('affiche l’erreur du titre d’étape sous le champ', function () {
    projet_monde($this->yao, $this->projet)->set('stepTitle', '')->call('addStep')->assertHasErrors(['stepTitle'])->assertSeeText('Le nom d’une étape doit avoir entre 2 et 80 caractères.');
});

it('refuse les actions d’étape à un membre ou une personne extérieure, depuis l’écran', function () {
    $e = etape($this->yao, $this->projet);

    foreach ([$this->membre, $this->dehors] as $qui) {
        projet_monde($qui, $this->projet)->set('stepTitle', 'Intrus')->call('addStep')->assertNotFound();
        projet_monde($qui, $this->projet)->call('setStepState', $e->id, 'done')->assertNotFound();
        projet_monde($qui, $this->projet)->call('moveStep', $e->id, 1)->assertNotFound();
        projet_monde($qui, $this->projet)->call('removeStep', $e->id)->assertNotFound();
    }
    expect($e->fresh()->state)->toBe(ProjectStepState::Todo)->and(ProjectStep::count())->toBe(1);
});

it('ne touche pas une étape d’un autre projet, ni un identifiant qui n’en est pas un, ni un état inconnu', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    $ailleurs = etape($this->yao, $autre, 'Étape ailleurs');
    $ici = etape($this->yao, $this->projet);

    projet_monde($this->yao, $this->projet)->call('setStepState', $ailleurs->id, 'done')->assertNotFound();
    projet_monde($this->yao, $this->projet)->call('removeStep', $ailleurs->id)->assertNotFound();
    projet_monde($this->yao, $this->projet)->call('moveStep', 'pas-un-uuid', 1)->assertNotFound();
    projet_monde($this->yao, $this->projet)->call('setStepState', $ici->id, 'inconnu')->assertNotFound();

    expect($ailleurs->fresh()->state)->toBe(ProjectStepState::Todo)->and(ProjectStep::count())->toBe(2);
});

it('verrouille le projet de l’écran', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    $c = projet_monde($this->yao, $this->projet);

    expect(fn () => $c->set('projectId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('cache les étapes à qui a été rétrogradé, même avec l’écran déjà ouvert', function () {
    $c = projet_monde($this->resp, $this->projet);
    app(Memberships::class)->demote($this->yao, $this->zumra, ligne($this->zumra, $this->resp));

    $c->set('stepTitle', 'Tardive')->call('addStep')->assertNotFound();
    expect(ProjectStep::count())->toBe(0);
});

// ─── Publier au nom d'un projet ──────────────────────────────────────────────

it('laisse les gestionnaires publier une avancée et une réalisation au nom du projet, qui sont aussi des publications de la ZUMRA', function () {
    foreach ([$this->yao, $this->resp] as $qui) {
        foreach ([PublicationType::ProjectUpdate, PublicationType::Achievement] as $type) {
            $p = avancee($qui, $this->projet, type: $type);

            expect($p->project_id)->toBe($this->projet->id)->and($p->zumra_id)->toBe($this->zumra->id)->and($p->author_id)->toBe($qui->id)
                ->and($p->type)->toBe($type)->and($p->need_state)->toBeNull();
        }
    }
    expect(Publication::count())->toBe(4);
});

it('refuse de publier au nom du projet à un membre, à une personne extérieure', function () {
    foreach ([$this->membre, $this->dehors] as $qui) {
        expect(fn () => avancee($qui, $this->projet))->toThrow(AuthorizationException::class);
    }
    expect(Publication::count())->toBe(0);
});

it('n’admet au nom d’un projet que l’avancée, la réalisation et le besoin, et les deux premiers types que pour un projet', function () {
    foreach ([PublicationType::Knowledge, PublicationType::Offer, PublicationType::LearningWish, PublicationType::ZumraCall] as $type) {
        expect(fn () => avancee($this->yao, $this->projet, type: $type))->toThrow(ValidationException::class, 'n’existe pas pour un projet');
    }

    foreach ([PublicationType::ProjectUpdate, PublicationType::Achievement] as $type) {
        expect(fn () => publication($this->yao, $type))->toThrow(ValidationException::class, 'au nom d’un projet');
        expect(fn () => app(Publications::class)->publish($this->yao, $type, ['body' => 'x'], $this->zumra))->toThrow(ValidationException::class, 'au nom d’un projet');
    }
    expect(Publication::count())->toBe(0);
});

it('admet les trois niveaux de visibilité pour une publication de projet, et vérifie le texte', function () {
    foreach (['public', 'members', 'zumra'] as $niveau) {
        expect(avancee($this->yao, $this->projet, ['visibility' => $niveau])->visibility)->toBe($niveau);
    }
    expect(fn () => avancee($this->yao, $this->projet, ['visibility' => 'secret']))->toThrow(ValidationException::class);
    expect(fn () => avancee($this->yao, $this->projet, ['body' => '  ']))->toThrow(ValidationException::class);
    expect(fn () => avancee($this->yao, $this->projet, ['body' => str_repeat('a', 501)]))->toThrow(ValidationException::class);
});

it('garantit en base qu’une avancée ou une réalisation a un projet, que seules elles en ont, et qu’il y a une ZUMRA', function () {
    $base = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'author_id' => $this->yao->id, 'body' => 'x', 'visibility' => 'public', 'created_at' => now(), 'updated_at' => now()];

    $mauvais = [
        ['type' => 'project_update'],
        ['type' => 'achievement', 'zumra_id' => $this->zumra->id],
        ['type' => 'knowledge', 'project_id' => $this->projet->id, 'zumra_id' => $this->zumra->id],
        ['type' => 'need', 'need_state' => 'open', 'project_id' => $this->projet->id],
        ['type' => 'project_update', 'project_id' => $this->projet->id],
    ];
    foreach ($mauvais as $data) {
        expect(fn () => DB::transaction(fn () => DB::table('publications')->insert($base($data))))->toThrow(QueryException::class);
    }
    expect(Publication::count())->toBe(0);
});

it('supprime les avancées et réalisations avec le projet, et laisse les autres publications de la ZUMRA', function () {
    avancee($this->yao, $this->projet);
    avancee($this->yao, $this->projet, type: PublicationType::Achievement);
    app(Publications::class)->publish($this->yao, PublicationType::Need, ['body' => 'Besoin de la ZUMRA'], $this->zumra);

    $this->projet->delete();

    expect(Publication::count())->toBe(1)->and(Publication::first()->type)->toBe(PublicationType::Need);
});

// ─── Écran de publication ────────────────────────────────────────────────────

it('ouvre l’écran de publication d’un projet aux gestionnaires, avec les trois niveaux de visibilité', function () {
    foreach ([$this->yao, $this->resp] as $qui) {
        $this->actingAs($qui)->get(route('projets.publier', [$this->projet, 'avancee']))->assertOk()
            ->assertSeeText('Vous publiez au nom du projet Uniformes de la rentrée (Couture solidaire)')->assertSeeText('Publier l’avancée')
            ->assertSeeText('Public')->assertSeeText('Inscrits seulement')->assertSeeText('ZUMRA seulement');
    }
    $this->actingAs($this->yao)->get(route('projets.publier', [$this->projet, 'realisation']))->assertOk()->assertSeeText('Publier la réalisation');
});

it('ferme l’écran de publication d’un projet aux autres, aux types qui n’existent pas pour un projet et aux visiteurs', function () {
    foreach ([$this->membre, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('projets.publier', [$this->projet, 'avancee']))->assertNotFound();
    }
    foreach (['savoir', 'aide', 'apprendre', 'appel', 'n-importe-quoi'] as $slug) {
        $this->actingAs($this->yao)->get(route('projets.publier', [$this->projet, $slug]))->assertNotFound();
    }
});

it('renvoie un visiteur sans compte vers la connexion pour publier au nom d’un projet', function () {
    $this->get(route('projets.publier', [$this->projet, 'avancee']))->assertRedirect(route('login'));
});

it('publie depuis l’écran et mène à la publication ; le texte vide est refusé', function () {
    Livewire::actingAs($this->resp)->test(PublierPublication::class, ['slug' => 'avancee', 'project' => $this->projet])
        ->assertSet('domain_id', $this->zumra->domain_id)
        ->set('body', '')->call('publish')->assertHasErrors(['body'])
        ->set('body', 'Les trois écoles ont validé la commande.')->set('visibility', 'zumra')->call('publish')->assertHasNoErrors();

    $p = Publication::sole();
    expect($p->type)->toBe(PublicationType::ProjectUpdate)->and($p->project_id)->toBe($this->projet->id)->and($p->visibility)->toBe('zumra');
});

it('verrouille le projet et la ZUMRA de l’écran, et revérifie le droit de publier', function () {
    $autre = projet($this->yao, $this->zumra, 'Autre projet');
    $c = Livewire::actingAs($this->yao)->test(PublierPublication::class, ['slug' => 'avancee', 'project' => $this->projet]);

    expect(fn () => $c->set('projectId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $c->set('zumraId', (string) Str::uuid()))->toThrow(CannotUpdateLockedPropertyException::class);

    $ouvert = Livewire::actingAs($this->resp)->test(PublierPublication::class, ['slug' => 'avancee', 'project' => $this->projet])->set('body', 'Tardive');
    app(Memberships::class)->demote($this->yao, $this->zumra, ligne($this->zumra, $this->resp));
    $ouvert->call('publish')->assertNotFound();
    expect(Publication::count())->toBe(0);
});

// ─── Fil du projet ───────────────────────────────────────────────────────────

it('montre le Fil du projet : avancées et réalisations visibles, plus récentes d’abord, avec le lien vers le projet', function () {
    avancee($this->yao, $this->projet, ['body' => 'Première avancée']);
    avancee($this->resp, $this->projet, ['body' => 'Belle réalisation'], PublicationType::Achievement);
    avancee($this->yao, projet($this->yao, $this->zumra, 'Autre projet'), ['body' => 'Avancée ailleurs']);

    $html = projet_monde($this->dehors, $this->projet, 'fil')->assertSeeText('Première avancée')->assertSeeText('Belle réalisation')->assertDontSeeText('Avancée ailleurs')
        ->assertSeeText('Avancée de projet')->assertSeeText('Réalisation')->assertSeeText('Projet : Uniformes de la rentrée')->html();
    expect(strpos($html, 'Belle réalisation'))->toBeLessThan(strpos($html, 'Première avancée'));

    // Page publique : un visiteur sans compte lit aussi le Fil.
    $this->get(route('projets.show', ['project' => $this->projet, 'onglet' => 'fil']))->assertOk()->assertSeeText('Première avancée');
});

it('applique la visibilité de chaque publication dans le Fil du projet', function () {
    avancee($this->yao, $this->projet, ['body' => 'Texte public', 'visibility' => 'public']);
    avancee($this->yao, $this->projet, ['body' => 'Texte inscrits', 'visibility' => 'members']);
    avancee($this->yao, $this->projet, ['body' => 'Texte ZUMRA', 'visibility' => 'zumra']);

    projet_monde($this->membre, $this->projet, 'fil')->assertSeeText('Texte public')->assertSeeText('Texte inscrits')->assertSeeText('Texte ZUMRA');
    projet_monde($this->dehors, $this->projet, 'fil')->assertSeeText('Texte public')->assertSeeText('Texte inscrits')->assertDontSeeText('Texte ZUMRA');
    app('auth')->forgetGuards();
    projet_monde(null, $this->projet, 'fil')->assertSeeText('Texte public')->assertDontSeeText('Texte inscrits')->assertDontSeeText('Texte ZUMRA');
});

it('propose « Publier une avancée » et « Publier une réalisation » aux gestionnaires seulement, avec un état vide honnête', function () {
    foreach ([$this->yao, $this->resp] as $qui) {
        $html = projet_monde($qui, $this->projet, 'fil')->assertSeeText('Rien dans le Fil de ce projet pour l’instant.')->assertSeeText('Publier une avancée')->assertSeeText('Publier une réalisation')->html();
        expect($html)->toContain(route('projets.publier', [$this->projet, 'avancee']))->toContain(route('projets.publier', [$this->projet, 'realisation']));
    }
    foreach ([$this->membre, $this->dehors, null] as $qui) {
        projet_monde($qui, $this->projet, 'fil')->assertSeeText('Rien dans le Fil de ce projet pour l’instant.')->assertDontSeeText('Publier une avancée');
    }
});

it('pagine le Fil du projet : dix publications d’abord, les plus anciennes sur demande', function () {
    foreach (range(1, 12) as $i) {
        avancee($this->yao, $this->projet, ['body' => sprintf('Avancée n°%02d', $i)]);
    }

    projet_monde($this->dehors, $this->projet, 'fil')->assertSeeText('Avancée n°12')->assertSeeText('Avancée n°03')->assertDontSeeText('Avancée n°02')->assertDontSeeText('Avancée n°01')
        ->assertSeeText('Voir plus de publications')->call('more')->assertSeeText('Avancée n°02')->assertSeeText('Avancée n°01')->assertDontSeeText('Voir plus de publications');
});

it('ouvre les onglets Avancement et Fil, et ignore un onglet inconnu', function () {
    projet_monde($this->dehors, $this->projet, 'fil')->assertSeeText('Fil du projet')->assertDontSeeText('Histoire du projet');
    projet_monde($this->dehors, $this->projet, 'avancement')->assertSeeText('Histoire du projet')->assertDontSeeText('Fil du projet');
    projet_monde($this->dehors, $this->projet)->call('setTab', 'fil')->assertSet('tab', 'fil')->call('setTab', 'inconnu')->assertSet('tab', 'avancement')->assertSeeText('Histoire du projet');
});

it('montre les quatre onglets : Avancement, Fil, Équipe et Besoins', function () {
    foreach ([$this->yao, $this->dehors, null] as $qui) {
        projet_monde($qui, $this->projet)->assertSeeText('Avancement')->assertSeeText('Fil')->assertSeeText('Équipe')->assertSeeText('Besoins');
    }
});

// ─── Pas de geste sur une publication de projet ──────────────────────────────

it('n’admet aucun geste sur une avancée ou une réalisation (le soutien est autre chose), et n’affiche aucun bouton de réponse', function () {
    $p = avancee($this->yao, $this->projet);

    foreach ([PublicationType::ProjectUpdate, PublicationType::Achievement] as $type) {
        expect($type->takesGestures())->toBeFalse();
    }
    expect($p->acceptsGestures())->toBeFalse();
    foreach ([$this->dehors, $this->membre] as $qui) {
        expect(fn () => app(Gestures::class)->propose($qui, $p, 'Je soutiens'))->toThrow(AuthorizationException::class);
    }

    foreach ([$this->dehors, $this->membre, null] as $qui) {
        projet_monde($qui, $this->projet, 'fil')->assertDontSeeText('Se connecter pour répondre')->assertDontSeeText('Je peux aider')
            ->assertDontSeeText('Ça m’intéresse')->assertDontSeeText('Voir ma publication');
    }
    expect(PublicationType::Need->takesGestures())->toBeTrue()->and(PublicationType::ZumraCall->takesGestures())->toBeFalse();
});

it('montre à un gestionnaire sa publication de projet, sans réponses à attendre', function () {
    $p = avancee($this->yao, $this->projet, ['body' => 'Notre avancée']);

    Livewire::actingAs($this->resp)->test(PagePublication::class, ['publication' => $p])
        ->assertSeeText('Notre avancée de projet')->assertSeeText('Projet : Uniformes de la rentrée')->assertDontSeeText('Personne n’a encore répondu')->assertDontSeeText('Aucune réponse pour l’instant');

    Livewire::actingAs($this->dehors)->test(PagePublication::class, ['publication' => $p])->assertSeeText('Avancée de projet')->assertSeeText('Projet : Uniformes de la rentrée');
});

// ─── Fil global et ZUMRA ─────────────────────────────────────────────────────

it('montre les avancées dans le Fil global, avec le projet, et les filtre sur « Projets »', function () {
    avancee($this->yao, $this->projet, ['body' => 'Avancée du projet']);
    app(Publications::class)->publish($this->yao, PublicationType::Need, ['body' => 'Besoin de la ZUMRA'], $this->zumra);
    publication($this->dehors, data: ['body' => 'Besoin personnel']);

    Livewire::actingAs($this->dehors)->test(Fil::class)->assertSeeText('Avancée du projet')->assertSeeText('Besoin de la ZUMRA')->assertSeeText('Projet : Uniformes de la rentrée')
        ->call('setFilter', 'projets')->assertSeeText('Avancée du projet')->assertDontSeeText('Besoin de la ZUMRA')->assertDontSeeText('Besoin personnel')
        ->call('setFilter', 'zumra')->assertSeeText('Avancée du projet')->assertSeeText('Besoin de la ZUMRA')->assertDontSeeText('Besoin personnel')
        ->call('setFilter', 'tout')->assertSeeText('Besoin personnel');
});

it('applique la visibilité d’une publication de projet dans le Fil global, et la page de la publication', function () {
    $p = avancee($this->yao, $this->projet, ['body' => 'Réservée à la ZUMRA', 'visibility' => 'zumra']);

    Livewire::actingAs($this->membre)->test(Fil::class)->assertSeeText('Réservée à la ZUMRA');
    Livewire::actingAs($this->dehors)->test(Fil::class)->assertDontSeeText('Réservée à la ZUMRA');
    $this->actingAs($this->membre)->get(route('publications.show', $p))->assertOk();
    $this->actingAs($this->dehors)->get(route('publications.show', $p))->assertNotFound();
});

it('montre aussi les avancées dans le Fil de la ZUMRA', function () {
    avancee($this->yao, $this->projet, ['body' => 'Avancée du projet']);

    monde($this->dehors, $this->zumra, 'fil')->assertSeeText('Avancée du projet')->assertSeeText('Projet : Uniformes de la rentrée');
    monde($this->dehors, $this->zumra, 'formation')->assertDontSeeText('Avancée du projet');
});

it('laisse la modération masquer une publication de projet, que les gestionnaires voient encore', function () {
    $p = avancee($this->yao, $this->projet, ['body' => 'Avancée à masquer']);
    app(Moderation::class)->hide(User::factory()->moderator()->create(), $p, 'Motif de test');

    projet_monde($this->dehors, $this->projet, 'fil')->assertDontSeeText('Avancée à masquer');
    projet_monde($this->resp, $this->projet, 'fil')->assertSeeText('Avancée à masquer');
    Livewire::actingAs($this->dehors)->test(Fil::class)->assertDontSeeText('Avancée à masquer');
});

it('ne fait pas une requête de plus par carte pour afficher le projet', function () {
    foreach (range(1, 6) as $i) {
        avancee($this->yao, $this->projet, ['body' => "Avancée $i"]);
    }

    $c = Livewire::actingAs($this->dehors)->test(Fil::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $c->call('$refresh');
    $requetes = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (range(7, 12) as $i) {
        avancee($this->yao, $this->projet, ['body' => "Avancée $i"]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $c->call('$refresh');
    $plus = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($plus)->toBeLessThanOrEqual($requetes + 2);
});
