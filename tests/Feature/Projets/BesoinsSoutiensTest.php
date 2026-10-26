<?php

use App\Enums\GestureState;
use App\Enums\NeedState;
use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Livewire\Fil;
use App\Livewire\GestureSheet;
use App\Livewire\MondeProjet;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\Gesture;
use App\Models\Project;
use App\Models\Publication;
use App\Models\PublicationSupport;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Gestures;
use App\Services\Moderation;
use App\Services\ProjectTeam;
use App\Services\Publications;
use App\Services\Supports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    $this->membre = person('Simple Membre', []);
    $this->dehors = person('Personne Extérieure', []);
    $this->autre = person('Autre Personne', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    ajouteMembre($this->zumra, $this->membre);
    $this->projet = projet($this->yao, $this->zumra, 'Uniformes de la rentrée');
    // Un projet est privé (P1) : son porteur (yao) et ses collaborateurs en ouvrent le dossier. Deux collaborateurs, l'un membre de la ZUMRA, l'autre non.
    app(ProjectTeam::class)->enter($this->projet, $this->membre, null);
    app(ProjectTeam::class)->enter($this->projet, $this->dehors, null);
    app(ProjectTeam::class)->enter($this->projet, $this->autre, null);
});

function besoin_projet(User $qui, Project $projet, array $data = []): Publication
{
    return app(Publications::class)->publish($qui, PublicationType::Need, $data + ['body' => 'Il nous faut du tissu.'], project: $projet);
}

function avancee_projet(User $qui, Project $projet, array $data = []): Publication
{
    return app(Publications::class)->publish($qui, PublicationType::ProjectUpdate, $data + ['body' => 'Où en est le projet.'], project: $projet);
}

function monde_onglet(?User $qui, Project $projet, string $onglet)
{
    return ($qui ? Livewire::actingAs($qui) : new class
    {
        public function __call($m, $a)
        {
            return Livewire::$m(...$a);
        }
    })->test(MondeProjet::class, ['project' => $projet])->set('tab', $onglet);
}

// ─── Besoins de projet ───────────────────────────────────────────────────────

it('laisse le porteur publier un besoin au nom du projet : un besoin ouvert, de la ZUMRA et du projet', function () {
    foreach ([$this->yao] as $qui) {
        $p = besoin_projet($qui, $this->projet);

        expect($p->type)->toBe(PublicationType::Need)->and($p->need_state)->toBe(NeedState::Open)->and($p->project_id)->toBe($this->projet->id)
            ->and($p->zumra_id)->toBe($this->zumra->id)->and($p->author_id)->toBe($qui->id);
    }
});

it('refuse le besoin de projet à un collaborateur et à un responsable de la ZUMRA', function () {
    foreach ([$this->membre, $this->dehors, $this->resp] as $qui) {
        expect(fn () => besoin_projet($qui, $this->projet))->toThrow(AuthorizationException::class);
    }
    expect(Publication::count())->toBe(0);
});

it('ouvre l’écran « Publier un besoin » d’un projet à son porteur, et publie', function () {
    $this->actingAs($this->yao)->get(route('projets.publier', [$this->projet, 'besoin']))->assertOk()->assertSeeText('Vous publiez au nom du projet Uniformes de la rentrée')->assertSeeText('Publier mon besoin');
    $this->actingAs($this->membre)->get(route('projets.publier', [$this->projet, 'besoin']))->assertNotFound();

    Livewire::actingAs($this->yao)->test(PublierPublication::class, ['slug' => 'besoin', 'project' => $this->projet])
        ->set('body', 'Nous cherchons trois machines à coudre.')->call('publish')->assertHasNoErrors();

    $p = Publication::sole();
    expect($p->type)->toBe(PublicationType::Need)->and($p->project_id)->toBe($this->projet->id);
});

it('garantit en base qu’un besoin de projet a une ZUMRA, et que seuls trois types ont un projet', function () {
    $base = fn (array $data) => $data + ['id' => (string) Str::uuid(), 'author_id' => $this->yao->id, 'body' => 'x', 'visibility' => 'public', 'created_at' => now(), 'updated_at' => now()];

    foreach ([['type' => 'need', 'need_state' => 'open', 'project_id' => $this->projet->id], ['type' => 'offer', 'project_id' => $this->projet->id, 'zumra_id' => $this->zumra->id]] as $data) {
        expect(fn () => DB::transaction(fn () => DB::table('publications')->insert($base($data))))->toThrow(QueryException::class);
    }
    expect(Publication::count())->toBe(0);
});

it('liste les besoins du projet dans l’onglet Besoins, et les avancées dans le Fil, sans les mêler', function () {
    besoin_projet($this->yao, $this->projet, ['body' => 'Besoin de tissu']);
    avancee_projet($this->yao, $this->projet, ['body' => 'Avancée du projet']);
    besoin_projet($this->yao, projet($this->yao, $this->zumra, 'Autre projet'), ['body' => 'Besoin ailleurs']);

    monde_onglet($this->dehors, $this->projet, 'besoins')->assertSeeText('Besoin de tissu')->assertSeeText('Projet : Uniformes de la rentrée')->assertDontSeeText('Avancée du projet')->assertDontSeeText('Besoin ailleurs');
    monde_onglet($this->dehors, $this->projet, 'fil')->assertSeeText('Avancée du projet')->assertDontSeeText('Besoin de tissu');
});

it('applique la visibilité de chaque besoin dans l’onglet Besoins', function () {
    besoin_projet($this->yao, $this->projet, ['body' => 'Besoin public', 'visibility' => 'public']);
    besoin_projet($this->yao, $this->projet, ['body' => 'Besoin inscrits', 'visibility' => 'members']);
    besoin_projet($this->yao, $this->projet, ['body' => 'Besoin ZUMRA', 'visibility' => 'zumra']);

    monde_onglet($this->membre, $this->projet, 'besoins')->assertSeeText('Besoin public')->assertSeeText('Besoin inscrits')->assertSeeText('Besoin ZUMRA');
    monde_onglet($this->dehors, $this->projet, 'besoins')->assertSeeText('Besoin public')->assertSeeText('Besoin inscrits')->assertDontSeeText('Besoin ZUMRA');
});

it('propose « Publier un besoin » au porteur seulement, avec un état vide honnête', function () {
    foreach ([$this->yao] as $qui) {
        monde_onglet($qui, $this->projet, 'besoins')->assertSeeText('Aucun besoin pour ce projet pour l’instant.')->assertSeeText('Publier un besoin')->assertSee(route('projets.publier', [$this->projet, 'besoin']));
    }
    foreach ([$this->membre, $this->dehors] as $qui) {
        monde_onglet($qui, $this->projet, 'besoins')->assertSeeText('Aucun besoin pour ce projet pour l’instant.')->assertDontSeeText('Publier un besoin');
    }
});

it('pagine les besoins du projet', function () {
    foreach (range(1, 12) as $i) {
        besoin_projet($this->yao, $this->projet, ['body' => sprintf('Besoin n°%02d', $i)]);
    }

    monde_onglet($this->dehors, $this->projet, 'besoins')->assertSeeText('Besoin n°12')->assertDontSeeText('Besoin n°01')->assertSeeText('Voir plus de besoins')
        ->call('more')->assertSeeText('Besoin n°01')->assertDontSeeText('Voir plus de besoins');
});

it('laisse une personne répondre à un besoin de projet, jamais ses gestionnaires, et prévient tous les gestionnaires', function () {
    $besoin = besoin_projet($this->yao, $this->projet);

    app(Gestures::class)->propose($this->dehors, $besoin, 'Je peux prêter des machines.');
    app(Gestures::class)->propose($this->membre, $besoin);
    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        expect(fn () => app(Gestures::class)->propose($gestionnaire, $besoin))->toThrow(AuthorizationException::class);
    }

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        expect(UserNotification::query()->where('user_id', $gestionnaire->id)->where('kind', NotificationKind::GestureReceived->value)->count())->toBe(2);
    }
    expect(Gesture::count())->toBe(2);
});

it('montre le bouton de réponse sur un besoin de projet, et envoie le geste depuis l’onglet Besoins', function () {
    $besoin = besoin_projet($this->yao, $this->projet);

    monde_onglet($this->dehors, $this->projet, 'besoins')->assertSeeText('Je peux aider');
    monde_onglet($this->yao, $this->projet, 'besoins')->assertDontSeeText('Je peux aider')->assertSeeText('Voir ma publication');

    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->dispatch('open-gesture', id: $besoin->id)->set('message', 'Je suis disponible.')->call('send');
    expect(Gesture::sole()->state)->toBe(GestureState::Sent);

    monde_onglet($this->dehors, $this->projet, 'besoins')->assertSeeText('Proposition envoyée');
});

it('laisse un gestionnaire accepter la réponse et résoudre le besoin de projet', function () {
    $besoin = besoin_projet($this->yao, $this->projet);
    $g = app(Gestures::class)->propose($this->dehors, $besoin);

    Livewire::actingAs($this->resp)->test(PagePublication::class, ['publication' => $besoin])->assertSeeText('Propositions reçues')->call('accept', $g->id)->assertHasNoErrors();
    expect($besoin->fresh()->need_state)->toBe(NeedState::InProgress);

    app(Publications::class)->resolve($this->yao, $besoin->fresh());
    expect($besoin->fresh()->need_state)->toBe(NeedState::Resolved);
});

it('montre un besoin de projet dans le Fil global, avec le projet, et dans le filtre « Projets »', function () {
    besoin_projet($this->yao, $this->projet, ['body' => 'Besoin de projet']);
    app(Publications::class)->publish($this->yao, PublicationType::Need, ['body' => 'Besoin de la ZUMRA'], $this->zumra);
    publication($this->dehors, data: ['body' => 'Besoin personnel']);

    Livewire::actingAs($this->autre)->test(Fil::class)->assertSeeText('Besoin de projet')->assertSeeText('Projet : Uniformes de la rentrée')
        ->call('setFilter', 'projets')->assertSeeText('Besoin de projet')->assertDontSeeText('Besoin de la ZUMRA')->assertDontSeeText('Besoin personnel');
});

// ─── Soutiens ────────────────────────────────────────────────────────────────

it('laisse une personne vérifiée soutenir une avancée, une seule fois, et compte les soutiens', function () {
    $p = avancee_projet($this->yao, $this->projet);

    app(Supports::class)->support($this->dehors, $p);
    app(Supports::class)->support($this->dehors, $p);
    app(Supports::class)->support($this->membre, $p);
    app(Supports::class)->support($this->autre, $p);

    expect(PublicationSupport::count())->toBe(3)->and($p->supports()->count())->toBe(3);
});

it('refuse le soutien aux gestionnaires, aux personnes non vérifiées, aux visiteurs, et sur autre chose qu’une avancée', function () {
    $p = avancee_projet($this->yao, $this->projet);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        expect(fn () => app(Supports::class)->support($gestionnaire, $p))->toThrow(AuthorizationException::class, 'propre avancée');
    }
    $non = User::factory()->unverified()->create();
    expect(fn () => app(Supports::class)->support($non, $p))->toThrow(AuthorizationException::class, 'Vérifiez votre adresse e-mail');
    expect(Gate::forUser(null)->allows('support', $p))->toBeFalse();

    foreach ([avancee_projet($this->yao, $this->projet, []), besoin_projet($this->yao, $this->projet), publication($this->yao)] as $autre) {
        if ($autre->type === PublicationType::ProjectUpdate) {
            continue;
        }
        expect(fn () => app(Supports::class)->support($this->dehors, $autre))->toThrow(AuthorizationException::class);
    }
    $realisation = app(Publications::class)->publish($this->yao, PublicationType::Achievement, ['body' => 'Réalisation'], project: $this->projet);
    expect(fn () => app(Supports::class)->support($this->dehors, $realisation))->toThrow(AuthorizationException::class);

    expect(PublicationSupport::count())->toBe(0);
});

it('ne laisse soutenir que ce qu’on a le droit de voir : « page introuvable » sinon', function () {
    $reservee = avancee_projet($this->yao, $this->projet, ['visibility' => 'zumra']);
    $inscrits = avancee_projet($this->yao, $this->projet, ['visibility' => 'members']);

    expect(fn () => app(Supports::class)->support($this->dehors, $reservee))->toThrow(AuthorizationException::class);
    app(Supports::class)->support($this->membre, $reservee);
    app(Supports::class)->support($this->dehors, $inscrits);
    expect(Gate::forUser($this->dehors)->inspect('support', $reservee)->status())->toBe(404);

    expect(PublicationSupport::count())->toBe(2);
});

it('ne laisse plus soutenir une avancée masquée, mais laisse retirer son soutien', function () {
    $p = avancee_projet($this->yao, $this->projet);
    app(Supports::class)->support($this->dehors, $p);
    app(Moderation::class)->hide(User::factory()->moderator()->create(), $p, 'Motif de test');

    expect(fn () => app(Supports::class)->support($this->membre, $p->fresh()))->toThrow(AuthorizationException::class);

    app(Supports::class)->withdraw($this->dehors, $p->fresh());
    expect(PublicationSupport::count())->toBe(0);
});

it('retire son soutien, sans effet si on ne soutenait pas, et sans toucher celui des autres', function () {
    $p = avancee_projet($this->yao, $this->projet);
    app(Supports::class)->support($this->dehors, $p);
    app(Supports::class)->support($this->membre, $p);

    app(Supports::class)->withdraw($this->autre, $p);
    expect(PublicationSupport::count())->toBe(2);

    app(Supports::class)->withdraw($this->dehors, $p);
    expect(PublicationSupport::sole()->user_id)->toBe($this->membre->id);

    // On peut soutenir de nouveau après avoir retiré.
    app(Supports::class)->support($this->dehors, $p);
    expect(PublicationSupport::count())->toBe(2);
});

it('limite le nombre de soutiens par personne et par heure, et ne compte pas le soutien déjà donné', function () {
    config(['gamad.limits.supports_per_hour' => 2]);
    $a = avancee_projet($this->yao, $this->projet, ['body' => 'A']);
    $b = avancee_projet($this->yao, $this->projet, ['body' => 'B']);
    $c = avancee_projet($this->yao, $this->projet, ['body' => 'C']);

    app(Supports::class)->support($this->dehors, $a);
    app(Supports::class)->support($this->dehors, $a);
    app(Supports::class)->support($this->dehors, $b);
    expect(fn () => app(Supports::class)->support($this->dehors, $c))->toThrow(ValidationException::class, 'trop vite');
    app(Supports::class)->support($this->membre, $c);

    $this->travel(61)->minutes();
    app(Supports::class)->support($this->dehors, $c);
    expect(PublicationSupport::where('user_id', $this->dehors->id)->count())->toBe(3);
});

it('garantit en base un seul soutien par personne et par avancée, et supprime les soutiens avec la publication ou la personne', function () {
    $p = avancee_projet($this->yao, $this->projet);
    app(Supports::class)->support($this->dehors, $p);

    expect(fn () => DB::transaction(fn () => DB::table('publication_supports')->insert(['id' => (string) Str::uuid(), 'publication_id' => $p->id, 'user_id' => $this->dehors->id, 'created_at' => now()])))->toThrow(QueryException::class);

    app(Supports::class)->support($this->membre, $p);
    $this->dehors->delete();
    expect(PublicationSupport::count())->toBe(1);

    $p->delete();
    expect(PublicationSupport::count())->toBe(0);
});

it('ne prévient personne d’un soutien', function () {
    $p = avancee_projet($this->yao, $this->projet);

    app(Supports::class)->support($this->dehors, $p);

    expect(UserNotification::count())->toBe(0);
});

it('montre « Je soutiens » aux personnes qui peuvent soutenir, et l’état de leur soutien', function () {
    $p = avancee_projet($this->yao, $this->projet);

    monde_onglet($this->dehors, $this->projet, 'fil')->assertSeeText('Je soutiens')->assertDontSeeText('Retirer mon soutien');
    app(Supports::class)->support($this->dehors, $p);
    monde_onglet($this->dehors, $this->projet, 'fil')->assertSeeText('Vous soutenez cette avancée')->assertSeeText('Retirer mon soutien')->assertDontSeeText('Je soutiens');
    monde_onglet($this->membre, $this->projet, 'fil')->assertSeeText('Je soutiens');
});

it('ne montre pas « Je soutiens » au porteur ni aux personnes non vérifiées', function () {
    avancee_projet($this->yao, $this->projet);
    $non = User::factory()->unverified()->create();

    app(ProjectTeam::class)->enter($this->projet, $non, null);

    foreach ([$this->yao, $non] as $qui) {
        monde_onglet($qui, $this->projet, 'fil')->assertDontSeeText('Je soutiens')->assertDontSeeText('Retirer mon soutien');
    }
});

it('ne montre « Je soutiens » que sur une avancée, pas sur une réalisation ni un besoin', function () {
    app(Publications::class)->publish($this->yao, PublicationType::Achievement, ['body' => 'Réalisation faite'], project: $this->projet);

    monde_onglet($this->dehors, $this->projet, 'fil')->assertSeeText('Réalisation faite')->assertDontSeeText('Je soutiens')->assertDontSeeText('Se connecter pour soutenir');
    besoin_projet($this->yao, $this->projet);
    monde_onglet($this->dehors, $this->projet, 'besoins')->assertDontSeeText('Je soutiens');
});

it('affiche le nombre réel de soutiens, au singulier et au pluriel, sans nommer personne', function () {
    $p = avancee_projet($this->yao, $this->projet);
    monde_onglet($this->dehors, $this->projet, 'fil')->assertDontSeeText('personne soutient')->assertDontSeeText('personnes soutiennent');

    app(Supports::class)->support($this->dehors, $p);
    monde_onglet($this->autre, $this->projet, 'fil')->assertSeeText('1 personne soutient')->assertDontSeeText('Personne Extérieure');

    app(Supports::class)->support($this->membre, $p);
    app(Supports::class)->support($this->autre, $p);
    monde_onglet($this->yao, $this->projet, 'fil')->assertSeeText('3 personnes soutiennent')->assertDontSeeText('Simple Membre');
});

it('soutient et retire depuis l’écran, par la feuille d’actions, pour une personne connectée seulement', function () {
    $p = avancee_projet($this->yao, $this->projet);

    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->call('support', $p->id);
    expect(PublicationSupport::sole()->user_id)->toBe($this->dehors->id);
    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->call('unsupport', $p->id);
    expect(PublicationSupport::count())->toBe(0);

    Livewire::actingAs($this->yao)->test(GestureSheet::class)->call('support', $p->id)->assertForbidden();
    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->call('support', 'pas-un-uuid')->assertNotFound();
    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->call('support', (string) Str::uuid())->assertNotFound();
    expect(PublicationSupport::count())->toBe(0);
});

it('rafraîchit le Fil du projet après un soutien', function () {
    $p = avancee_projet($this->yao, $this->projet);

    monde_onglet($this->dehors, $this->projet, 'fil')->assertSeeText('Je soutiens');
    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->call('support', $p->id)->assertDispatched('gesture-changed');
    monde_onglet($this->dehors, $this->projet, 'fil')->dispatch('gesture-changed')->assertSeeText('Vous soutenez cette avancée')->assertSeeText('1 personne soutient');
});

it('montre le soutien dans le Fil global, avec le compte, et sur la page de la publication', function () {
    $p = avancee_projet($this->yao, $this->projet, ['body' => 'Avancée à soutenir']);
    app(Supports::class)->support($this->membre, $p);

    Livewire::actingAs($this->dehors)->test(Fil::class)->assertSeeText('Avancée à soutenir')->assertSeeText('Je soutiens')->assertSeeText('1 personne soutient');
    Livewire::actingAs($this->membre)->test(Fil::class)->assertSeeText('Vous soutenez cette avancée');
    Livewire::actingAs($this->dehors)->test(PagePublication::class, ['publication' => $p])->assertSeeText('Je soutiens')->assertSeeText('1 personne soutient');
});

it('montre aux gestionnaires le nombre de soutiens de leur avancée, et « personne n’a encore soutenu » sinon', function () {
    $p = avancee_projet($this->yao, $this->projet);

    Livewire::actingAs($this->resp)->test(PagePublication::class, ['publication' => $p])->assertSeeText('Personne n’a encore soutenu cette avancée.')->assertDontSeeText('Je soutiens');
    app(Supports::class)->support($this->dehors, $p);
    app(Supports::class)->support($this->membre, $p);
    Livewire::actingAs($this->resp)->test(PagePublication::class, ['publication' => $p])->assertSeeText('2 personnes soutiennent');
});

it('ne fait pas une requête de plus par carte pour les soutiens', function () {
    foreach (range(1, 6) as $i) {
        $p = avancee_projet($this->yao, $this->projet, ['body' => "Avancée $i"]);
        app(Supports::class)->support($this->dehors, $p);
    }

    $c = Livewire::actingAs($this->dehors)->test(Fil::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $c->call('$refresh');
    $requetes = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (range(7, 12) as $i) {
        $p = avancee_projet($this->yao, $this->projet, ['body' => "Avancée $i"]);
        app(Supports::class)->support($this->dehors, $p);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $c->call('$refresh');
    $plus = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($plus)->toBeLessThanOrEqual($requetes + 1);
});
