<?php

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Enums\ZumraRole;
use App\Livewire\Espace;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\JoinRequests;
use App\Services\Memberships;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->awa = person('Awa Traoré', ['Informatique']);
    $this->moussa = person('Moussa Koné', []);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
});

function demandes(): JoinRequests
{
    return app(JoinRequests::class);
}

// ─── Demander à rejoindre ────────────────────────────────────────────────────

it('enregistre une demande en attente avec un mot facultatif', function () {
    $d = demandes()->propose($this->awa, $this->zumra, 'Je peux animer un atelier de comptabilité.');

    expect($d->state)->toBe(JoinRequestState::Pending)->and($d->message)->toBe('Je peux animer un atelier de comptabilité.')
        ->and($d->user_id)->toBe($this->awa->id)->and($d->zumra_id)->toBe($this->zumra->id);
    expect(demandes()->propose($this->moussa, $this->zumra, '   ')->message)->toBeNull();
});

it('prévient le fondateur et les responsables, pas la personne qui demande', function () {
    demandes()->propose($this->awa, $this->zumra);

    foreach ([$this->yao, $this->resp] as $gestionnaire) {
        $n = UserNotification::query()->where('user_id', $gestionnaire->id)->sole();
        expect($n->kind)->toBe(NotificationKind::ZumraRequestReceived)->and($n->actor_id)->toBe($this->awa->id)->and($n->zumra_id)->toBe($this->zumra->id);
    }
    expect(UserNotification::query()->where('user_id', $this->awa->id)->count())->toBe(0);
});

it('accepte 280 caractères de mot et refuse 281', function () {
    expect(fn () => demandes()->propose($this->awa, $this->zumra, str_repeat('é', 281)))->toThrow(ValidationException::class);

    demandes()->propose($this->awa, $this->zumra, str_repeat('é', 280));
    expect(JoinRequest::count())->toBe(1);
});

it('demande à rejoindre par l’écran, qui passe à « Demande envoyée » avec « Retirer ma demande »', function () {
    monde($this->awa, $this->zumra)->assertSeeText('Je veux rejoindre')->assertSeeText('Un mot pour le fondateur')
        ->set('joinWord', 'Bonjour, je souhaite participer.')->call('requestJoin')->assertHasNoErrors()
        ->assertSeeText('Demande envoyée')->assertSeeText('Bonjour, je souhaite participer.')->assertSeeText('Retirer ma demande')->assertDontSeeText('Un mot pour le fondateur');

    expect(JoinRequest::sole()->message)->toBe('Bonjour, je souhaite participer.');
});

it('permet de retirer sa demande, puis de la refaire', function () {
    monde($this->awa, $this->zumra)->call('requestJoin')->call('withdrawRequest')->assertSeeText('Je veux rejoindre')->assertDontSeeText('Demande envoyée');

    expect(JoinRequest::sole()->state)->toBe(JoinRequestState::Withdrawn);

    monde($this->awa, $this->zumra)->set('joinWord', 'Je redemande.')->call('requestJoin')->assertSeeText('Demande envoyée');
    $d = JoinRequest::sole();
    expect($d->state)->toBe(JoinRequestState::Pending)->and($d->message)->toBe('Je redemande.');
});

it('n’admet qu’une demande en attente par personne et par ZUMRA', function () {
    demandes()->propose($this->awa, $this->zumra);

    expect(fn () => demandes()->propose($this->awa, $this->zumra))->toThrow(AuthorizationException::class);
    expect(JoinRequest::count())->toBe(1);
});

it('ne laisse pas refaire une demande déclinée, mais laisse refaire celle d’une personne qui a quitté la ZUMRA', function () {
    $d = demandes()->propose($this->awa, $this->zumra);
    demandes()->decline($this->yao, $d);
    expect(fn () => demandes()->propose($this->awa, $this->zumra))->toThrow(AuthorizationException::class);
    monde($this->awa, $this->zumra)->assertSeeText('Votre demande n’a pas été retenue.')->assertDontSeeText('Retirer ma demande');

    $membre = admis($this->zumra, $this->moussa);
    app(Memberships::class)->leave($this->moussa, $this->zumra);
    expect(JoinRequest::query()->where('user_id', $this->moussa->id)->sole()->state)->toBe(JoinRequestState::Accepted);

    demandes()->propose($this->moussa, $this->zumra);
    expect(JoinRequest::query()->where('user_id', $this->moussa->id)->sole()->state)->toBe(JoinRequestState::Pending);
});

it('refuse la demande d’un membre, d’une personne non vérifiée, et demande de se connecter à un visiteur', function () {
    expect(fn () => demandes()->propose($this->resp, $this->zumra))->toThrow(AuthorizationException::class)
        ->and(fn () => demandes()->propose($this->yao, $this->zumra))->toThrow(AuthorizationException::class)
        ->and(fn () => demandes()->propose(User::factory()->unverified()->create(), $this->zumra))->toThrow(AuthorizationException::class);

    monde(User::factory()->unverified()->create(), $this->zumra)->assertSeeText('Vérifiez votre adresse e-mail')->assertDontSeeText('Un mot pour le fondateur');

    app('auth')->forgetGuards();
    $this->get(route('zumras.show', [$this->zumra, 'onglet' => 'membres']))->assertSeeText('Connectez-vous pour demander à rejoindre')->assertSee(route('login'), false);
    expect(JoinRequest::count())->toBe(0);
});

it('limite le débit des demandes : 10 par jour et par personne (D-117)', function () {
    config(['gamad.limits.join_requests_per_day' => 2]);
    $z2 = zumra(User::factory()->create(), 'Deuxième');
    $z3 = zumra(User::factory()->create(), 'Troisième');

    demandes()->propose($this->awa, $this->zumra);
    demandes()->propose($this->awa, $z2);

    expect(fn () => demandes()->propose($this->awa, $z3))->toThrow(ValidationException::class, 'limite');

    demandes()->propose($this->moussa, $z3);
    expect(JoinRequest::count())->toBe(3);
});

// ─── Décider ─────────────────────────────────────────────────────────────────

it('montre les demandes au fondateur et aux responsables seulement, sans e-mail', function () {
    demandes()->propose($this->awa, $this->zumra, 'Je suis comptable.');

    foreach ([$this->yao, $this->resp] as $g) {
        $html = monde($g, $this->zumra)->assertSeeText('Demandes d’adhésion')->assertSeeText('Awa Traoré')->assertSeeText('Je suis comptable.')->assertSeeText('Informatique')->html();
        expect($html)->not->toContain($this->awa->email);
    }

    monde($this->moussa, $this->zumra)->assertDontSeeText('Demandes d’adhésion')->assertDontSeeText('Je suis comptable.');
    $membre = admis($this->zumra, $this->moussa);
    monde($this->moussa, $this->zumra)->assertDontSeeText('Demandes d’adhésion');
});

it('accepte une demande : la personne devient membre, la demande est close, elle en est informée', function () {
    $d = demandes()->propose($this->awa, $this->zumra);

    monde($this->resp, $this->zumra)->call('acceptRequest', $d->id)->assertHasNoErrors()->assertSeeText('Aucune demande en attente.');

    expect($this->zumra->roleOf($this->awa))->toBe(ZumraRole::Member)->and($d->fresh()->state)->toBe(JoinRequestState::Accepted)
        ->and($d->fresh()->decided_by)->toBe($this->resp->id);
    $n = UserNotification::query()->where('user_id', $this->awa->id)->sole();
    expect($n->kind)->toBe(NotificationKind::ZumraRequestAccepted)->and($n->actor_id)->toBe($this->resp->id);

    expect(monde($this->moussa, $this->zumra)->html())->toMatch('/>\s*3\s*<\/span>\s*<span[^>]*>\s*membres/');
});

it('décline une demande sans prévenir la personne, qui reste hors de la ZUMRA', function () {
    $d = demandes()->propose($this->awa, $this->zumra);

    monde($this->yao, $this->zumra)->call('declineRequest', $d->id)->assertHasNoErrors();

    expect($d->fresh()->state)->toBe(JoinRequestState::Declined)->and($this->zumra->roleOf($this->awa))->toBeNull()
        ->and(UserNotification::query()->where('user_id', $this->awa->id)->count())->toBe(0);
});

it('refuse de décider à qui ne gère pas la ZUMRA, ou d’une demande déjà traitée', function () {
    $d = demandes()->propose($this->awa, $this->zumra);
    $membre = admis($this->zumra, $this->moussa);

    foreach ([$this->moussa, $this->awa, User::factory()->create()] as $qui) {
        expect(fn () => demandes()->accept($qui, $d))->toThrow(AuthorizationException::class)
            ->and(fn () => demandes()->decline($qui, $d))->toThrow(AuthorizationException::class);
    }
    expect($d->fresh()->state)->toBe(JoinRequestState::Pending);

    demandes()->decline($this->yao, $d);
    expect(fn () => demandes()->accept($this->yao, $d->fresh()))->toThrow(ValidationException::class, 'déjà été traitée')
        ->and(fn () => demandes()->decline($this->yao, $d->fresh()))->toThrow(ValidationException::class);
});

it('ne laisse pas traiter la demande d’une autre ZUMRA en changeant l’identifiant', function () {
    $autre = zumra(User::factory()->create(), 'Autre ZUMRA');
    $d = demandes()->propose($this->awa, $autre);

    // Introuvable : jamais traitée, ni refusée avec un détail qui révélerait son existence.
    expect(fn () => monde($this->yao, $this->zumra)->call('acceptRequest', $d->id))->toThrow(ModelNotFoundException::class);
    monde($this->yao, $this->zumra)->call('declineRequest', 'pas-un-identifiant')->assertNotFound();

    expect($d->fresh()->state)->toBe(JoinRequestState::Pending);
});

it('ne montre pas, et ne laisse pas retirer, la demande d’une personne qui n’a pas demandé', function () {
    $d = demandes()->propose($this->awa, $this->zumra);

    expect(fn () => demandes()->withdraw($this->moussa, $d))->toThrow(NotFoundHttpException::class);
    expect($d->fresh()->state)->toBe(JoinRequestState::Pending);
});

it('ne laisse retirer qu’une demande en attente : une demande déclinée ou acceptée reste ce qu’elle est', function () {
    $declinee = demandes()->propose($this->awa, $this->zumra);
    demandes()->decline($this->yao, $declinee);

    expect(fn () => demandes()->withdraw($this->awa, $declinee->fresh()))->toThrow(ValidationException::class, 'plus être retirée');
    expect($declinee->fresh()->state)->toBe(JoinRequestState::Declined);

    // Une demande déclinée ne se contourne pas en la « retirant » pour la refaire.
    expect(fn () => demandes()->propose($this->awa, $this->zumra))->toThrow(AuthorizationException::class);

    $acceptee = demandes()->propose($this->moussa, $this->zumra);
    demandes()->accept($this->yao, $acceptee);
    expect(fn () => demandes()->withdraw($this->moussa, $acceptee->fresh()))->toThrow(ValidationException::class)
        ->and($acceptee->fresh()->state)->toBe(JoinRequestState::Accepted);
});

it('masque la demande d’une personne dont l’adresse n’est plus vérifiée', function () {
    demandes()->propose($this->awa, $this->zumra);
    $this->awa->forceFill(['email_verified_at' => null])->save();

    monde($this->yao, $this->zumra)->assertDontSeeText('Awa Traoré')->assertSeeText('Aucune demande en attente.');
    Livewire::actingAs($this->yao)->test(Espace::class)->assertDontSeeText('demande d’adhésion');
});

it('affiche dans « À faire maintenant » les demandes à traiter, par ZUMRA, aux seuls gestionnaires', function () {
    demandes()->propose($this->awa, $this->zumra);
    demandes()->propose($this->moussa, $this->zumra);

    foreach ([$this->yao, $this->resp] as $g) {
        Livewire::actingAs($g)->test(Espace::class)->assertSeeText('2 demandes d’adhésion à traiter')->assertSeeText('Dans la ZUMRA « Couture solidaire »')->assertSee(route('zumras.show', $this->zumra), false);
    }
    Livewire::actingAs($this->awa)->test(Espace::class)->assertDontSeeText('demandes d’adhésion à traiter');
    // Un simple membre de la ZUMRA ne décide pas : aucune ligne pour lui.
    ajouteMembre($this->zumra, $this->membreSimple = person('Membre Simple', []));
    Livewire::actingAs($this->membreSimple)->test(Espace::class)->assertDontSeeText('d’adhésion à traiter');

    demandes()->accept($this->yao, JoinRequest::query()->where('user_id', $this->awa->id)->first());
    Livewire::actingAs($this->yao)->test(Espace::class)->assertSeeText('1 demande d’adhésion à traiter');
});

it('ouvre la notification d’une demande sur le Monde de la ZUMRA', function () {
    demandes()->propose($this->awa, $this->zumra);
    $n = UserNotification::query()->where('user_id', $this->yao->id)->sole();

    $this->actingAs($this->yao)->get(route('notifications.open', $n))->assertRedirect(route('zumras.show', $this->zumra));
    expect($n->fresh()->read_at)->not->toBeNull();

    $this->get(route('notifications'))->assertSeeText('Awa Traoré')->assertSeeText('demande à rejoindre « Couture solidaire »');
});
