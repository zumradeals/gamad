<?php

use App\Enums\PublicationType;
use App\Models\Publication;
use App\Models\User;
use App\Services\Moderation;

it('présente GAMAD sans compte comme un réseau de personnes et de savoir-faire', function () {
    $this->get('/')->assertOk()->assertSeeText('Votre savoir-faire')->assertSeeText('mérite d’être connu.')
        ->assertSeeText('Rencontrez des personnes')->assertSeeText('Exprimez un besoin')
        ->assertSeeText('Créer un compte')->assertSeeText('Se connecter')
        ->assertSee('images/gamad-accueil-hero.webp', false)
        ->assertDontSeeText('Mouvement')->assertDontSeeText('Adoration');
    $this->assertGuest();
});

it('mène la découverte et la recherche à des pages publiques et propose explicitement la connexion', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('action="'.route('personnes.index').'"')->toContain('href="'.route('login').'"')->toContain('href="'.route('register').'"');
    $this->get(route('personnes.index', ['q' => 'Plomberie']))->assertOk();
    $this->get(route('login'))->assertOk()->assertSeeText('Découvrir GAMAD sans compte');
    $this->get(route('register'))->assertOk()->assertDontSeeText('Mouvement');
});

it('renvoie une personne connectée vers son accueil en conservant la route historique', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('fil'));
    $this->get(route('fil'))->assertOk()->assertSee('<title>Accueil', false)->assertDontSeeText('Adoration');
});

it('affiche des pages d’erreur en français', function () {
    $this->get('/page-inexistante')->assertNotFound()->assertSeeText('Page introuvable');
});

it('ne simule ni profils ni publications quand le réseau est vide', function () {
    $this->get('/')->assertOk()->assertSeeText('Les profils publics se découvrent ici.')
        ->assertDontSeeText('Des partages et des occasions.')->assertDontSeeText('GAMAD en chiffres');
});

it('présente seulement des profils et publications réellement visibles sans compte', function () {
    $public = person('Artisane publique', ['Couture']);
    $private = person('Personne réservée', ['Plomberie'], visibility: 'members');
    $unverified = person('Compte non vérifié', ['Menuiserie'], attributes: ['email_verified_at' => null]);
    publication($public, PublicationType::PersonalWork, ['body' => 'Une réalisation publique.']);
    publication($public, PublicationType::Share, ['body' => 'Un texte réservé.', 'visibility' => Publication::MEMBERS]);
    publication($private, PublicationType::Share, ['body' => 'Un auteur au profil réservé.']);
    $hidden = publication($public, PublicationType::Share, ['body' => 'Une publication masquée.']);
    app(Moderation::class)->hide(User::factory()->superAdmin()->create(), $hidden, 'Masquage pour vérifier les aperçus publics.');
    $this->get('/')->assertOk()->assertSeeText('Artisane publique')->assertSeeText('Une réalisation publique.')
        ->assertDontSeeText('Personne réservée')->assertDontSeeText('Compte non vérifié')->assertDontSeeText('Un texte réservé.')
        ->assertDontSeeText('Un auteur au profil réservé.')->assertDontSeeText('Une publication masquée.')->assertDontSeeText($public->email);
});

it('borne les profils à trois par nom et les publications aux trois plus récentes', function () {
    foreach (['Delta', 'Bravo', 'Charlie', 'Alpha'] as $name) {
        person($name, ['Couture']);
    }
    $author = User::where('name', 'Alpha')->sole();
    foreach (range(1, 4) as $i) {
        $this->travel(1)->minutes();
        publication($author, PublicationType::Share, ['body' => 'Publication numéro '.$i]);
    }
    $page = $this->get('/')->assertOk()->assertDontSeeText('Delta')->assertSeeText('Alpha')->assertSeeText('Bravo')->assertSeeText('Charlie')
        ->assertSeeText('Publication numéro 4')->assertDontSeeText('Publication numéro 1');
    expect($page->viewData('people'))->toHaveCount(3)->and($page->viewData('posts'))->toHaveCount(3);
});

it('ne promeut pas les communautés et projets conservés dans l’accueil public', function () {
    $author = person('Auteur', ['Couture']);
    $group = zumra($author, 'Collectif conservé');
    projet($author, $group, 'Projet conservé');
    $this->get('/')->assertOk()->assertDontSeeText('Collectif conservé')->assertDontSeeText('Projet conservé')
        ->assertDontSee('href="'.route('zumras.index').'"', false)->assertDontSee('href="'.route('projets.index').'"', false);
});

it('fait découvrir GAMAD aux visiteurs venant de /fil tout en conservant les autres protections', function () {
    $this->get(route('fil', ['filtre' => 'besoins']))->assertRedirect(route('home'));
    $this->get(route('espace'))->assertRedirect(route('login'));
    $this->get(route('agir'))->assertRedirect(route('login'));
});

it('conserve la destination demandée après la découverte puis une connexion explicite', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);
    $destination = route('fil', ['filtre' => 'besoins', 'q' => 'Couture']);
    $this->get($destination)->assertRedirect(route('home'));
    $this->get('/')->assertOk();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'motdepasse1'])->assertRedirect($destination);
});
