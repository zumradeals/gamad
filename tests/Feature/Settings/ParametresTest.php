<?php

use App\Livewire\Parametres;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function formulaireComplet(array $remplace = []): array
{
    return $remplace + [
        'mailHost' => 'smtp.exemple.org',
        'mailPort' => '465',
        'mailEncryption' => 'ssl',
        'mailUsername' => 'no-reply@exemple.org',
        'mailFromAddress' => 'no-reply@exemple.org',
        'mailFromName' => 'GAMAD',
        'appName' => 'GAMAD',
        'appUrl' => 'https://reseau.exemple.org',
        'limitRegister' => '20',
        'limitLogin' => '5',
    ];
}

function remplir($composant, array $valeurs)
{
    foreach ($valeurs as $propriete => $valeur) {
        $composant->set($propriete, $valeur);
    }

    return $composant;
}

it('renvoie un invité vers la connexion', function () {
    $this->get('/espace/parametres')->assertRedirect(route('login'));
});

it('refuse la page à un membre et à un modérateur', function () {
    $this->actingAs(User::factory()->create())->get('/espace/parametres')->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get('/espace/parametres')->assertForbidden();
});

it('refuse aussi les actions du composant à un membre et à un modérateur', function () {
    Livewire::actingAs(User::factory()->create())->test(Parametres::class)->assertForbidden();
    Livewire::actingAs(User::factory()->moderator()->create())->test(Parametres::class)->assertForbidden();
});

it('affiche la page au super administrateur, avec ses trois sections', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace/parametres')
        ->assertOk()
        ->assertSeeText('Courriel')
        ->assertSeeText('Plateforme')
        ->assertSeeText('Sécurité')
        ->assertSeeText('Envoyer un e-mail de test')
        ->assertSee('aria-current="page"', false);
});

it('propose le lien Paramètres dans Espace au super administrateur seulement', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace')
        ->assertSee('href="'.route('espace.parametres').'"', false);

    $this->actingAs(User::factory()->moderator()->create())->get('/espace')
        ->assertDontSee('espace/parametres', false)
        ->assertDontSeeText('Administration');

    $this->actingAs(User::factory()->create())->get('/espace')
        ->assertDontSee('espace/parametres', false);
});

it('affiche les valeurs par défaut quand rien n’est enregistré', function () {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Parametres::class)
        ->assertSet('appName', 'GAMAD')
        ->assertSet('mailPort', '587')
        ->assertSet('mailEncryption', 'starttls')
        ->assertSet('limitRegister', '20')
        ->assertSet('limitLogin', '5')
        ->assertSet('hasMailPassword', false);
});

it('enregistre les réglages, les applique et inscrit chaque modification au journal', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Le Président']);

    remplir(Livewire::actingAs($admin)->test(Parametres::class), formulaireComplet(['mailPassword' => 'MotDePasse-SMTP-9', 'limitRegister' => '30']))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Réglages enregistrés');

    $settings = app(Settings::class);
    expect($settings->get('mail_host'))->toBe('smtp.exemple.org')
        ->and($settings->get('mail_port'))->toBe(465)
        ->and($settings->get('limit_register_per_hour'))->toBe(30)
        ->and($settings->secret('mail_password'))->toBe('MotDePasse-SMTP-9')
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('gamad.limits.register_per_hour'))->toBe(30);

    $entrees = AdminAction::where('action', 'setting.changed')->get()->keyBy('subject_id');
    expect($entrees)->toHaveKeys(['mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name', 'app_url', 'limit_register_per_hour'])
        ->and($entrees['limit_register_per_hour']->payload)->toEqual(['ancien' => 20, 'nouveau' => 30])
        ->and($entrees['mail_host']->payload)->toEqual(['ancien' => null, 'nouveau' => 'smtp.exemple.org'])
        ->and($entrees['mail_password']->payload)->toEqual(['secret' => true, 'etat' => 'modifié'])
        ->and($entrees['mail_host']->actor_label)->toBe('Le Président')
        ->and($entrees)->not->toHaveKeys(['app_name', 'limit_login_per_minute']);
});

it('ne réaffiche jamais le mot de passe, ni dans la page ni dans son état', function () {
    $admin = User::factory()->superAdmin()->create();

    $composant = remplir(Livewire::actingAs($admin)->test(Parametres::class), formulaireComplet(['mailPassword' => 'MotDePasse-SMTP-9']))
        ->call('save')
        ->assertSet('mailPassword', '')
        ->assertSet('hasMailPassword', true)
        ->assertSee('Un mot de passe est enregistré');

    expect($composant->html())->not->toContain('MotDePasse-SMTP-9');

    // Nouvelle visite de la page : le mot de passe n'est pas rechargé.
    $this->actingAs($admin)->get('/espace/parametres')->assertOk()->assertDontSee('MotDePasse-SMTP-9');
    Livewire::actingAs($admin)->test(Parametres::class)->assertSet('mailPassword', '');

    // Et la valeur stockée est chiffrée.
    expect(DB::table('settings')->where('key', 'mail_password')->value('value'))->not->toContain('MotDePasse-SMTP-9');
});

it('laisse le mot de passe inchangé quand le champ reste vide, sans inscription au journal', function () {
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['mail_password' => 'Ancien-Secret-1'], $admin);
    $avant = AdminAction::where('subject_id', 'mail_password')->count();

    Livewire::actingAs($admin)->test(Parametres::class)
        ->set('appName', 'Réseau GAMAD')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Settings::class)->secret('mail_password'))->toBe('Ancien-Secret-1')
        ->and(AdminAction::where('subject_id', 'mail_password')->count())->toBe($avant);
});

it('retire le mot de passe enregistré seulement avec la case dédiée, et le journalise « modifié »', function () {
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['mail_password' => 'Ancien-Secret-1'], $admin);

    Livewire::actingAs($admin)->test(Parametres::class)
        ->set('clearMailPassword', true)
        ->call('save')
        ->assertSet('hasMailPassword', false)
        ->assertSet('clearMailPassword', false);

    expect(app(Settings::class)->secret('mail_password'))->toBeNull()
        ->and(AdminAction::where('subject_id', 'mail_password')->count())->toBe(2);
});

it('dit « Aucune modification » quand rien ne change', function () {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Parametres::class)
        ->call('save')
        ->assertSee('Aucune modification.');

    expect(AdminAction::count())->toBe(0);
});

it('valide les champs', function (array $valeurs, array $erreurs) {
    remplir(Livewire::actingAs(User::factory()->superAdmin()->create())->test(Parametres::class), formulaireComplet($valeurs))
        ->call('save')
        ->assertHasErrors($erreurs);

    expect(AdminAction::count())->toBe(0);
})->with([
    'port hors limites' => [['mailPort' => '70000'], ['mailPort']],
    'port non numérique' => [['mailPort' => 'abc'], ['mailPort']],
    'chiffrement inconnu' => [['mailEncryption' => 'rot13'], ['mailEncryption']],
    'adresse d’expéditeur invalide' => [['mailFromAddress' => 'pas-une-adresse'], ['mailFromAddress']],
    'adresse d’expéditeur manquante avec un serveur' => [['mailFromAddress' => ''], ['mailFromAddress']],
    'nom de plateforme vide' => [['appName' => ''], ['appName']],
    'adresse publique sans https' => [['appUrl' => 'http://reseau.exemple.org'], ['appUrl']],
    'adresse publique invalide' => [['appUrl' => 'pas une url'], ['appUrl']],
    'limite d’inscription nulle' => [['limitRegister' => '0'], ['limitRegister']],
    'limite d’inscription trop haute' => [['limitRegister' => '5000'], ['limitRegister']],
    'limite de connexion trop haute' => [['limitLogin' => '61'], ['limitLogin']],
]);

it('accepte un courriel entièrement vide', function () {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Parametres::class)
        ->set('appName', 'GAMAD Test')
        ->call('save')
        ->assertHasNoErrors();
});

it('envoie un e-mail de test à l’adresse du super administrateur', function () {
    $admin = User::factory()->superAdmin()->create(['email' => 'president@example.com']);
    app(Settings::class)->update(['mail_host' => 'smtp.exemple.org', 'app_name' => 'GAMAD'], $admin);
    config(['mail.default' => 'array']);

    Livewire::actingAs($admin)->test(Parametres::class)
        ->call('sendTestEmail')
        ->assertSet('testFailed', false)
        ->assertSee('E-mail de test envoyé à president@example.com');

    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);
    $message = $messages[0]->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe('president@example.com')
        ->and($message->getSubject())->toBe('Courriel de test — GAMAD')
        ->and($message->getTextBody())->toContain('fonctionne');
    expect(AdminAction::where('action', 'settings.test_email_sent')->count())->toBe(1);
});

it('refuse d’envoyer le test tant que le serveur SMTP n’est pas enregistré', function () {
    config(['mail.default' => 'array']);

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Parametres::class)
        ->call('sendTestEmail')
        ->assertSet('testFailed', true)
        ->assertSee('Renseignez et enregistrez d’abord le serveur SMTP');

    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
});

it('montre l’échec d’envoi sans jamais laisser passer le mot de passe', function () {
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['mail_host' => 'smtp.exemple.org', 'mail_password' => 'MotDePasse-SMTP-9'], $admin);

    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Authentification refusée pour MotDePasse-SMTP-9 sur smtp.exemple.org'));

    $composant = Livewire::actingAs($admin)->test(Parametres::class)
        ->call('sendTestEmail')
        ->assertSet('testFailed', true)
        ->assertSee('L’envoi a échoué')
        ->assertSee('Authentification refusée');

    expect($composant->html())->not->toContain('MotDePasse-SMTP-9');
    expect(json_encode(DB::table('admin_actions')->get()))->not->toContain('MotDePasse-SMTP-9')
        ->and(AdminAction::where('action', 'settings.test_email_failed')->count())->toBe(1);
});

it('affiche le nom de plateforme réglé dans le titre des pages', function () {
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['app_name' => 'Réseau GAMAD'], $admin);
    app(Settings::class)->applyToConfig();

    $this->actingAs($admin)->get('/espace')->assertSee('<title>Espace — Réseau GAMAD</title>', false);
    auth()->logout();
    $this->get('/login')->assertSee('<title>Connexion — Réseau GAMAD</title>', false);
});
