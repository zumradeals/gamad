<?php

use App\Models\User;
use Illuminate\Support\Str;

it('est configurée en français avec le fuseau Africa/Abidjan', function () {
    expect(config('app.locale'))->toBe('fr')
        ->and(config('app.timezone'))->toBe('Africa/Abidjan')
        ->and(date_default_timezone_get())->toBe('Africa/Abidjan');
});

it('affiche les messages de validation en français', function () {
    $this->post('/register', [])
        ->assertSessionHasErrors([
            'name' => 'Le champ nom affiché est obligatoire.',
            'email' => 'Le champ adresse e-mail est obligatoire.',
            'password' => 'Le champ mot de passe est obligatoire.',
        ]);
});

it('utilise des identifiants UUID pour les comptes', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue();
});
