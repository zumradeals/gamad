<?php

use App\Livewire\MessagesContact;
use App\Models\AdminAction;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\ContactMessages;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/** Pages Aide et Contact (D-208). */
function messageValide(array $remplace = []): array
{
    return array_merge(['name' => 'Aminata Diallo', 'email' => 'Aminata@Exemple.test', 'subject' => 'general', 'message' => 'Bonjour, j’ai une question sur mon profil.'], $remplace);
}

beforeEach(fn () => RateLimiter::clear('contact'));

it('ouvre Aide et Contact sans compte, avec des liens dans le pied de page sur la même ligne', function () {
    $this->get('/aide')->assertOk()->assertSee('Comment créer un compte ?')->assertSee('Nous écrire');
    $this->get('/contact')->assertOk()->assertSee('Envoyer le message')->assertSee('2 000 caractères');

    $html = $this->get('/')->getContent();
    preg_match('#<nav aria-label="Pied de page".*?</nav>#s', $html, $nav);
    $texte = trim(preg_replace('/\s+/', ' ', strip_tags($nav[0])));
    expect($texte)->toStartWith('À propos Découvrir les personnes Aide Contact');
    expect($nav[0])->toContain(route('aide'))->toContain(route('contact'));
});

it('ouvre Aide et Contact dans l’application pour une personne connectée, avec son nom prérempli', function () {
    $moi = User::factory()->create(['name' => 'Awa Traoré']);
    $this->actingAs($moi)->get('/aide')->assertOk()->assertSee('Comment créer un compte ?');
    $this->actingAs($moi)->get('/contact')->assertOk()->assertSee('value="Awa Traoré"', false);
});

it('enregistre un message de contact sans compte et le confirme clairement', function () {
    $this->post('/contact', messageValide())->assertRedirect('/contact');
    $this->get('/contact')->assertSee('Votre message est bien reçu');

    $m = ContactMessage::query()->sole();
    expect($m->name)->toBe('Aminata Diallo')->and($m->email)->toBe('aminata@exemple.test')
        ->and($m->subject)->toBe('general')->and($m->state)->toBe('new')->and($m->user_id)->toBeNull();
});

it('relie le message au compte connecté', function () {
    $moi = User::factory()->create();
    $this->actingAs($moi)->post('/contact', messageValide())->assertRedirect('/contact');
    expect(ContactMessage::query()->sole()->user_id)->toBe($moi->getKey());
});

it('refuse un message invalide avec des erreurs claires et ne conserve rien', function (array $remplace, string $champ) {
    $this->from('/contact')->post('/contact', messageValide($remplace))->assertRedirect('/contact')->assertSessionHasErrors($champ);
    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'nom vide' => [['name' => ''], 'name'],
    'e-mail invalide' => [['email' => 'pas-un-email'], 'email'],
    'sujet inconnu' => [['subject' => 'piratage'], 'subject'],
    'message trop court' => [['message' => 'court'], 'message'],
    'message trop long' => [['message' => str_repeat('a', 2001)], 'message'],
]);

it('affiche les erreurs sous les champs, reliées par aria-describedby', function () {
    $html = $this->followingRedirects()->from('/contact')->post('/contact', messageValide(['email' => 'x']))->getContent();
    expect($html)->toContain('id="email-error"')->toContain('aria-invalid="true"')->toContain('email-hint email-error');
});

it('ignore en silence un robot qui remplit le champ piège', function () {
    $this->post('/contact', messageValide(['website' => 'http://spam.test']))->assertRedirect('/contact');
    $this->get('/contact')->assertSee('Votre message est bien reçu');
    expect(ContactMessage::query()->count())->toBe(0);
});

it('limite les messages par adresse e-mail et par adresse IP', function () {
    config(['gamad.limits.contact_per_hour' => 2]);
    $this->post('/contact', messageValide())->assertSessionHasNoErrors();
    $this->post('/contact', messageValide())->assertSessionHasNoErrors();
    $this->from('/contact')->post('/contact', messageValide())->assertSessionHasErrors('message');
    expect(ContactMessage::query()->count())->toBe(2);
});

it('réserve la lecture des messages au seul super administrateur', function () {
    ContactMessage::create(['name' => 'A', 'email' => 'a@a.test', 'subject' => 'other', 'body' => 'Secret du message.']);

    $this->get('/espace/messages')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/espace/messages')->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get('/espace/messages')->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace/messages')->assertOk()->assertSee('Messages de contact');
});

it('n’expose jamais le contenu d’un message aux membres ni dans le journal', function () {
    $admin = User::factory()->superAdmin()->create();
    $m = ContactMessage::create(['name' => 'Aminata', 'email' => 'secret@a.test', 'subject' => 'account', 'body' => 'Contenu très privé.']);
    app(ContactMessages::class)->open($admin, $m);

    $acte = AdminAction::query()->where('action', 'contact.read')->sole();
    expect(json_encode($acte->payload))->not->toContain('privé')->not->toContain('secret@a.test');

    $this->actingAs(User::factory()->moderator()->create())->get('/moderation/journal')->assertOk()->assertDontSee('message de contact');
    $this->actingAs($admin)->get('/moderation/journal')->assertSee('a lu un message de contact');
});

it('passe un message à Lu à l’ouverture, le marque Traité et le supprime, avec le journal', function () {
    $admin = User::factory()->superAdmin()->create();
    $m = ContactMessage::create(['name' => 'Aminata', 'email' => 'a@a.test', 'subject' => 'problem', 'body' => 'Un souci.']);

    $c = Livewire::actingAs($admin)->test(MessagesContact::class)->assertSee('Signaler un problème');
    $c->call('open', $m->getKey())->assertSee('Un souci.');
    expect($m->fresh()->state)->toBe('read')->and($m->fresh()->read_at)->not->toBeNull();

    $c->call('handle', $m->getKey());
    expect($m->fresh()->state)->toBe('handled');

    $c->call('delete', $m->getKey());
    expect(ContactMessage::query()->count())->toBe(0);
    expect(AdminAction::query()->where('action', 'like', 'contact.%')->orderBy('created_at')->pluck('action')->all())
        ->toEqualCanonicalizing(['contact.read', 'contact.handled', 'contact.deleted']);
});

it('refuse les actions de lecture à un membre ou un modérateur', function () {
    $m = ContactMessage::create(['name' => 'A', 'email' => 'a@a.test', 'subject' => 'other', 'body' => 'Secret.']);
    Livewire::actingAs(User::factory()->moderator()->create())->test(MessagesContact::class)->assertForbidden();
    expect($m->fresh()->state)->toBe('new');
});

it('efface les messages plus anciens que la durée de conservation', function () {
    $vieux = ContactMessage::create(['name' => 'A', 'email' => 'a@a.test', 'subject' => 'other', 'body' => 'Ancien message.']);
    $vieux->forceFill(['created_at' => now()->subMonths(13)])->save();
    $recent = ContactMessage::create(['name' => 'B', 'email' => 'b@a.test', 'subject' => 'other', 'body' => 'Message récent.']);

    $this->artisan('gamad:purger-contacts')->expectsOutput('1 message(s) de contact effacé(s).')->assertSuccessful();
    expect(ContactMessage::query()->pluck('id')->all())->toBe([$recent->getKey()]);
});

it('annonce le nombre de nouveaux messages dans l’administration du Président', function () {
    ContactMessage::create(['name' => 'A', 'email' => 'a@a.test', 'subject' => 'other', 'body' => 'Un nouveau message.']);
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace?rubrique=administration')->assertSee('1 nouveau')->assertSee('Lire les messages');
});

it('supprime le lien au compte sans supprimer le message quand le compte est supprimé', function () {
    $moi = User::factory()->create();
    $m = ContactMessage::create(['user_id' => $moi->getKey(), 'name' => 'A', 'email' => 'a@a.test', 'subject' => 'other', 'body' => 'Un message.']);
    $moi->delete();
    expect($m->fresh()->user_id)->toBeNull();
});
