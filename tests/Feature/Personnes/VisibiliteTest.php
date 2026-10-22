<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('montre un profil public à un visiteur sans compte', function () {
    $awa = person('Awa Traoré', ['Couture']);

    $this->get(route('personnes.show', $awa->profile->public_id))
        ->assertOk()
        ->assertSeeText('Awa Traoré')
        ->assertSeeText('Couture');
});

it('rend un profil « inscrits seulement » invisible (404) pour un visiteur sans compte, visible une fois connecté', function () {
    $awa = person('Awa Traoré', ['Couture'], visibility: 'members');
    $url = route('personnes.show', $awa->profile->public_id);

    $this->get($url)->assertNotFound();

    $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertSeeText('Awa Traoré');
});

it('répond 404, jamais 403, à qui n’a pas le droit : le profil n’existe pas pour lui', function () {
    $awa = person('Awa', visibility: 'members');

    $this->get(route('personnes.show', $awa->profile->public_id))->assertNotFound()->assertDontSeeText('Accès refusé');
    $this->get('/personnes/identifiant-inconnu')->assertNotFound();
});

it('cache le profil d’une adresse e-mail non vérifiée à tout le monde, sauf à son propriétaire', function () {
    $nouveau = User::factory()->unverified()->create(['name' => 'Pas encore vérifié']);
    $url = route('personnes.show', $nouveau->profile->public_id);

    $this->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->actingAs($nouveau)->get($url)->assertOk();
});

it('ne laisse pas une personne non vérifiée voir les profils « inscrits seulement »', function () {
    $awa = person('Awa', visibility: 'members');

    $this->actingAs(User::factory()->unverified()->create())->get(route('personnes.show', $awa->profile->public_id))->assertNotFound();
});

it('montre toujours son propre profil à son propriétaire, même « inscrits seulement »', function () {
    $awa = person('Awa', visibility: 'members');

    $this->actingAs($awa)->get(route('personnes.show', $awa->profile->public_id))->assertOk();
});

it('applique la même règle à la fiche, à la photo et à la liste', function () {
    $verifie = User::factory()->create();
    $nonVerifie = User::factory()->unverified()->create();
    $proprietaires = [
        'public vérifié' => person('Pub', ['Couture']),
        'inscrits vérifié' => person('Ins', ['Couture'], visibility: 'members'),
        'public non vérifié' => person('Pub2', ['Couture'], attributes: ['email_verified_at' => null]),
        'inscrits non vérifié' => person('Ins2', ['Couture'], visibility: 'members', attributes: ['email_verified_at' => null]),
    ];

    foreach ([null, $verifie, $nonVerifie, $proprietaires['inscrits vérifié'], $proprietaires['public non vérifié']] as $spectateur) {
        foreach ($proprietaires as $proprietaire) {
            $profile = $proprietaire->profile;
            $parLaRegle = Gate::forUser($spectateur)->allows('view', $profile);
            $parLaRequete = Profile::query()->visibleTo($spectateur)->whereKey($profile->id)->exists();

            expect($parLaRequete)->toBe($parLaRegle, 'écart pour '.$proprietaire->name.' vu par '.($spectateur?->name ?? 'un visiteur'));
        }
    }
});

it('n’affiche jamais l’e-mail ni un téléphone, ni sur la fiche ni dans le Carrefour', function () {
    $awa = person('Awa Traoré', ['Couture'], attributes: ['email' => 'awa.secret@example.test']);

    foreach ([route('personnes.show', $awa->profile->public_id), route('personnes.index')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('awa.secret@example.test')->assertDontSee('mailto:')->assertDontSee('tel:');
        $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertDontSee('awa.secret@example.test');
    }
});

it('utilise un identifiant de profil sans lien avec le nom, l’e-mail ni l’identifiant du compte', function () {
    $un = person('Awa Traoré', attributes: ['email' => 'awa.traore@example.test']);
    $deux = person('Awa Traoré', attributes: ['email' => 'awa.traore2@example.test']);

    expect($un->profile->public_id)->not->toBe($deux->profile->public_id)
        ->and($un->profile->public_id)->toMatch('/^[a-z0-9]{12}$/')
        ->and(route('personnes.show', $un->profile->public_id))->not->toContain($un->id)->not->toContain('@');
});
