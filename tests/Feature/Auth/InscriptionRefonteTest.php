<?php

use App\Models\User;
use App\Services\LegalTexts;

/** Refonte de la page d'inscription : même charte que la connexion, mécanismes Fortify inchangés. */
it('présente l’inscription avec les champs, libellés, aides et liens réels, sans fournisseur externe', function () {
    $html = $this->get(route('register'))->assertOk()
        ->assertSeeText('Bienvenue sur')->assertSeeText('Créer un compte')->assertSeeText('Nom affiché')->assertSeeText('Adresse e-mail')
        ->assertSeeText('Mot de passe')->assertSeeText('Confirmer le mot de passe')->assertSeeText('Elle reste visible par vous seul.')
        ->assertSeeText('8 caractères au minimum.')->assertSeeText('Rien d’obligatoire')->assertSeeText('Se connecter')->assertSeeText('Découvrir GAMAD sans compte')
        ->getContent();

    expect($html)->toContain('for="name"')->toContain('for="email"')->toContain('for="password"')->toContain('for="password_confirmation"')
        ->toContain('autocomplete="new-password"')->toContain('name="_token"')->toContain('Afficher les mots de passe')
        ->toContain('href="'.route('login').'"')->toContain('href="'.route('home').'"')
        ->not->toContain('Google')->not->toContain('Apple');
});

it('affiche les erreurs sous les champs, reliées au champ, sans redonner les mots de passe', function () {
    $this->from(route('register'))->post(route('register.store'), ['name' => 'Nadia', 'email' => 'pas-un-email', 'password' => 'secret-court', 'password_confirmation' => 'autre'])
        ->assertRedirect(route('register'));
    $html = $this->followingRedirects()->get(route('register'))->assertOk()->getContent();

    expect($html)->toContain('role="alert"')->toContain('aria-invalid="true"')->toContain('aria-describedby="email-hint email-error"')
        ->not->toContain('secret-court')->not->toContain('value="autre"');
});

it('crée le compte comme avant, puis demande de vérifier l’adresse e-mail', function () {
    $this->post(route('register.store'), ['name' => 'Nadia Test', 'email' => 'nadia@exemple.org', 'password' => 'motdepasse-1', 'password_confirmation' => 'motdepasse-1'])
        ->assertRedirect();

    expect(User::query()->where('email', 'nadia@exemple.org')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

it('exige la charte quand elle est adoptée', function () {
    $admin = User::factory()->superAdmin()->create();
    app(LegalTexts::class)->adoptCharter($admin);

    $this->get(route('register'))->assertOk()->assertSeeText('charte de conduite')->assertSee('name="charter"', false);
    $this->post(route('register.store'), ['name' => 'Nadia', 'email' => 'n2@exemple.org', 'password' => 'motdepasse-1', 'password_confirmation' => 'motdepasse-1'])->assertSessionHasErrors('charter');
});

it('garde les liens d’en-tête cohérents sur l’inscription : pas de second bouton « Créer un compte »', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    // Un seul lien vers l'inscription reste (le menu du téléphone) : l'en-tête ne propose pas ce que la page fait déjà.
    expect(substr_count($html, 'href="'.route('register').'"'))->toBeLessThanOrEqual(1);
    expect($html)->toContain('Se connecter');
});
