<?php

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('crée un compte membre et envoie le lien de vérification', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Awa Traoré',
        'email' => 'Awa@Example.com',
        'password' => 'motdepasse1',
        'password_confirmation' => 'motdepasse1',
    ])->assertRedirect(route('fil'));

    $user = User::firstWhere('email', 'awa@example.com');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Awa Traoré')
        ->and($user->platform_role)->toBe(PlatformRole::Member)
        ->and($user->email_verified_at)->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);

    // Le Fil exige un e-mail vérifié : la personne arrive sur l'écran de vérification.
    $this->get('/fil')->assertRedirect(route('verification.notice'));
});

it('refuse un mot de passe trop court ou non confirmé et une adresse déjà prise', function () {
    User::factory()->create(['email' => 'pris@example.com']);

    $this->post('/register', ['name' => 'A', 'email' => 'pris@example.com', 'password' => 'court', 'password_confirmation' => 'autre'])
        ->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

it('ne donne jamais le rôle de plateforme à l’inscription, même si on le demande', function () {
    $this->post('/register', [
        'name' => 'Intrus', 'email' => 'intrus@example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        'platform_role' => 'super_admin',
    ]);

    expect(User::firstWhere('email', 'intrus@example.com')->platform_role)->toBe(PlatformRole::Member);
});

it('bloque l’accès au Fil et à Espace tant que l’e-mail n’est pas vérifié', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/fil')->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get('/espace')->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSeeText($user->email);
});

it('vérifie l’e-mail avec le lien signé', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('fil').'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($user)->get('/fil')->assertOk();
});

it('refuse un lien de vérification dont la signature est fausse', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
        ->assertForbidden()->assertSeeText('Accès refusé');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('renvoie le lien de vérification à la demande', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rédige le courriel de vérification en français', function () {
    $user = User::factory()->unverified()->create();
    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Vérifiez votre adresse e-mail')
        ->and($mail->actionText)->toBe('Vérifier mon adresse e-mail')
        ->and($mail->introLines)->toContain('Cliquez sur le bouton ci-dessous pour confirmer votre adresse e-mail.');
});

it('limite à 20 les inscriptions par heure et par adresse IP', function () {
    foreach (range(1, 20) as $n) {
        $this->post('/register', [
            'name' => "Personne $n", 'email' => "personne$n@example.com",
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertRedirect(route('fil'));
        $this->post('/logout');
    }

    $this->post('/register', [
        'name' => 'Vingt et unième', 'email' => 'vingtunieme@example.com',
        'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
    ])->assertStatus(429);

    expect(User::where('email', 'vingtunieme@example.com')->exists())->toBeFalse();
});

it('compte aussi les tentatives refusées, et ne gêne pas les autres adresses IP', function () {
    foreach (range(1, 20) as $_) {
        $this->post('/register', [])->assertSessionHasErrors();
    }

    $this->post('/register', [])->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post('/register', [])->assertSessionHasErrors();
});

it('n’applique pas la limite d’inscription à l’affichage du formulaire ni à la connexion', function () {
    foreach (range(1, 8) as $_) {
        $this->get('/register')->assertOk();
    }

    foreach (range(1, 20) as $_) {
        $this->post('/register', []);
    }

    $this->get('/register')->assertOk();
    $this->get('/login')->assertOk();
});
