<?php

use App\Enums\PublicationType;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\Fil;
use App\Livewire\GestureSheet;
use App\Livewire\PublierPublication;
use App\Models\Gesture;
use App\Models\Message;
use App\Models\Publication;
use App\Models\User;
use App\Services\ConversationActions;
use App\Services\Moderation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    $this->author = person('Awa Traoré', ['Couture']);
    $this->helper = person('Idrissa Koné', ['Comptabilité']);
});

it('garde seulement Fil Agir Mon espace dans la navigation, avec les personnes accessibles depuis le Fil', function () {
    $html = $this->actingAs($this->author)->get(route('fil'))->assertOk()->assertSeeText('Trouver une personne')->getContent();
    preg_match_all('/<nav aria-label="Navigation principale".*?<\/nav>/s', $html, $navs);
    $text = trim(preg_replace('/\s+/', ' ', strip_tags(implode(' ', $navs[0]))));
    expect($text)->toContain('Fil')->toContain('Agir')->toContain('Mon espace')->not->toContain('Personnes')->not->toContain('Découvrir');
    $this->get(route('personnes.index'))->assertOk()->assertSeeText('Retour au Fil');
    $this->get(route('fil', ['q' => 'terme introuvable']))->assertOk()->assertSeeText('Aucune publication ne correspond')->assertSee('name="q"', false);
});

it('cherche dans le texte, les capacités et les noms des auteurs sans changer l’ordre chronologique', function () {
    $a = publication($this->author, data: ['body' => 'Préparer un tableau de dépenses']);
    $this->travel(1)->seconds();
    $b = publication($this->helper, PublicationType::Knowledge, ['body' => 'Conseil utile', 'skills' => ['Comptabilité']]);
    publication($this->helper, data: ['body' => 'Un autre sujet']);
    $page = Livewire::actingAs($this->author)->test(Fil::class);
    expect($page->set('query', 'TABLEAU')->viewData('posts')->pluck('id')->all())->toBe([$a->id]);
    expect($page->set('query', 'comptabilite')->viewData('posts')->pluck('id')->all())->toBe([$b->id]);
    expect($page->set('query', 'TRAORE')->viewData('posts')->pluck('id')->all())->toBe([$a->id]);
    $page->set('query', '')->call('more')->assertSet('limit', 20)->set('query', 'tableau')->assertSet('limit', 10);
});

it('combine recherche et filtre et traite les caractères spéciaux comme du texte', function () {
    publication($this->author, data: ['body' => 'Couture de vêtements']);
    publication($this->helper, PublicationType::Knowledge, ['body' => 'Couture : un savoir']);
    $page = Livewire::actingAs($this->author)->test(Fil::class)->set('query', 'Couture')->call('setFilter', 'savoirs');
    expect($page->viewData('posts'))->toHaveCount(1)->and($page->viewData('posts')->first()->type)->toBe(PublicationType::Knowledge);
    foreach (['%', '_', "'; drop table publications; --", str_repeat('a', 5000)] as $query) {
        expect($page->set('query', $query)->viewData('posts'))->toBeEmpty();
    }
    $page->assertSeeText('Aucune publication ne correspond')->call('clearSearch')->assertSet('query', '')->assertSet('limit', 10);
});

it('n’élargit jamais la visibilité à cause d’une correspondance de recherche', function () {
    $hidden = publication($this->author, data: ['body' => 'secret rare']);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $hidden, 'Motif de modération pour cet essai.');
    $unverified = User::factory()->unverified()->create();
    $post = new Publication(['body' => 'secret rare', 'type' => PublicationType::Need, 'need_state' => 'open', 'visibility' => Publication::PUBLIC]);
    $post->author()->associate($unverified);
    $post->save();
    expect(Livewire::actingAs($this->helper)->test(Fil::class)->set('query', 'secret rare')->viewData('posts'))->toBeEmpty();
    expect(Livewire::actingAs($this->author)->test(Fil::class)->set('query', 'secret rare')->viewData('posts'))->toBeEmpty();
});

it('trouve aussi une personne qui ne publie pas et transmet le terme à la recherche existante', function () {
    $silent = person('Sans publication', ['Menuiserie']);
    Livewire::actingAs($this->author)->test(Fil::class)->set('query', 'Menuiserie')->call('findPeople')
        ->assertRedirect(route('personnes.index', ['q' => 'Menuiserie']));
    Livewire::actingAs($this->author)->test(CarrefourPersonnes::class)->set('query', 'Menuiserie')->assertSeeText($silent->name);
    expect($silent->publications()->count())->toBe(0);
});

it('compose les précisions du besoin dans un seul texte sans perdre la visibilité choisie', function () {
    Livewire::actingAs($this->author)->test(PublierPublication::class, ['slug' => 'besoin'])
        ->set('body', 'Je cherche une aide pour un tableur.')
        ->set('needPurpose', 'Tenir les comptes de ma boutique')
        ->set('needTiming', 'À distance, samedi matin')
        ->set('visibility', Publication::MEMBERS)->call('publish')->assertHasNoErrors();
    $post = Publication::query()->sole();
    expect($post->body)->toBe("Je cherche une aide pour un tableur.\n\nPour : Tenir les comptes de ma boutique\n\nOù et quand : À distance, samedi matin")
        ->and($post->visibility)->toBe(Publication::MEMBERS);
});

it('vérifie le besoin obligatoire et le plafond du texte final sans perdre la saisie', function () {
    $page = Livewire::actingAs($this->author)->test(PublierPublication::class, ['slug' => 'besoin']);
    foreach (['  ', "\u{200b}"] as $empty) {
        $page->set('body', $empty)->set('needPurpose', 'Un but seul')->call('publish')->assertHasErrors('body');
    }
    $page->set('body', str_repeat('é', 450))->set('needPurpose', str_repeat('b', 100))->call('publish')->assertHasErrors('body')->assertSet('needPurpose', str_repeat('b', 100));
    expect(Publication::query()->count())->toBe(0);
});

it('ne compose pas de précisions de besoin dans un autre type de publication', function () {
    Livewire::actingAs($this->author)->test(PublierPublication::class, ['slug' => 'savoir'])
        ->set('body', 'Un savoir utile')->set('needPurpose', 'Non pertinent')->call('publish')->assertHasNoErrors();
    expect(Publication::query()->sole()->body)->toBe('Un savoir utile');
});

it('enregistre une proposition précise dans le geste et réinitialise ses champs après envoi', function () {
    $post = publication($this->author);
    Livewire::actingAs($this->helper)->test(GestureSheet::class)->call('open', $post->id)
        ->set('message', 'Je peux préparer le tableau avec vous.')
        ->set('availability', 'Samedi matin, pendant une heure')->set('helpFormat', 'remote')->call('send')
        ->assertHasNoErrors()->assertSet('availability', '')->assertSet('helpFormat', '');
    expect(Gesture::query()->sole()->message)->toBe("Je peux préparer le tableau avec vous.\nDisponibilités : Samedi matin, pendant une heure\nFormat : à distance");
});

it('refuse un format forgé et une proposition finale trop longue', function () {
    $post = publication($this->author);
    $page = Livewire::actingAs($this->helper)->test(GestureSheet::class)->call('open', $post->id);
    $page->set('helpFormat', 'script')->call('send')->assertHasErrors('helpFormat');
    $page->set('helpFormat', 'onsite')->set('message', str_repeat('é', 260))->set('availability', 'Samedi matin')->call('send')->assertHasErrors('message');
    expect(Gesture::query()->count())->toBe(0);
});

it('fait confirmer la prochaine action par l’autre participant et l’affiche à tous deux', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $date = now()->timezone('Africa/Abidjan')->addDay()->toDateString();
    pageConversation($this->author, $conversation)->call('beginAction')->set('actionText', 'Préparer ensemble le tableau des dépenses')->set('actionDate', $date)->call('proposeAction')->assertHasNoErrors()->assertSeeText('À confirmer');
    $conversation->refresh();
    expect($conversation->next_action_revision)->toBe(1)->and($conversation->next_action_confirmed_at)->toBeNull();
    pageConversation($this->helper, $conversation)->call('confirmAction', 1)->assertHasNoErrors()->assertSeeText('Convenue ensemble');
    expect($conversation->fresh()->next_action_confirmed_at)->not->toBeNull();
    expect($conversation->fresh()->next_action_revision)->toBe(2);
    pageConversation($this->author, $conversation)->assertSeeText('Convenue ensemble')->assertSeeText('Préparer ensemble le tableau des dépenses');
    expect($conversation->messages()->count())->toBe(2);
});

it('interdit l’auto-confirmation sans écrire de message ou changer la version', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $actions = app(ConversationActions::class);
    $actions->propose($this->author, $conversation, 'Une action', null, 0);
    expect(fn () => $actions->confirm($this->author, $conversation, 1))->toThrow(ValidationException::class);
    expect($conversation->fresh()->next_action_confirmed_at)->toBeNull()->and($conversation->messages()->count())->toBe(1)->and($conversation->fresh()->next_action_revision)->toBe(1);
});

it('annule la confirmation après une nouvelle proposition et permet à chacun de retirer l’action', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $actions = app(ConversationActions::class);
    $actions->propose($this->author, $conversation, 'Première action', null, 0);
    $actions->confirm($this->helper, $conversation, 1);
    $actions->propose($this->helper, $conversation, 'Une autre action', null, 2);
    expect($conversation->fresh()->next_action_confirmed_at)->toBeNull()->and($conversation->fresh()->next_action_by)->toBe($this->helper->id);
    $actions->withdraw($this->author, $conversation, 3);
    expect($conversation->fresh()->next_action)->toBeNull()->and($conversation->fresh()->next_action_revision)->toBe(4);
});

it('refuse une confirmation ou un remplacement d’ancienne version sans écraser la nouvelle action', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $actions = app(ConversationActions::class);
    $actions->propose($this->author, $conversation, 'Première action', null, 0);
    $actions->propose($this->author, $conversation, 'Nouvelle action', null, 1);
    expect(fn () => $actions->confirm($this->helper, $conversation, 1))->toThrow(ValidationException::class);
    expect(fn () => $actions->propose($this->helper, $conversation, 'Version périmée', null, 1))->toThrow(ValidationException::class);
    expect(fn () => $actions->withdraw($this->helper, $conversation, 1))->toThrow(ValidationException::class);
    expect($conversation->fresh()->next_action)->toBe('Nouvelle action')->and($conversation->fresh()->next_action_confirmed_at)->toBeNull()->and($conversation->messages()->count())->toBe(2);
});

it('préserve la version du brouillon malgré le rafraîchissement des messages', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $page = pageConversation($this->helper, $conversation)->call('beginAction')->set('actionText', 'Mon brouillon');
    app(ConversationActions::class)->propose($this->author, $conversation, 'Action reçue pendant la saisie', null, 0);
    $page->call('$refresh')->assertSet('actionRevision', 0)->call('proposeAction')->assertHasErrors('action')->assertSet('actionText', 'Mon brouillon');
    expect($conversation->fresh()->next_action)->toBe('Action reçue pendant la saisie');
});

it('refuse une action vide trop longue ou datée dans le passé', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $page = pageConversation($this->author, $conversation)->call('beginAction');
    $page->set('actionText', '   ')->call('proposeAction')->assertHasErrors('actionText');
    $page->set('actionText', str_repeat('é', 281))->call('proposeAction')->assertHasErrors('actionText');
    $page->set('actionText', 'Action valide')->set('actionDate', '2020-01-01')->call('proposeAction')->assertHasErrors('actionDate');
    $page->set('actionDate', 'demain')->call('proposeAction')->assertHasErrors('actionDate');
    expect($conversation->fresh()->next_action)->toBeNull()->and($conversation->messages()->count())->toBe(0);
});

it('accepte une date facultative et 280 caractères, en texte brut uniquement', function () {
    $conversation = conversationDe($this->author, $this->helper);
    app(ConversationActions::class)->propose($this->author, $conversation, str_repeat('é', 280), null, 0);
    expect(mb_strlen($conversation->fresh()->next_action))->toBe(280)->and($conversation->fresh()->next_action_date)->toBeNull();
    app(ConversationActions::class)->propose($this->author, $conversation, '<script>alert(1)</script>', null, 1);
    $html = pageConversation($this->helper, $conversation)->html();
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>alert(1)</script>');
});

it('refuse tout accès aux actions d’une conversation par un tiers même administrateur', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $actions = app(ConversationActions::class);
    $actions->propose($this->author, $conversation, 'Action privée', null, 0);
    foreach ([User::factory()->create(), User::factory()->superAdmin()->create()] as $third) {
        expect(fn () => $actions->propose($third, $conversation, 'Intrusion', null, 1))->toThrow(AuthorizationException::class);
        expect(fn () => $actions->confirm($third, $conversation, 1))->toThrow(AuthorizationException::class);
        expect(fn () => $actions->withdraw($third, $conversation, 1))->toThrow(AuthorizationException::class);
        $this->actingAs($third)->get(route('conversations.show', $conversation))->assertNotFound();
    }
    expect($conversation->fresh()->next_action)->toBe('Action privée')->and($conversation->messages()->count())->toBe(1);
});

it('efface l’action courante au départ d’un participant et ne recopie pas son texte dans la confirmation', function () {
    $conversation = conversationDe($this->author, $this->helper);
    $actions = app(ConversationActions::class);
    $actions->propose($this->author, $conversation, 'Texte personnel à effacer', null, 0);
    $actions->confirm($this->helper, $conversation, 1);
    $this->author->delete();
    expect($conversation->fresh()->next_action)->toBeNull()->and($conversation->fresh()->next_action_date)->toBeNull()->and($conversation->fresh()->next_action_by)->toBeNull();
    expect(Message::query()->where('body', 'like', '%Texte personnel%')->count())->toBe(0);
    expect(fn () => $actions->propose($this->helper, $conversation, 'Impossible', null, 2))->toThrow(AuthorizationException::class);
});

it('annule aussi l’écriture de l’action si l’envoi du message dépasse la limite de débit', function () {
    $conversation = conversationDe($this->author, $this->helper);
    config(['gamad.limits.messages_per_minute' => 1]);
    ecrire($this->author, $conversation, 'Premier message');
    expect(fn () => app(ConversationActions::class)->propose($this->author, $conversation, 'Action refusée', null, 0))->toThrow(ValidationException::class);
    expect($conversation->fresh()->next_action)->toBeNull()->and($conversation->fresh()->next_action_revision)->toBe(0)->and($conversation->messages()->count())->toBe(1);
});
