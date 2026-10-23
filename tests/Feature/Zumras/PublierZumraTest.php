<?php

use App\Enums\NeedState;
use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\GestureSheet;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\JoinRequest;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Gestures;
use App\Services\Gratitudes;
use App\Services\Memberships;
use App\Services\Moderation;
use App\Services\Publications;
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

function auNomDe(User $qui, $zumra, PublicationType $type = PublicationType::Need, array $data = []): Publication
{
    return app(Publications::class)->publish($qui, $type, $data + ['body' => 'Une publication de la ZUMRA.'], $zumra);
}

// ─── Publier au nom d'une ZUMRA ──────────────────────────────────────────────

it('laisse le fondateur et les responsables publier au nom de la ZUMRA, les quatre types qui existent pour elle', function () {
    foreach ([$this->yao, $this->resp] as $qui) {
        foreach (PublicationType::forZumra() as $type) {
            $p = auNomDe($qui, $this->zumra, $type);

            expect($p->zumra_id)->toBe($this->zumra->id)->and($p->author_id)->toBe($qui->id)->and($p->type)->toBe($type);
        }
    }

    expect(Publication::count())->toBe(8);
});

it('refuse de publier au nom d’une ZUMRA à un membre ou à une personne extérieure', function () {
    foreach ([$this->membre, $this->dehors] as $qui) {
        expect(fn () => auNomDe($qui, $this->zumra))->toThrow(AuthorizationException::class);
    }

    expect(Publication::count())->toBe(0);
});

it('refuse « Je veux apprendre » au nom d’une ZUMRA, et l’Appel à rejoindre en son propre nom', function () {
    expect(fn () => auNomDe($this->yao, $this->zumra, PublicationType::LearningWish))->toThrow(ValidationException::class, 'n’existe pas pour une ZUMRA');
    expect(fn () => publication($this->yao, PublicationType::ZumraCall))->toThrow(ValidationException::class, 'au nom d’une ZUMRA');
    expect(Publication::count())->toBe(0);
});

it('n’admet le niveau « ZUMRA seulement » que pour une publication de ZUMRA', function () {
    expect(auNomDe($this->yao, $this->zumra, data: ['visibility' => 'zumra'])->visibility)->toBe('zumra');
    expect(fn () => publication($this->yao, data: ['visibility' => 'zumra']))->toThrow(ValidationException::class);
});

it('garantit en base qu’un Appel à rejoindre et le niveau ZUMRA n’existent que pour une ZUMRA', function () {
    foreach ([['type' => 'zumra_call', 'visibility' => 'public'], ['type' => 'need', 'visibility' => 'zumra']] as $mauvais) {
        expect(fn () => DB::transaction(fn () => DB::table('publications')->insert($mauvais + [
            'id' => (string) Str::uuid(), 'author_id' => $this->yao->id, 'body' => 'x',
            'need_state' => $mauvais['type'] === 'need' ? 'open' : null, 'created_at' => now(), 'updated_at' => now(),
        ])))->toThrow(QueryException::class);
    }
});

it('ouvre l’écran de publication d’une ZUMRA, avec ses trois niveaux de visibilité et ses valeurs reprises de la ZUMRA', function () {
    $this->actingAs($this->resp)->get(route('zumras.publier', [$this->zumra, 'savoir']))->assertOk()
        ->assertSeeText('Vous publiez au nom de Couture solidaire')->assertSeeText('Public')->assertSeeText('Inscrits seulement')->assertSeeText('ZUMRA seulement');

    Livewire::actingAs($this->yao)->test(PublierPublication::class, ['slug' => 'appel', 'zumra' => $this->zumra])
        ->assertSet('domain_id', $this->zumra->domain_id)->assertSeeText('Appel à rejoindre')
        ->set('body', 'Nous cherchons des couturières.')->set('visibility', 'zumra')->call('publish')->assertHasNoErrors();

    $p = Publication::sole();
    expect($p->type)->toBe(PublicationType::ZumraCall)->and($p->visibility)->toBe('zumra')->and($p->zumra_id)->toBe($this->zumra->id);
});

it('réserve l’écran aux gestionnaires, et ferme les types qui n’existent pas pour une ZUMRA', function () {
    foreach ([$this->membre, $this->dehors] as $qui) {
        $this->actingAs($qui)->get(route('zumras.publier', [$this->zumra, 'besoin']))->assertNotFound();
    }
    $this->actingAs($this->yao)->get(route('zumras.publier', [$this->zumra, 'apprendre']))->assertNotFound();
    $this->actingAs($this->yao)->get(route('zumras.publier', [$this->zumra, 'n-importe-quoi']))->assertNotFound();
    $this->actingAs($this->yao)->get('/agir/appel')->assertNotFound();
});

it('renvoie un visiteur sans compte vers la connexion', function () {
    $this->get(route('zumras.publier', [$this->zumra, 'besoin']))->assertRedirect(route('login'));
});

it('verrouille la ZUMRA de l’écran de publication et revérifie le droit à la publication', function () {
    $autre = zumra(User::factory()->create(), 'Autre ZUMRA');

    $c = Livewire::actingAs($this->yao)->test(PublierPublication::class, ['slug' => 'besoin', 'zumra' => $this->zumra]);
    expect(fn () => $c->set('zumraId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // Une personne devenue simple membre ne publie plus, même avec l'écran déjà ouvert.
    $c->set('body', 'Un besoin');
    app(Memberships::class)->demote($this->yao, $this->zumra, ligne($this->zumra, $this->resp));
    Livewire::actingAs($this->resp)->test(PublierPublication::class, ['slug' => 'besoin', 'zumra' => $this->zumra])->assertNotFound();
});

it('donne un lien « Publier au nom de la ZUMRA » aux gestionnaires dans le Fil de la ZUMRA, et aux seuls gestionnaires', function () {
    $html = monde($this->yao, $this->zumra, 'fil')->assertSeeText('Publier au nom de la ZUMRA')->html();
    foreach (PublicationType::forZumra() as $type) {
        expect($html)->toContain(route('zumras.publier', [$this->zumra, $type->slug()]));
    }

    monde($this->membre, $this->zumra, 'fil')->assertDontSeeText('Publier au nom de la ZUMRA');
    monde($this->dehors, $this->zumra, 'fil')->assertDontSeeText('Publier au nom de la ZUMRA');
});

it('invite agir à passer par la ZUMRA quand on en gère une', function () {
    $this->actingAs($this->yao)->get('/agir')->assertSeeText('Pour publier au nom d’une ZUMRA que vous gérez')->assertDontSeeText('Appel à rejoindre');
    $this->actingAs($this->dehors)->get('/agir')->assertDontSeeText('au nom d’une ZUMRA');
});

// ─── Visibilité « ZUMRA seulement » ──────────────────────────────────────────

it('ne montre une publication « ZUMRA seulement » qu’aux membres de la ZUMRA', function () {
    $p = auNomDe($this->yao, $this->zumra, data: ['body' => 'Réservé aux membres de la ZUMRA', 'visibility' => 'zumra']);

    foreach ([$this->yao, $this->resp, $this->membre] as $membre) {
        $this->actingAs($membre)->get(route('publications.show', $p))->assertOk()->assertSeeText('Réservé aux membres de la ZUMRA');
        Livewire::actingAs($membre)->test(Fil::class)->assertSeeText('Réservé aux membres de la ZUMRA');
    }

    foreach ([$this->dehors, User::factory()->unverified()->create()] as $autre) {
        $this->actingAs($autre)->get(route('publications.show', $p))->assertNotFound();
        Livewire::actingAs($autre)->test(Fil::class)->assertDontSeeText('Réservé aux membres de la ZUMRA');
    }

    app('auth')->forgetGuards();
    $this->get(route('publications.show', $p))->assertNotFound();
});

it('applique la même règle côté requête et côté politique, pour chaque spectateur et chaque niveau', function () {
    $niveaux = [
        auNomDe($this->yao, $this->zumra, data: ['visibility' => 'public']),
        auNomDe($this->yao, $this->zumra, data: ['visibility' => 'members']),
        auNomDe($this->yao, $this->zumra, data: ['visibility' => 'zumra']),
    ];
    $ancien = person('Ancien Responsable', []);
    ajouteMembre($this->zumra, $ancien, ZumraRole::Manager);
    $reserve = auNomDe($ancien, $this->zumra, data: ['visibility' => 'zumra']);
    app(Memberships::class)->remove($this->yao, $this->zumra, ligne($this->zumra, $ancien));

    $spectateurs = [null, $this->yao, $this->resp, $this->membre, $this->dehors, $ancien, User::factory()->unverified()->create(), User::factory()->moderator()->create()];

    foreach ($spectateurs as $spectateur) {
        foreach ([...$niveaux, $reserve] as $p) {
            expect(Publication::query()->visibleTo($spectateur)->whereKey($p->id)->exists())
                ->toBe(Gate::forUser($spectateur)->allows('view', $p->fresh()));
        }
    }

    // La personne qui a écrit une publication de niveau ZUMRA et n'est plus dans la ZUMRA ne la lit plus.
    expect(Gate::forUser($ancien)->allows('view', $reserve->fresh()))->toBeFalse();
});

// ─── Le Fil global ───────────────────────────────────────────────────────────

it('montre le nom de la ZUMRA sur sa publication, avec un lien vers son Monde, et « par » la personne qui l’a écrite', function () {
    $p = auNomDe($this->resp, $this->zumra, data: ['body' => 'Besoin de la ZUMRA']);

    $html = Livewire::actingAs($this->dehors)->test(Fil::class)->assertSeeText('Couture solidaire')->assertSeeText('par Responsable Un')->assertSeeText('Besoin de la ZUMRA')->html();

    expect($html)->toContain(route('zumras.show', $this->zumra));
});

it('filtre le Fil sur « ZUMRA » : seulement les publications faites au nom d’une ZUMRA', function () {
    auNomDe($this->yao, $this->zumra, data: ['body' => 'Texte de la ZUMRA']);
    publication($this->yao, data: ['body' => 'Texte personnel']);

    Livewire::actingAs($this->dehors)->test(Fil::class)->assertSeeText('Texte de la ZUMRA')->assertSeeText('Texte personnel')
        ->call('setFilter', 'zumra')->assertSeeText('Texte de la ZUMRA')->assertDontSeeText('Texte personnel')
        ->call('setFilter', 'tout')->assertSeeText('Texte personnel');
});

it('supprime les publications d’une ZUMRA avec elle', function () {
    auNomDe($this->yao, $this->zumra);

    $this->zumra->delete();

    expect(Publication::count())->toBe(0);
});

// ─── Appel à rejoindre : une demande d'adhésion, pas un geste (D-116) ─────────

it('montre « Je veux rejoindre » sur un Appel à rejoindre, qui mène à la demande d’adhésion du Monde', function () {
    $p = auNomDe($this->yao, $this->zumra, PublicationType::ZumraCall, ['body' => 'Rejoignez-nous !']);

    $html = Livewire::actingAs($this->dehors)->test(Fil::class)->assertSeeText('Appel à rejoindre')->assertSeeText('Je veux rejoindre')->html();
    expect($html)->toContain(route('zumras.show', $this->zumra).'#rejoindre');

    Livewire::actingAs($this->membre)->test(Fil::class)->assertSeeText('Vous êtes dans cette ZUMRA')->assertDontSeeText('Je veux rejoindre');

    // Le bouton mène à la demande d'adhésion (D-110) : un mot, l'accord d'un responsable.
    monde($this->dehors, $this->zumra, 'fil')->set('joinWord', 'Je réponds à votre appel.')->call('requestJoin')->assertSeeText('Demande envoyée');
    expect(JoinRequest::sole()->message)->toBe('Je réponds à votre appel.');
});

it('n’admet aucun geste sur un Appel à rejoindre', function () {
    $p = auNomDe($this->yao, $this->zumra, PublicationType::ZumraCall);

    expect($p->acceptsGestures())->toBeFalse();
    expect(fn () => geste($this->dehors, $p))->toThrow(AuthorizationException::class);
    Livewire::actingAs($this->dehors)->test(GestureSheet::class)->dispatch('open-gesture', id: $p->id)->assertForbidden();
    expect(Gesture::count())->toBe(0);
});

// ─── Besoins de ZUMRA : gestes, acceptation, état, remerciement ───────────────

it('laisse un membre ou une personne extérieure répondre à un besoin de ZUMRA, jamais ses gestionnaires', function () {
    $besoin = auNomDe($this->yao, $this->zumra, data: ['body' => 'Besoin de la ZUMRA']);

    geste($this->dehors, $besoin, 'Je peux aider.');
    geste($this->membre, $besoin);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        expect(fn () => geste($gestionnaire, $besoin))->toThrow(AuthorizationException::class);
    }
    expect(Gesture::count())->toBe(2);
});

it('prévient le fondateur et tous les responsables d’une réponse, et pas la personne qui répond', function () {
    $besoin = auNomDe($this->yao, $this->zumra);

    geste($this->dehors, $besoin);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        $n = UserNotification::query()->where('user_id', $gestionnaire->id)->where('kind', NotificationKind::GestureReceived)->sole();
        expect($n->actor_id)->toBe($this->dehors->id)->and($n->publication_id)->toBe($besoin->id);
    }
    expect(UserNotification::query()->where('user_id', $this->dehors->id)->count())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $this->membre->id)->count())->toBe(0);
});

it('montre les réponses à tout gestionnaire, qui peut accepter ou décliner, pas à un membre', function () {
    $besoin = auNomDe($this->yao, $this->zumra, data: ['body' => 'Besoin de la ZUMRA']);
    $g = geste($this->dehors, $besoin, 'Je suis libre samedi.');

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        Livewire::actingAs($gestionnaire)->test(PagePublication::class, ['publication' => $besoin])
            ->assertSeeText('Propositions reçues')->assertSeeText('Personne Extérieure')->assertSeeText('Je suis libre samedi.')->assertSeeText('Accepter');
    }

    Livewire::actingAs($this->membre)->test(PagePublication::class, ['publication' => $besoin])->assertDontSeeText('Propositions reçues')->assertDontSeeText('Je suis libre samedi.');
    Livewire::actingAs($this->membre)->test(PagePublication::class, ['publication' => $besoin])->call('accept', $g->id)->assertForbidden();
    expect($g->fresh()->state->value)->toBe('sent');
});

it('ouvre la conversation entre la personne qui a répondu et le responsable qui a accepté (D-116)', function () {
    $besoin = auNomDe($this->yao, $this->zumra);
    $g = geste($this->dehors, $besoin);

    Livewire::actingAs($this->resp)->test(PagePublication::class, ['publication' => $besoin])->call('accept', $g->id)->assertHasNoErrors();

    $c = Conversation::sole();
    expect($c->owner_id)->toBe($this->resp->id)->and($c->helper_id)->toBe($this->dehors->id)->and($besoin->fresh()->need_state)->toBe(NeedState::InProgress);

    $n = UserNotification::query()->where('user_id', $this->dehors->id)->where('kind', NotificationKind::GestureAccepted)->sole();
    expect($n->actor_id)->toBe($this->resp->id);

    // Le fondateur, qui n'a pas accepté, ne lit pas cette conversation.
    $this->actingAs($this->yao)->get(route('conversations.show', $c))->assertNotFound();
    $this->actingAs($this->resp)->get(route('conversations.show', $c))->assertOk();
});

it('laisse tout gestionnaire résoudre le besoin et remercier, le remerciement portant le nom de celui qui l’écrit', function () {
    $besoin = auNomDe($this->yao, $this->zumra);
    $g = geste($this->dehors, $besoin);
    app(Gestures::class)->accept($this->yao, $g);

    foreach ([$this->membre, $this->dehors] as $autre) {
        expect(fn () => app(Publications::class)->resolve($autre, $besoin->fresh()))->toThrow(AuthorizationException::class);
        expect(fn () => app(Gratitudes::class)->thank($autre, $besoin->fresh(), $this->dehors->id, 'Merci'))->toThrow(AuthorizationException::class);
    }

    $gratitude = app(Gratitudes::class)->resolveAndThank($this->resp, $besoin->fresh(), $this->dehors->id, 'Merci pour votre aide.');

    expect($besoin->fresh()->need_state)->toBe(NeedState::Resolved)->and($gratitude->author_id)->toBe($this->resp->id)->and($gratitude->helper_id)->toBe($this->dehors->id);
});

it('montre dans « À faire maintenant » de chaque gestionnaire les réponses à traiter et les remerciements, pas à un membre', function () {
    $besoin = auNomDe($this->yao, $this->zumra, data: ['body' => 'Besoin de la ZUMRA']);
    geste($this->dehors, $besoin);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        Livewire::actingAs($gestionnaire)->test(Espace::class)->assertSeeText('1 réponse à traiter')->assertSeeText('Besoin de la ZUMRA');
    }
    Livewire::actingAs($this->membre)->test(Espace::class)->assertDontSeeText('réponse à traiter');

    app(Gestures::class)->accept($this->yao, Gesture::sole());
    app(Publications::class)->resolve($this->resp, $besoin->fresh());

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        Livewire::actingAs($gestionnaire)->test(Espace::class)->assertSeeText('Remercier Personne Extérieure ?');
    }
});

it('laisse les gestionnaires voir et expliquer une publication de ZUMRA masquée, pas un simple membre', function () {
    $p = auNomDe($this->yao, $this->zumra, data: ['body' => 'Publication de la ZUMRA', 'visibility' => 'zumra']);
    app(Moderation::class)->hide(User::factory()->moderator()->create(), $p, 'Motif de test');

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        $this->actingAs($gestionnaire)->get(route('publications.show', $p))->assertOk()->assertSeeText('Cette publication est masquée');
    }
    $this->actingAs($this->membre)->get(route('publications.show', $p))->assertNotFound();

    app(Moderation::class)->explain($this->resp, $p->fresh(), 'Nous corrigeons.');
    expect($p->fresh()->author_explanation)->toBe('Nous corrigeons.');
    expect(fn () => app(Moderation::class)->explain($this->membre, $p->fresh(), 'Intrus'))->toThrow(AuthorizationException::class);
});

it('garde l’engagement visible et réel sur une publication de ZUMRA : « personnes proposent leur aide »', function () {
    $besoin = auNomDe($this->yao, $this->zumra);
    geste($this->dehors, $besoin);
    geste($this->membre, $besoin);

    Livewire::actingAs($this->resp)->test(Fil::class)->assertSeeText('2 personnes proposent leur aide')->assertSeeText('Voir les réponses');
});
