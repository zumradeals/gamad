<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('envoie le lien de réinitialisation', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('réinitialise le mot de passe avec un jeton valide', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-motdepasse',
        'password_confirmation' => 'nouveau-motdepasse',
    ])->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'nouveau-motdepasse'])->assertRedirect(route('fil'));
});

it('refuse un jeton invalide', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'faux',
        'email' => $user->email,
        'password' => 'nouveau-motdepasse',
        'password_confirmation' => 'nouveau-motdepasse',
    ])->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation n’est plus valide.']);
});
