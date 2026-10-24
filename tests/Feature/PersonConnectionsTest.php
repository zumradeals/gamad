<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Livewire\Fil;
use App\Livewire\MyNetwork;
use App\Livewire\PersonContact;
use App\Models\Conversation;
use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Conversations;
use App\Services\Gestures;
use App\Services\People;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    $this->a = person('Awa', ['Couture']);
    $this->b = person('Binta', ['Menuiserie']);
    $this->people = app(People::class);
});

it('suit une personne sans relation, conversation ni accès privé supplémentaire', function () {
    $public = publication($this->b, PublicationType::Share, ['body' => 'Une idée publique']);
    publication($this->b, data: ['visibility' => Publication::MEMBERS]);
    publication($this->a, data: ['body' => 'Ma publication']);
    $this->people->follow($this->a, $this->b, true);
    $this->people->follow($this->a, $this->b, true);
    expect(DB::table('person_follows')->count())->toBe(1)->and(Conversation::count())->toBe(0)->and(PersonConnection::count())->toBe(0);
    $page = Livewire::actingAs($this->a)->test(Fil::class)->call('setFilter', 'suivis');
    expect($page->viewData('posts')->pluck('id')->all())->toBe([$public->id]);
    $this->people->follow($this->a, $this->b, false);
    expect($page->call('refresh')->viewData('posts'))->toBeEmpty();
});

it('refuse le suivi de soi et le suivi par une personne non vérifiée', function () {
    expect(fn () => $this->people->follow($this->a, $this->a, true))->toThrow(HttpException::class);
    $unverified = User::factory()->unverified()->create();
    expect(fn () => $this->people->follow($unverified, $this->b, true))->toThrow(HttpException::class);
});

it('demande un mot personnel et refuse les doublons ou demandes croisées', function () {
    foreach (['', '  ', str_repeat('x', 281)] as $note) {
        expect(fn () => $this->people->request($this->a, $this->b, $note))->toThrow(ValidationException::class);
    }
    $this->people->request($this->a, $this->b, 'J’aimerais partager nos façons de réparer.');
    expect(Conversation::count())->toBe(0);
    expect(fn () => $this->people->request($this->b, $this->a, 'Une demande croisée'))->toThrow(ValidationException::class);
    expect(PersonConnection::count())->toBe(1)->and(UserNotification::where('user_id', $this->b->id)->sole()->kind)->toBe(NotificationKind::ConnectionRequested);
});

it('ouvre une conversation uniquement après la décision du destinataire et notifie les deux étapes', function () {
    $this->people->request($this->a, $this->b, 'Faisons connaissance.');
    $pair = PersonConnection::sole();
    expect(fn () => $this->people->decide($this->a, $this->b, 'accept', $pair->revision))->toThrow(HttpException::class);
    $this->people->decide($this->b, $this->a, 'accept', $pair->revision);
    $conversation = Conversation::sole();
    expect($pair->fresh()->state)->toBe('accepted')->and(DB::table('person_follows')->count())->toBe(0);
    app(Conversations::class)->send($this->a, $conversation, 'Bonjour Binta');
    $this->actingAs($this->b)->get(route('conversations.show', $conversation))->assertOk()->assertSeeText('Bonjour Binta');
    $stranger = person('Autre personne');
    $this->actingAs($stranger)->get(route('conversations.show', $conversation))->assertNotFound();
    $notification = UserNotification::where('kind', 'connection_accepted')->sole();
    $this->actingAs($this->a)->get(route('notifications.open', $notification))->assertRedirect(route('conversations.show', $conversation));
});

it('décline, retire et empêche une nouvelle sollicitation pendant sept jours', function () {
    $this->people->request($this->a, $this->b, 'Un premier contact');
    $this->people->decide($this->b, $this->a, 'decline', PersonConnection::sole()->revision);
    expect(Conversation::count())->toBe(0)->and(PersonConnection::sole()->note)->toBeNull();
    expect(fn () => $this->people->request($this->a, $this->b, 'Encore'))->toThrow(ValidationException::class);
    $this->travel(8)->days();
    $this->people->request($this->a, $this->b, 'Une autre raison de se rencontrer');
    $this->people->decide($this->a, $this->b, 'withdraw', PersonConnection::sole()->revision);
    expect(PersonConnection::sole()->state)->toBe('withdrawn');
});

it('refuse une décision affichée sur une ancienne version de la demande', function () {
    $this->people->request($this->a, $this->b, 'Premier motif');
    $old = PersonConnection::sole()->revision;
    $this->people->decide($this->a, $this->b, 'withdraw', $old);
    $this->travel(8)->days();
    $this->people->request($this->a, $this->b, 'Nouveau motif');
    expect(fn () => $this->people->decide($this->b, $this->a, 'accept', $old))->toThrow(HttpException::class);
    expect(Conversation::count())->toBe(0)->and(PersonConnection::sole()->state)->toBe('pending');
});

it('limite le nombre de demandes quotidiennes', function () {
    RateLimiter::hit('connection:'.$this->a->id, 86400, 20);
    expect(fn () => $this->people->request($this->a, $this->b, 'Bonjour'))->toThrow(ValidationException::class);
});

it('bloque les deux sens du contact, retire suivis et relation et garde la lecture des messages', function () {
    $post = publication($this->b);
    $this->people->follow($this->a, $this->b, true);
    $this->people->follow($this->b, $this->a, true);
    $this->people->request($this->a, $this->b, 'Bonjour');
    $this->people->decide($this->b, $this->a, 'accept', PersonConnection::sole()->revision);
    $conversation = Conversation::sole();
    app(Conversations::class)->send($this->a, $conversation, 'Notre échange avant blocage');
    $this->people->block($this->b, $this->a, true);
    expect(DB::table('person_follows')->count())->toBe(0)->and(PersonConnection::sole()->state)->toBe('removed');
    expect(Gate::forUser($this->a)->allows('send', $conversation))->toBeFalse();
    expect(Gate::forUser($this->b)->allows('send', $conversation))->toBeFalse();
    $this->actingAs($this->a)->get(route('conversations.show', $conversation))->assertOk()->assertSeeText('Notre échange avant blocage')->assertDontSee('Écrire un message');
    $this->get(route('personnes.show', $this->b->profile))->assertNotFound();
    $this->get(route('publications.show', $post))->assertNotFound();
    expect(Profile::visibleTo($this->a)->where('user_id', $this->b->id)->exists())->toBeFalse()
        ->and(Publication::visibleTo($this->a)->whereKey($post->id)->exists())->toBeFalse();
    expect(fn () => $this->people->follow($this->a, $this->b, true))->toThrow(HttpException::class);
    expect(fn () => $this->people->request($this->a, $this->b, 'Contourner le blocage'))->toThrow(HttpException::class);
    $this->people->block($this->a, $this->b, false);
    expect(People::blocked($this->a, $this->b))->toBeTrue();
    $this->people->block($this->b, $this->a, false);
    expect(People::blocked($this->a, $this->b))->toBeFalse()->and(PersonConnection::sole()->state)->toBe('removed');
});

it('empêche aussi l’acceptation d’un geste après un blocage', function () {
    $post = publication($this->b);
    $gesture = app(Gestures::class)->propose($this->a, $post, 'Je peux aider');
    $this->people->block($this->b, $this->a, true);
    expect(fn () => app(Gestures::class)->accept($this->b, $gesture))->toThrow(AuthorizationException::class);
    expect(Conversation::count())->toBe(0);
});

it('ne montre les demandes et les suivis que dans l’espace de leur propriétaire', function () {
    $this->people->request($this->a, $this->b, 'Mot strictement personnel');
    $this->people->follow($this->a, $this->b, true);
    Livewire::actingAs($this->b)->test(MyNetwork::class)->assertSeeText('Mot strictement personnel')->call('select', 'follows')->assertDontSeeText('Awa');
    $stranger = person('Visiteur tiers');
    Livewire::actingAs($stranger)->test(MyNetwork::class)->assertDontSeeText('Mot strictement personnel');
    $this->actingAs($stranger)->get(route('personnes.show', $this->b->profile))->assertOk()->assertDontSeeText('Mot strictement personnel')->assertDontSeeText('abonnés');
});

it('permet de suivre et accepter depuis les vrais composants puis écrire directement', function () {
    Livewire::actingAs($this->a)->test(PersonContact::class, ['profile' => $this->b->profile])->call('follow', true)->set('composing', true)->set('note', 'Rencontrons-nous')->call('request')->assertHasNoErrors();
    $pair = PersonConnection::sole();
    Livewire::actingAs($this->b)->test(MyNetwork::class)->call('act', $this->a->id, 'accept', $pair->revision)->call('select', 'relations')->assertSee('Écrire');
    Livewire::actingAs($this->a)->test(PersonContact::class, ['profile' => $this->b->profile])->assertSeeText('En relation')->assertSee('href="'.route('conversations.show', Conversation::sole()).'"', false);
});

it('retire une relation sans perdre la conversation et réutilise celle-ci après un nouvel accord', function () {
    $this->people->request($this->a, $this->b, 'Bonjour');
    $this->people->decide($this->b, $this->a, 'accept', PersonConnection::sole()->revision);
    $conversation = Conversation::sole();
    $this->people->decide($this->a, $this->b, 'remove', PersonConnection::sole()->revision);
    expect(Conversation::count())->toBe(1);
    $this->travel(8)->days();
    $this->people->request($this->a, $this->b, 'Reprenons contact');
    $this->people->decide($this->b, $this->a, 'accept', PersonConnection::sole()->revision);
    expect(Conversation::sole()->id)->toBe($conversation->id);
});

it('nettoie les liens à la suppression du compte et conserve la conversation chez l’autre personne', function () {
    $this->people->request($this->a, $this->b, 'Bonjour');
    $this->people->decide($this->b, $this->a, 'accept', PersonConnection::sole()->revision);
    $this->people->follow($this->a, $this->b, true);
    $this->a->delete();
    expect(PersonConnection::count())->toBe(0)->and(DB::table('person_follows')->count())->toBe(0)->and(Conversation::count())->toBe(1);
    $this->actingAs($this->b)->get(route('conversations.show', Conversation::sole()))->assertOk();
});
