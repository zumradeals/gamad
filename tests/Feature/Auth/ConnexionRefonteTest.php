<?php

use App\Models\User;

/** Refonte de la page de connexion : visuel et ergonomie seulement, mécanismes d'authentification inchangés. */
it('présente la page de connexion à un visiteur, avec les champs, libellés et liens réels', function () {
    $html = $this->get(route('login'))->assertOk()
        ->assertSeeText('Retrouvez votre espace')->assertSeeText('GAMAD.')->assertSeeText('Les mêmes personnes.')->assertSeeText('Se connecter')
        ->assertSeeText('Adresse e-mail')->assertSeeText('Mot de passe')->assertSeeText('Rester connecté')
        ->assertSeeText('Mot de passe oublié ?')->assertSeeText('Créer un compte')->assertSeeText('Découvrir GAMAD sans compte')
        ->getContent();

    expect($html)->toContain('for="email"')->toContain('for="password"')->toContain('autocomplete="current-password"')
        ->toContain('href="'.route('password.request').'"')->toContain('href="'.route('register').'"')->toContain('href="'.route('home').'"')
        ->toContain('Afficher le mot de passe')->toContain('name="remember"')->toContain('name="_token"')
        ->not->toContain('Google')->not->toContain('Apple')->not->toContain('value="password"');
});

it('ne préremplit aucun identifiant et ne met pas en avant les modules gelés', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->not->toMatch('/<input[^>]*name="email"[^>]*value="[^"]+"/')->not->toContain('ZUMRA')->not->toContain('Projets')->not->toContain('Contributions');
});

it('garde la connexion valide, l’option « Rester connecté » et la redirection vers la page demandée', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);
    $destination = route('fil', ['filtre' => 'besoins']);

    $this->get($destination)->assertRedirect(route('home'));
    $reponse = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'motdepasse1', 'remember' => '1']);

    $reponse->assertRedirect($destination);
    expect(collect($reponse->headers->getCookies())->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_')))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('ne pose pas de cookie « se souvenir » sans la case cochée', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    $reponse = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'motdepasse1']);

    expect(collect($reponse->headers->getCookies())->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_')))->toBeFalse();
});

it('affiche l’erreur sous le champ, accessible, sans révéler lequel des identifiants est faux ni redonner le mot de passe', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-faux'])->assertRedirect(route('login'));
    $html = $this->get(route('login'))->assertOk()->assertSeeText('Ces identifiants ne correspondent à aucun compte.')->getContent();

    expect($html)->toContain('role="alert"')->toContain('aria-invalid="true"')->toContain('aria-describedby="email-error"')
        ->not->toContain('secret-faux')->not->toContain('motdepasse1');
});

it('mène la page de connexion à la récupération du mot de passe, à l’inscription et à l’accueil public', function () {
    $this->get(route('password.request'))->assertOk();
    $this->get(route('register'))->assertOk();
    $this->get(route('home'))->assertOk()->assertSeeText('Des personnes qui inspirent.');
});
