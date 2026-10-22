<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

it('refuse un second compte dont l’adresse ne diffère que par la casse', function () {
    $this->post('/register', ['name' => 'Koné', 'email' => 'Kone@Exemple.org', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1']);
    $this->post('/logout');

    $this->post('/register', ['name' => 'Autre Koné', 'email' => 'kone@exemple.org', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
        ->assertSessionHasErrors('email');
    $this->post('/register', ['name' => 'Autre Koné', 'email' => 'KONE@EXEMPLE.ORG', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(1)
        ->and(User::sole()->email)->toBe('kone@exemple.org');
});

it('refuse en base deux adresses qui ne diffèrent que par la casse', function () {
    User::factory()->create(['email' => 'kone@exemple.org']);

    expect(fn () => DB::transaction(fn () => DB::table('users')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Doublon',
        'email' => 'Kone@Exemple.org',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('connecte quelle que soit la casse de l’adresse saisie', function (string $saisie) {
    $user = User::factory()->create(['email' => 'kone@exemple.org', 'password' => 'motdepasse1']);

    $this->post('/login', ['email' => $saisie, 'password' => 'motdepasse1'])->assertRedirect(route('fil'));

    $this->assertAuthenticatedAs($user);
})->with(['Kone@Exemple.org', 'KONE@EXEMPLE.ORG', 'kone@exemple.org']);

it('envoie le lien de mot de passe oublié quelle que soit la casse de l’adresse saisie', function (string $saisie) {
    Notification::fake();
    $user = User::factory()->create(['email' => 'kone@exemple.org']);

    $this->post('/forgot-password', ['email' => $saisie])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
})->with(['Kone@Exemple.org', 'KONE@EXEMPLE.ORG']);

it('réinitialise le mot de passe quelle que soit la casse de l’adresse saisie', function () {
    $user = User::factory()->create(['email' => 'kone@exemple.org']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'Kone@Exemple.org',
        'password' => 'nouveau-motdepasse',
        'password_confirmation' => 'nouveau-motdepasse',
    ])->assertRedirect(route('login'));

    $this->post('/login', ['email' => 'KONE@exemple.org', 'password' => 'nouveau-motdepasse'])->assertRedirect(route('fil'));
});

it('promeut un compte avec gamad:super-admin quelle que soit la casse de l’adresse saisie', function (string $saisie) {
    $user = User::factory()->create(['email' => 'kone@exemple.org']);

    $this->artisan('gamad:super-admin', ['email' => $saisie, '--raison' => 'Président de GAMAD'])->assertSuccessful();

    expect($user->fresh()->isSuperAdmin())->toBeTrue();
})->with(['Kone@Exemple.org', 'KONE@EXEMPLE.ORG', '  kone@exemple.org  ']);
