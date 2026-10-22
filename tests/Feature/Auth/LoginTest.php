<?php

use App\Models\User;

it('connecte une personne dont l’e-mail est vérifié, puis la déconnecte', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    $this->post('/login', ['email' => $user->email, 'password' => 'motdepasse1'])
        ->assertRedirect(route('fil'));
    $this->assertAuthenticatedAs($user);

    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('refuse un mot de passe faux avec un message en français', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'faux'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);

    $this->assertGuest();
});

it('limite les tentatives de connexion', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'faux']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'motdepasse1'])
        ->assertStatus(429);

    $this->assertGuest();
});

it('renvoie un invité vers la connexion', function () {
    $this->get('/fil')->assertRedirect(route('login'));
    $this->get('/espace')->assertRedirect(route('login'));
});
