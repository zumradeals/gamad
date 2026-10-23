<?php

use App\Enums\GestureState;
use App\Enums\PublicationType;
use App\Livewire\Espace;
use App\Livewire\PageConversation;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\Message;
use App\Models\Publication;
use App\Models\User;
use App\Services\Conversations;
use App\Services\Gestures;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->aidant = User::factory()->create(['name' => 'Awa Traoré']);
    $this->besoin = publication($this->auteur, data: ['body' => 'Logiciel de caisse pour ma boutique']);
});

// ─── Ouverture ───────────────────────────────────────────────────────────────

it('ouvre une conversation privée quand un geste est accepté, entre l’auteur et la personne acceptée', function () {
    $geste = geste($this->aidant, $this->besoin);
    expect(Conversation::count())->toBe(0);

    app(Gestures::class)->accept($this->auteur, $geste);

    $conversation = Conversation::query()->sole();
    expect($conversation->owner_id)->toBe($this->auteur->id)
        ->and($conversation->helper_id)->toBe($this->aidant->id)
        ->and($conversation->publication_id)->toBe($this->besoin->id)
        ->and($conversation->gesture_id)->toBe($geste->id);
});

it('n’ouvre pas de conversation pour un geste décliné, retiré ou en attente', function () {
    $decline = geste(User::factory()->create(), $this->besoin);
    $retire = geste(User::factory()->create(), $this->besoin);
    geste($this->aidant, $this->besoin);

    app(Gestures::class)->decline($this->auteur, $decline);
    app(Gestures::class)->withdraw($retire->author, $retire);

    expect(Conversation::count())->toBe(0);
});

it('ne rouvre pas une conversation existante : une seule par geste accepté', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    // Une nouvelle ouverture du même geste (rejouée) retrouve la même conversation.
    app(Conversations::class)->open($conversation->gesture->fresh());

    expect(Conversation::count())->toBe(1);
});

it('ouvre une conversation par personne acceptée quand plusieurs propositions sont acceptées', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);
    conversationDe($this->auteur, User::factory()->create(), $this->besoin);

    expect(Conversation::count())->toBe(2);
});

it('ouvre aussi une conversation pour un savoir partagé dont l’apprenant est accepté', function () {
    $savoir = publication($this->auteur, PublicationType::Knowledge, ['body' => 'Je montre la comptabilité simple']);

    $conversation = conversationDe($this->auteur, $this->aidant, $savoir);

    expect($conversation->publication_id)->toBe($savoir->id);
});

// ─── Droits ──────────────────────────────────────────────────────────────────

it('laisse les deux personnes lire la conversation', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    foreach ([$this->auteur, $this->aidant] as $personne) {
        $this->actingAs($personne)->get(route('conversations.show', $conversation))->assertOk()->assertSee('placeholder="Écrire un message…"', false);
    }
});

it('répond « page introuvable » (404) à toute autre personne, même vérifiée ou modératrice', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    $curieux = User::factory()->create();

    $this->actingAs($curieux)->get(route('conversations.show', $conversation))->assertNotFound();
    $this->actingAs(User::factory()->moderator()->create())->get(route('conversations.show', $conversation))->assertNotFound();
});

it('demande de se connecter à un visiteur sans compte, et de vérifier son adresse à une personne non vérifiée', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    $this->get(route('conversations.show', $conversation))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())->get(route('conversations.show', $conversation))->assertRedirect();
});

it('refuse à un tiers de lire ou d’écrire par le service, même s’il connaît l’identifiant', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    $tiers = User::factory()->create();

    expect(fn () => ecrire($tiers, $conversation, 'Bonjour'))->toThrow(AuthorizationException::class)
        ->and(Message::count())->toBe(0);

    expect(fn () => app(Conversations::class)->markRead($tiers, $conversation))->toThrow(AuthorizationException::class);
});

it('verrouille l’identifiant de la conversation et revérifie le droit de voir à l’affichage', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    $autre = conversationDe(User::factory()->create(), User::factory()->create());

    expect(fn () => pageConversation($this->auteur, $conversation)->set('conversationId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // Même si l'identifiant était changé malgré le verrou, la page n'affiche rien à qui n'y a pas droit.
    $this->actingAs($this->auteur);
    $page = new PageConversation;
    $page->conversationId = $autre->id;

    try {
        $page->render(app(Conversations::class));
        $this->fail('Rendu autorisé pour un tiers');
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(404);
    }
});

// ─── Écrire ──────────────────────────────────────────────────────────────────

it('enregistre un message, dans l’ordre, avec l’heure d’Abidjan', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    pageConversation($this->auteur, $conversation)->set('body', 'Bonjour Awa, merci d’avoir proposé votre aide.')->call('send')->assertHasNoErrors()->assertSet('body', '');
    pageConversation($this->aidant, $conversation)->set('body', 'Bonjour Yao. Avec plaisir.')->call('send');

    $messages = $conversation->messages()->orderBy('created_at')->orderBy('id')->get();

    expect($messages->pluck('body')->all())->toBe(['Bonjour Awa, merci d’avoir proposé votre aide.', 'Bonjour Yao. Avec plaisir.'])
        ->and($messages[0]->sender_id)->toBe($this->auteur->id);

    pageConversation($this->auteur, $conversation)->assertSeeTextInOrder(['Bonjour Awa', 'Bonjour Yao']);
});

it('refuse un message vide ou fait d’espaces', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    foreach (['', '   ', "\n\n"] as $vide) {
        pageConversation($this->auteur, $conversation)->set('body', $vide)->call('send')->assertHasErrors(['body']);
    }

    expect(Message::count())->toBe(0);
});

it('accepte 1 000 caractères et refuse 1 001 (D-086)', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    pageConversation($this->auteur, $conversation)->set('body', str_repeat('é', 1000))->call('send')->assertHasNoErrors();
    pageConversation($this->auteur, $conversation)->set('body', str_repeat('é', 1001))->call('send')->assertHasErrors(['body']);

    expect(Message::count())->toBe(1);
});

it('affiche un message comme du texte brut : jamais de HTML, de lien cliquable ni d’image', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, '<script>alert(1)</script> <img src=x onerror=alert(2)> https://exemple.org/piege');

    $html = pageConversation($this->aidant, $conversation)->html();

    expect($html)->not->toContain('<script>alert(1)')->not->toContain('<img src=x')->not->toContain('href="https://exemple.org')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('limite le débit des messages : par minute et par jour (D-086)', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    config(['gamad.limits.messages_per_minute' => 2, 'gamad.limits.messages_per_day' => 3]);
    ecrire($this->auteur, $conversation, 'un');
    ecrire($this->auteur, $conversation, 'deux');

    expect(fn () => ecrire($this->auteur, $conversation, 'trois'))->toThrow(ValidationException::class, 'trop vite');
    expect(Message::count())->toBe(2);

    // La limite est par personne : l'autre participant n'est pas gêné.
    ecrire($this->aidant, $conversation, 'réponse');
    expect(Message::count())->toBe(3);
});

it('ne montre jamais l’e-mail ni le téléphone de l’autre personne, et rappelle qu’ils restent privés', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    $html = pageConversation($this->auteur, $conversation)->html();

    expect($html)->not->toContain($this->aidant->email)->and($html)->toContain('Votre e-mail et votre téléphone restent privés');
});

it('rafraîchit la conversation toutes les 10 secondes, sans WebSocket', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    $html = pageConversation($this->auteur, $conversation)->html();

    expect($html)->toContain('wire:poll.10s');
});

it('montre à l’autre personne le message arrivé entre deux rafraîchissements', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);

    $page = pageConversation($this->aidant, $conversation);
    $page->assertDontSeeText('Nouveau message');

    ecrire($this->auteur, $conversation, 'Nouveau message de Yao');

    $page->call('$refresh')->assertSeeText('Nouveau message de Yao');
});

// ─── Lu / non lu ─────────────────────────────────────────────────────────────

it('compte comme non lu ce que l’autre a écrit, jamais ce que j’ai écrit moi-même', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, 'Bonjour');

    expect(Conversation::unreadFor($this->aidant)->count())->toBe(1)
        ->and(Conversation::unreadFor($this->auteur)->count())->toBe(0);
});

it('marque la conversation lue dès qu’on l’ouvre, et seulement pour soi', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, 'Bonjour');

    pageConversation($this->aidant, $conversation);

    expect(Conversation::unreadFor($this->aidant)->count())->toBe(0);

    ecrire($this->auteur, $conversation, 'Un autre message');
    expect(Conversation::unreadFor($this->aidant)->count())->toBe(1);
});

// ─── Suppression d'un compte (D-097) ─────────────────────────────────────────

it('efface les messages d’une personne qui supprime son compte et garde ceux de l’autre lisibles (D-097)', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, 'Message de Yao, secret');
    ecrire($this->aidant, $conversation, 'Message d’Awa, à garder');

    Livewire::actingAs($this->auteur)->test(Espace::class)->set('password', 'password')->call('deleteAccount');

    // Les messages de la personne supprimée sont effacés en base, pas seulement cachés.
    expect(Message::query()->whereNull('body')->count())->toBe(1)
        ->and(Message::query()->where('body', 'like', '%secret%')->exists())->toBeFalse();

    // Il ne reste que la conversation, relisible par Awa.
    $this->flushSession();
    app('auth')->forgetGuards();
    $html = pageConversation($this->aidant, $conversation->fresh())->html();

    expect($html)->toContain('Personne supprimée')->toContain('Message supprimé')->toContain('Message d’Awa, à garder')
        ->and($html)->not->toContain('Message de Yao')->not->toContain('Yao Kouassi');
});

it('ne permet plus d’écrire à une personne qui a supprimé son compte', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    $this->aidant->delete();

    $conversation = $conversation->fresh();

    expect(fn () => ecrire($this->auteur, $conversation, 'Tu es là ?'))->toThrow(AuthorizationException::class);
    pageConversation($this->auteur, $conversation)->assertSeeText('a supprimé son compte')->assertDontSee('placeholder="Écrire un message…"', false);
});

it('supprime la conversation quand les deux personnes ont supprimé leur compte, et ses messages avec', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->auteur, $conversation, 'Bonjour');

    $this->auteur->delete();
    expect(Conversation::count())->toBe(1);

    $this->aidant->delete();
    expect(Conversation::count())->toBe(0)->and(Message::count())->toBe(0);
});

it('affiche « Publication supprimée » quand le besoin n’existe plus, la conversation restant lisible', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->aidant, $conversation, 'Je suis disponible samedi');

    $this->auteur->delete();

    $page = pageConversation($this->aidant, $conversation->fresh());
    $page->assertSeeText('Publication supprimée')->assertSeeText('Personne supprimée')->assertSeeText('Je suis disponible samedi');
});

it('ne laisse aucun geste accepté orphelin : la suppression du compte de l’auteur supprime ses publications et gestes (D-097)', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);

    $this->auteur->delete();

    expect(Publication::count())->toBe(0)->and(Gesture::where('state', GestureState::Accepted->value)->count())->toBe(0);
});
