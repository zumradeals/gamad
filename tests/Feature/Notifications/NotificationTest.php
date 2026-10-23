<?php

use App\Enums\NotificationKind;
use App\Livewire\NotificationBell;
use App\Livewire\PageNotifications;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Gestures;
use App\Services\Gratitudes;
use App\Services\Notifier;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = person('Yao Kouassi', ['Couture']);
    $this->aidant = person('Awa Traoré', ['Informatique']);
    $this->besoin = publication($this->auteur, data: ['body' => 'Logiciel de caisse pour ma boutique']);
});

function notifs(User $qui)
{
    return UserNotification::query()->where('user_id', $qui->id)->orderBy('created_at')->get();
}

// ─── Création ────────────────────────────────────────────────────────────────

it('prévient l’auteur quand quelqu’un répond à sa publication', function () {
    geste($this->aidant, $this->besoin);

    $n = notifs($this->auteur)->sole();
    expect($n->kind)->toBe(NotificationKind::GestureReceived)->and($n->actor_id)->toBe($this->aidant->id)
        ->and($n->publication_id)->toBe($this->besoin->id)->and($n->read_at)->toBeNull();
});

it('prévient la personne dont la proposition est acceptée, avec un lien vers la conversation', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    $n = notifs($this->aidant)->sole();
    expect($n->kind)->toBe(NotificationKind::GestureAccepted)->and($n->actor_id)->toBe($this->auteur->id)->and($n->conversation_id)->toBe($conversation->id);
});

it('ne prévient pas quand une proposition est déclinée ni retirée', function () {
    $geste = geste($this->aidant, $this->besoin);
    app(Gestures::class)->decline($this->auteur, $geste);

    expect(notifs($this->aidant))->toHaveCount(0);

    $autre = User::factory()->create();
    $retire = geste($autre, $this->besoin);
    app(Gestures::class)->withdraw($autre, $retire);
    expect(notifs($this->auteur)->where('actor_id', $autre->id))->toHaveCount(1); // seule l'arrivée du geste a été signalée
});

it('prévient l’autre personne d’un message, une seule fois tant que le premier n’est pas lu', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    ecrire($this->auteur, $conversation, 'Bonjour');
    ecrire($this->auteur, $conversation, 'Vous êtes là ?');
    ecrire($this->auteur, $conversation, 'Samedi 9 h 30 ?');

    expect(notifs($this->aidant)->where('kind', NotificationKind::MessageReceived))->toHaveCount(1);

    // Quand elle a lu, un nouveau message la prévient de nouveau.
    pageConversation($this->aidant, $conversation);
    ecrire($this->auteur, $conversation, 'Je suis là');
    expect(notifs($this->aidant)->where('kind', NotificationKind::MessageReceived))->toHaveCount(2);
});

it('ne prévient jamais quelqu’un de son propre message', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, 'Bonjour');

    expect(notifs($this->auteur)->where('kind', NotificationKind::MessageReceived))->toHaveCount(0);
});

it('prévient la personne remerciée', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);
    app(Gratitudes::class)->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci Awa');

    $n = notifs($this->aidant)->where('kind', NotificationKind::GratitudeReceived)->sole();
    expect($n->actor_id)->toBe($this->auteur->id);
});

// ─── La page et la cloche ────────────────────────────────────────────────────

it('liste mes notifications, la plus récente d’abord, avec le nom de la personne et une phrase', function () {
    geste($this->aidant, $this->besoin);
    geste(person('Seydou Bamba', ['Informatique']), $this->besoin);

    Livewire::actingAs($this->auteur)->test(PageNotifications::class)
        ->assertSeeTextInOrder(['Seydou Bamba', 'Awa Traoré'])->assertSeeText('a répondu à votre publication')->assertSeeText('Logiciel de caisse');
});

it('ne montre que mes notifications, jamais celles d’une autre personne', function () {
    geste($this->aidant, $this->besoin);

    Livewire::actingAs($this->aidant)->test(PageNotifications::class)->assertDontSeeText('Awa Traoré')->assertSeeText('Rien de nouveau');
});

it('affiche l’état vide', function () {
    Livewire::actingAs($this->auteur)->test(PageNotifications::class)->assertSeeText('Rien de nouveau.')->assertDontSeeText('Tout marquer lu');
});

it('compte les non lues dans la cloche, puis « Tout marquer lu » les marque toutes', function () {
    geste($this->aidant, $this->besoin);
    geste(User::factory()->create(), $this->besoin);

    Livewire::actingAs($this->auteur)->test(NotificationBell::class)->assertSee('2 non lues')->assertSeeText('2');

    Livewire::actingAs($this->auteur)->test(PageNotifications::class)->assertSeeText('Tout marquer lu')->call('markAllRead')->assertDontSeeText('Tout marquer lu');

    Livewire::actingAs($this->auteur)->test(NotificationBell::class)->assertDontSee('non lue');
    expect(notifs($this->auteur)->whereNull('read_at'))->toHaveCount(0);
});

it('ne marque comme lues que mes notifications', function () {
    geste($this->aidant, $this->besoin);
    conversationDe($this->auteur, $this->aidant, publication($this->auteur, data: ['body' => 'Autre']));

    Livewire::actingAs($this->aidant)->test(PageNotifications::class)->call('markAllRead');

    expect(notifs($this->auteur)->whereNull('read_at'))->not->toHaveCount(0);
});

it('ouvre une notification : elle est marquée lue, puis on va à ce qu’elle annonce', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, 'Bonjour');
    $message = notifs($this->aidant)->firstWhere('kind', NotificationKind::MessageReceived);
    $accepte = notifs($this->aidant)->firstWhere('kind', NotificationKind::GestureAccepted);

    $this->actingAs($this->aidant)->get(route('notifications.open', $message))->assertRedirect(route('conversations.show', $conversation));
    expect($message->fresh()->read_at)->not->toBeNull();

    $this->get(route('notifications.open', $accepte))->assertRedirect(route('conversations.show', $conversation));

    $recue = notifs($this->auteur)->firstWhere('kind', NotificationKind::GestureReceived);
    $this->actingAs($this->auteur)->get(route('notifications.open', $recue))->assertRedirect(route('publications.show', $this->besoin));
});

it('ouvre le remerciement sur mon profil', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);
    app(Gratitudes::class)->resolveAndThank($this->auteur, $this->besoin, $this->aidant->id, 'Merci');
    $n = notifs($this->aidant)->firstWhere('kind', NotificationKind::GratitudeReceived);

    $this->actingAs($this->aidant)->get(route('notifications.open', $n))->assertRedirect(route('personnes.show', $this->aidant->profile->public_id));
});

it('répond « introuvable » (404) à qui ouvre la notification d’une autre personne, sans la marquer lue', function () {
    geste($this->aidant, $this->besoin);
    $n = notifs($this->auteur)->sole();

    $this->actingAs($this->aidant)->get(route('notifications.open', $n))->assertNotFound();
    $this->actingAs(User::factory()->moderator()->create())->get(route('notifications.open', $n))->assertNotFound();

    expect($n->fresh()->read_at)->toBeNull();
});

it('supprime les notifications d’une publication avec elle : rien ne mène à une page disparue', function () {
    geste($this->aidant, $this->besoin);
    $n = notifs($this->auteur)->sole();
    $this->besoin->delete();

    expect(UserNotification::count())->toBe(0);
    $this->actingAs($this->auteur)->get(route('notifications.open', $n))->assertNotFound();
});

it('demande de se connecter à un visiteur sans compte', function () {
    $this->get(route('notifications'))->assertRedirect(route('login'));
});

it('n’affiche aucune adresse e-mail ni numéro de téléphone', function () {
    geste($this->aidant, $this->besoin);
    $conversation = conversationDe($this->auteur, $this->aidant, publication($this->auteur, data: ['body' => 'Autre']));
    ecrire($this->aidant, $conversation, 'Bonjour');

    $html = Livewire::actingAs($this->auteur)->test(PageNotifications::class)->html();

    expect($html)->not->toContain($this->aidant->email)->not->toContain('@');
});

it('affiche « Personne supprimée » quand la personne à l’origine de la notification a supprimé son compte', function () {
    geste($this->aidant, $this->besoin);
    $this->aidant->delete();

    Livewire::actingAs($this->auteur)->test(PageNotifications::class)->assertSeeText('Personne supprimée');
});

it('affiche la cloche dans la barre du haut, avec le nombre de non lues, et pas aux visiteurs', function () {
    geste($this->aidant, $this->besoin);

    $this->actingAs($this->auteur)->get(route('fil'))->assertOk()->assertSee('aria-label="Notifications', false)->assertSee(route('notifications'), false);

    app('auth')->forgetGuards();
    $this->get(route('personnes.index'))->assertOk()->assertDontSee('aria-label="Notifications', false);
});

it('ne notifie jamais une personne de son propre acte, même si le service est appelé ainsi', function () {
    app(Notifier::class)->push($this->auteur, NotificationKind::GestureReceived, $this->auteur, $this->besoin);

    expect(UserNotification::count())->toBe(0);
});
