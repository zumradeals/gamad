<?php

use App\Models\User;

it('affiche l’accueil public avec les textes décidés', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Des personnes. Des actions. Un impact réel.')
        ->assertSeeText('Apportez ce que vous savez faire, apprenez ce qui vous manque, agissez avec d’autres.')
        ->assertSeeText('Formation — Travail — Adoration')
        ->assertSeeText('Créer un compte')
        ->assertSeeText('Se connecter');
});

it('mène les boutons de l’accueil vers de vraies pages', function () {
    $this->get('/')
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('href="'.route('login').'"', false);

    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk();
});

it('renvoie une personne connectée vers le Fil', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('fil'));
});

it('affiche des pages d’erreur en français', function () {
    $this->get('/page-inexistante')->assertNotFound()->assertSeeText('Page introuvable');
});
