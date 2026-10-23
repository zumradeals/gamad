<?php

use App\Enums\InvitationState;
use App\Enums\NotificationKind;
use App\Enums\ZumraRole;
use App\Livewire\Espace;
use App\Livewire\InviterPersonnes;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Invitations;
use App\Services\JoinRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->awa = person('Awa Traoré', ['Informatique', 'Tableur Excel'], city: 'Yamoussoukro');
    $this->moussa = person('Moussa Koné', ['Informatique']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->resp = person('Responsable Un', []);
    ajouteMembre($this->zumra, $this->resp, ZumraRole::Manager);
    $this->membre = person('Simple Membre', ['Informatique']);
    ajouteMembre($this->zumra, $this->membre);
});

function invitations(): Invitations
{
    return app(Invitations::class);
}

// ─── Inviter ─────────────────────────────────────────────────────────────────

it('invite une personne avec un mot facultatif : en attente, valable 14 jours, la personne est prévenue', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa, 'Nous cherchons votre compétence.');

    expect($i->state)->toBe(InvitationState::Pending)->and($i->message)->toBe('Nous cherchons votre compétence.')
        ->and($i->invited_by)->toBe($this->yao->id)->and($i->user_id)->toBe($this->awa->id)
        ->and($i->expires_at->between(now()->addDays(14)->subMinute(), now()->addDays(14)->addMinute()))->toBeTrue();

    $n = UserNotification::query()->where('user_id', $this->awa->id)->sole();
    expect($n->kind)->toBe(NotificationKind::ZumraInvitationReceived)->and($n->actor_id)->toBe($this->yao->id)->and($n->zumra_id)->toBe($this->zumra->id);
});

it('laisse un responsable inviter, et refuse aux membres et aux autres', function () {
    invitations()->invite($this->resp, $this->zumra, $this->awa);
    expect(Invitation::count())->toBe(1);

    foreach ([$this->membre, $this->moussa] as $qui) {
        expect(fn () => invitations()->invite($qui, $this->zumra, $this->awa))->toThrow(AuthorizationException::class);
    }
    expect(Invitation::count())->toBe(1);
});

it('n’invite que des personnes vérifiées dont le profil est visible pour soi', function () {
    $nonVerifie = User::factory()->unverified()->create();

    expect(fn () => invitations()->invite($this->yao, $this->zumra, $nonVerifie))->toThrow(ValidationException::class, 'ne peut pas être invitée');

    // Un profil « inscrits seulement » reste invitable par une personne vérifiée.
    $discret = person('Chantal Discrète', ['Couture'], visibility: 'members');
    invitations()->invite($this->yao, $this->zumra, $discret);
    expect(Invitation::count())->toBe(1);
});

it('refuse d’inviter un membre, une personne déjà invitée, ou qui a déjà demandé à rejoindre', function () {
    expect(fn () => invitations()->invite($this->yao, $this->zumra, $this->membre))->toThrow(ValidationException::class, 'déjà dans la ZUMRA');

    invitations()->invite($this->yao, $this->zumra, $this->awa);
    expect(fn () => invitations()->invite($this->resp, $this->zumra, $this->awa))->toThrow(ValidationException::class, 'déjà une invitation');

    app(JoinRequests::class)->propose($this->moussa, $this->zumra);
    expect(fn () => invitations()->invite($this->yao, $this->zumra, $this->moussa))->toThrow(ValidationException::class, 'acceptez sa demande');
    expect(Invitation::count())->toBe(1);
});

it('accepte 280 caractères de mot et refuse 281', function () {
    expect(fn () => invitations()->invite($this->yao, $this->zumra, $this->awa, str_repeat('é', 281)))->toThrow(ValidationException::class);

    invitations()->invite($this->yao, $this->zumra, $this->awa, str_repeat('é', 280));
    expect(Invitation::count())->toBe(1);
});

it('limite le débit des invitations : 30 par jour et par personne qui invite (D-117)', function () {
    config(['gamad.limits.invitations_per_day' => 2]);

    invitations()->invite($this->yao, $this->zumra, $this->awa);
    invitations()->invite($this->yao, $this->zumra, $this->moussa);

    expect(fn () => invitations()->invite($this->yao, $this->zumra, person('Troisième', [])))->toThrow(ValidationException::class, 'limite');
    // La limite est par personne qui invite.
    invitations()->invite($this->resp, $this->zumra, person('Quatrième', []));
    expect(Invitation::count())->toBe(3);
});

it('garantit en base une seule invitation en attente par personne et par ZUMRA', function () {
    invitations()->invite($this->yao, $this->zumra, $this->awa);

    expect(fn () => DB::transaction(fn () => DB::table('invitations')->insert([
        'id' => (string) Str::uuid(), 'zumra_id' => $this->zumra->id, 'user_id' => $this->awa->id, 'state' => 'pending',
        'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(UniqueConstraintViolationException::class);
});

// ─── Répondre ────────────────────────────────────────────────────────────────

it('montre l’invitation à la personne invitée sur le Monde, avec Accepter et Refuser', function () {
    invitations()->invite($this->yao, $this->zumra, $this->awa, 'Venez nous aider.');

    monde($this->awa, $this->zumra)->assertSeeText('Vous êtes invité à rejoindre cette ZUMRA')->assertSeeText('Yao Kouassi vous invite')->assertSeeText('Venez nous aider.')
        ->assertSeeText('Accepter')->assertSeeText('Refuser')->assertDontSeeText('Un mot pour le fondateur');
    monde($this->moussa, $this->zumra)->assertDontSeeText('Vous êtes invité');
});

it('accepte l’invitation : la personne devient membre, l’invitation est close, celui qui l’a envoyée en est informé', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);

    monde($this->awa, $this->zumra)->call('acceptInvitation', $i->id)->assertHasNoErrors()->assertSeeText('Membre');

    expect($this->zumra->roleOf($this->awa))->toBe(ZumraRole::Member)->and($i->fresh()->state)->toBe(InvitationState::Accepted);
    $n = UserNotification::query()->where('user_id', $this->yao->id)->where('kind', NotificationKind::ZumraInvitationAccepted)->sole();
    expect($n->actor_id)->toBe($this->awa->id);
});

it('clôt aussi la demande en attente de la personne qui accepte une invitation', function () {
    $d = app(JoinRequests::class)->propose($this->awa, $this->zumra);
    // L'invitation est arrivée avant la demande : on la crée directement pour ce cas limite.
    $i = new Invitation(['state' => InvitationState::Pending, 'expires_at' => now()->addDays(3)]);
    $i->zumra()->associate($this->zumra);
    $i->user()->associate($this->awa);
    $i->inviter()->associate($this->yao);
    $i->save();

    invitations()->accept($this->awa, $i);

    expect($d->fresh()->state->value)->toBe('accepted')->and(JoinRequest::query()->where('state', 'pending')->count())->toBe(0);
});

it('accepter une demande clôt les invitations en attente de la même personne', function () {
    $i = new Invitation(['state' => InvitationState::Pending, 'expires_at' => now()->addDays(3)]);
    $i->zumra()->associate($this->zumra);
    $i->user()->associate($this->awa);
    $i->inviter()->associate($this->yao);
    $i->save();
    $d = app(JoinRequests::class)->propose($this->awa, $this->zumra);

    app(JoinRequests::class)->accept($this->yao, $d);

    expect($i->fresh()->state)->toBe(InvitationState::Accepted);
});

it('refuse l’invitation : la personne reste hors de la ZUMRA et l’invitation est close', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);

    monde($this->awa, $this->zumra)->call('refuseInvitation', $i->id)->assertHasNoErrors()->assertDontSeeText('Vous êtes invité');

    expect($i->fresh()->state)->toBe(InvitationState::Refused)->and($this->zumra->roleOf($this->awa))->toBeNull();
    expect(fn () => invitations()->accept($this->awa, $i->fresh()))->toThrow(ValidationException::class, 'plus en attente');
});

it('ne laisse répondre qu’à la personne invitée', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);

    foreach ([$this->moussa, $this->yao, $this->resp] as $qui) {
        expect(fn () => invitations()->accept($qui, $i))->toThrow(NotFoundHttpException::class)
            ->and(fn () => invitations()->refuse($qui, $i))->toThrow(NotFoundHttpException::class);
    }
    expect($i->fresh()->state)->toBe(InvitationState::Pending);
});

it('expire après 14 jours : plus d’acceptation, et une nouvelle invitation est possible', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);

    Carbon::setTestNow(now()->addDays(15));

    expect($i->fresh()->isOpen())->toBeFalse();
    expect(fn () => invitations()->accept($this->awa, $i->fresh()))->toThrow(ValidationException::class, 'expiré');
    monde($this->awa, $this->zumra)->assertDontSeeText('Vous êtes invité');
    Livewire::actingAs($this->awa)->test(Espace::class)->assertDontSeeText('Invitation à rejoindre');

    // On peut réinviter : l'ancienne est close avant la nouvelle.
    $nouvelle = invitations()->invite($this->yao, $this->zumra, $this->awa);
    expect($i->fresh()->state)->toBe(InvitationState::Expired)->and($nouvelle->isOpen())->toBeTrue();

    Carbon::setTestNow();
});

it('est encore valable le 13e jour', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);
    Carbon::setTestNow(now()->addDays(13));

    invitations()->accept($this->awa, $i->fresh());
    expect($this->zumra->roleOf($this->awa))->toBe(ZumraRole::Member);

    Carbon::setTestNow();
});

it('laisse un responsable annuler une invitation en attente, pas un membre', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);

    expect(fn () => invitations()->cancel($this->membre, $i))->toThrow(AuthorizationException::class);

    monde($this->resp, $this->zumra)->assertSeeText('Invitations en attente')->assertSeeText('Awa Traoré')->call('cancelInvitation', $i->id)->assertSeeText('Aucune invitation en attente.');

    expect($i->fresh()->state)->toBe(InvitationState::Cancelled);
    expect(fn () => invitations()->accept($this->awa, $i->fresh()))->toThrow(ValidationException::class);
    expect(fn () => invitations()->cancel($this->yao, $i->fresh()))->toThrow(ValidationException::class);
});

it('affiche l’invitation dans « À faire maintenant » de la personne invitée, qui disparaît une fois traitée', function () {
    $i = invitations()->invite($this->yao, $this->zumra, $this->awa);

    Livewire::actingAs($this->awa)->test(Espace::class)->assertSeeText('Invitation à rejoindre « Couture solidaire »')->assertSeeText('De la part de Yao Kouassi')->assertSee(route('zumras.show', $this->zumra), false);
    Livewire::actingAs($this->yao)->test(Espace::class)->assertDontSeeText('Invitation à rejoindre');

    invitations()->refuse($this->awa, $i);
    Livewire::actingAs($this->awa)->test(Espace::class)->assertDontSeeText('Invitation à rejoindre');
});

// ─── L'écran « Inviter des personnes » ───────────────────────────────────────

it('réserve l’écran d’invitation au fondateur et aux responsables', function () {
    foreach ([$this->yao, $this->resp] as $g) {
        $this->actingAs($g)->get(route('zumras.invite', $this->zumra))->assertOk()->assertSeeText('Inviter des personnes');
    }
    foreach ([$this->membre, $this->moussa] as $autre) {
        $this->actingAs($autre)->get(route('zumras.invite', $this->zumra))->assertNotFound();
    }
});

it('demande de se connecter à un visiteur', function () {
    $this->get(route('zumras.invite', $this->zumra))->assertRedirect(route('login'));
});

it('cherche par capacité, sans tenir compte de la casse ni des accents, et écarte les membres', function () {
    Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])
        ->set('query', 'TABLEUR')->assertSeeText('Awa Traoré')->assertSeeText('Tableur Excel')->assertDontSeeText('Moussa Koné')
        ->set('query', 'informatique')->assertSeeText('Awa Traoré')->assertSeeText('Moussa Koné')->assertDontSeeText('Simple Membre')
        ->set('query', 'yamoussoukro')->assertSeeText('Personne ne correspond');
});

it('ne propose dans la liste que les personnes qui ont indiqué une capacité, sauf celle choisie depuis son profil', function () {
    $sansCapacite = person('Chantal Sans Capacité', []);

    Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])->assertDontSeeText('Chantal Sans Capacité')->assertSeeText('Awa Traoré');
});

it('range les personnes par ordre alphabétique et n’affiche jamais leur e-mail', function () {
    $html = Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])->assertSeeTextInOrder(['Awa Traoré', 'Moussa Koné'])->html();

    expect($html)->not->toContain($this->awa->email)->not->toContain($this->moussa->email);
});

it('invite par l’écran, avec un mot, et montre ensuite « Invitation envoyée »', function () {
    Livewire::actingAs($this->resp)->test(InviterPersonnes::class, ['zumra' => $this->zumra])
        ->call('startInvite', $this->awa->profile->id)->assertSeeText('Un mot pour Awa Traoré')
        ->set('inviteWord', 'Bienvenue chez nous.')->call('send')->assertHasNoErrors()->assertSeeText('Invitation envoyée à Awa Traoré.');

    $i = Invitation::sole();
    expect($i->message)->toBe('Bienvenue chez nous.')->and($i->invited_by)->toBe($this->resp->id);

    Livewire::actingAs($this->resp)->test(InviterPersonnes::class, ['zumra' => $this->zumra])->assertSeeText('Invitation envoyée')->assertSeeText('Moussa Koné');
});

it('affiche l’erreur du mot trop long et n’invite pas', function () {
    Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])
        ->call('startInvite', $this->awa->profile->id)->set('inviteWord', str_repeat('é', 281))->call('send')->assertHasErrors(['inviteWord']);

    expect(Invitation::count())->toBe(0);
});

it('montre la personne choisie depuis son profil, même sans capacité indiquée', function () {
    $sansCapacite = person('Chantal Sans Capacité', []);

    Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])
        ->set('person', $sansCapacite->profile->public_id)->assertSeeText('Chantal Sans Capacité');
});

it('ne montre pas, et n’invite pas, une personne dont l’adresse n’est pas vérifiée ou qui est déjà membre', function () {
    $nonVerifie = person('Fantôme Inconnu', ['Informatique']);
    $nonVerifie->forceFill(['email_verified_at' => null])->save();

    Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])
        ->assertDontSeeText('Fantôme Inconnu')->set('person', $this->membre->profile->public_id)->assertDontSeeText('Simple Membre')
        ->call('startInvite', $nonVerifie->profile->id)->set('inviteWord', 'x')->assertSeeText('Awa Traoré');

    expect(fn () => Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra])->call('startInvite', $nonVerifie->profile->id)->call('send'))
        ->toThrow(ModelNotFoundException::class);
    expect(Invitation::count())->toBe(0);
});

it('verrouille la ZUMRA de l’écran et revérifie le droit à chaque affichage', function () {
    $autre = zumra(User::factory()->create(), 'Autre ZUMRA');

    $c = Livewire::actingAs($this->yao)->test(InviterPersonnes::class, ['zumra' => $this->zumra]);
    expect(fn () => $c->set('zumraId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);

    $this->actingAs($this->membre);
    $page = new InviterPersonnes;
    $page->zumraId = $this->zumra->id;
    expect(fn () => $page->render())->toThrow(AuthorizationException::class);
});

// ─── Depuis le profil d'une personne ─────────────────────────────────────────

it('propose « Inviter dans ma ZUMRA » sur le profil d’une autre personne, au fondateur et aux responsables', function () {
    $url = route('personnes.show', $this->awa->profile->public_id);

    foreach ([$this->yao, $this->resp] as $g) {
        $this->actingAs($g)->get($url)->assertSeeText('Inviter dans ma ZUMRA')->assertSee(route('zumras.invite', [$this->zumra, 'personne' => $this->awa->profile->public_id]), false);
    }
});

it('ne propose pas « Inviter » sur son propre profil, ni pour un membre, ni aux simples membres, ni aux visiteurs', function () {
    $this->actingAs($this->yao)->get(route('personnes.show', $this->yao->profile->public_id))->assertDontSeeText('Inviter dans');
    $this->actingAs($this->yao)->get(route('personnes.show', $this->membre->profile->public_id))->assertDontSeeText('Inviter dans');
    $this->actingAs($this->membre)->get(route('personnes.show', $this->awa->profile->public_id))->assertDontSeeText('Inviter dans');
    $this->actingAs($this->moussa)->get(route('personnes.show', $this->awa->profile->public_id))->assertDontSeeText('Inviter dans');
    app('auth')->forgetGuards();
    $this->get(route('personnes.show', $this->awa->profile->public_id))->assertDontSeeText('Inviter dans');
});

it('ne propose pas « Inviter » à un fondateur dont l’adresse n’est plus vérifiée', function () {
    $this->yao->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($this->yao->fresh())->get(route('personnes.show', $this->awa->profile->public_id))->assertDontSeeText('Inviter dans');
});

it('nomme la ZUMRA quand on en gère plusieurs', function () {
    zumra($this->yao, 'Agri Bouaké');

    $this->actingAs($this->yao)->get(route('personnes.show', $this->awa->profile->public_id))
        ->assertSeeText('Inviter dans Couture solidaire')->assertSeeText('Inviter dans Agri Bouaké')->assertDontSeeText('Inviter dans ma ZUMRA');
});
