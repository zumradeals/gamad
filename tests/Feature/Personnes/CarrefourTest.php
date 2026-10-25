<?php

use App\Livewire\CarrefourPersonnes;
use App\Models\City;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\User;
use Livewire\Livewire;

function noms($composant): array
{
    return $composant->viewData('people')->getCollection()->map(fn ($profile) => $profile->user->name)->all();
}

beforeEach(function () {
    $this->awa = person('Awa Traoré', ['Comptabilité', 'Gestion de petit commerce', 'Tableur Excel'], ['Vente sur WhatsApp', 'Anglais'], 'Yamoussoukro', domains: ['Économie et Finances']);
    $this->yao = person('Yao Kouassi', ['Comptabilité', 'Fiscalité des entreprises'], ['Gestion de projet'], 'Cocody', domains: ['Économie et Finances']);
    $this->fatou = person('Fatoumata Diallo', ['Micro-crédit', 'Comptabilité'], ['Informatique de base'], 'Bouaké', domains: ['Affaires commerciales']);
    $this->seydou = person('Seydou Bamba', ['Comptabilité agricole'], ['Transformation du karité'], 'Korhogo', domains: ['Agriculture, Pêche et Élevage']);
});

function visiteur()
{
    return Livewire::test(CarrefourPersonnes::class)->set('mode', 'offered');
}

it('est ouvert à un visiteur sans compte', function () {
    $this->get('/personnes')->assertOk()->assertSeeText('Personnes')->assertSeeText('Trouvez qui peut vous aider');
});

it('trouve par capacité sans tenir compte de la casse ni des accents', function () {
    foreach (['comptabilité', 'COMPTABILITE', 'Comptabilité', '  comptabilite  '] as $saisie) {
        $noms = noms(visiteur()->set('query', $saisie));

        expect($noms)->toBe(['Awa Traoré', 'Fatoumata Diallo', 'Seydou Bamba', 'Yao Kouassi']);
    }
});

it('trouve par nom sans tenir compte de la casse ni des accents', function () {
    expect(noms(visiteur()->set('query', 'TRAORE')))->toBe(['Awa Traoré'])
        ->and(noms(visiteur()->set('query', 'fatoumata')))->toBe(['Fatoumata Diallo'])
        ->and(noms(visiteur()->set('query', 'yao kouassi')))->toBe(['Yao Kouassi']);
});

it('trouve une partie de capacité (« comptabilité » trouve « Comptabilité agricole »)', function () {
    expect(noms(visiteur()->set('query', 'agricole')))->toBe(['Seydou Bamba']);
});

it('traite les caractères spéciaux de la recherche comme du texte', function () {
    expect(noms(visiteur()->set('query', '%')))->toBe([])
        ->and(noms(visiteur()->set('query', '_')))->toBe([])
        ->and(noms(visiteur()->set('query', "'; drop table users; --")))->toBe([]);
});

it('bascule entre « peut l’apporter » et « veut l’apprendre »', function () {
    $apporter = visiteur()->set('query', 'comptabilité');
    expect(noms($apporter))->toHaveCount(4);

    $apprendre = visiteur()->set('query', 'anglais')->call('setMode', 'wanted');
    expect(noms($apprendre))->toBe(['Awa Traoré']);

    // « Anglais » n'est apporté par personne : dans le sens « apporter », la recherche est vide.
    expect(noms(visiteur()->set('query', 'anglais')))->toBe([]);
    expect(noms(visiteur()->set('query', 'comptabilité')->call('setMode', 'wanted')))->toBe([]);
});

it('ne liste, sans recherche, que les personnes qui ont indiqué au moins une capacité dans le sens cherché', function () {
    person('Sans capacité');
    person('Que apprend', [], ['Couture']);

    expect(noms(visiteur()))->not->toContain('Sans capacité')->not->toContain('Que apprend')
        ->and(noms(visiteur()->call('setMode', 'wanted')))->toContain('Que apprend')->not->toContain('Sans capacité');
});

it('filtre par ville', function () {
    $cocody = City::query()->where('name', 'Cocody')->value('id');

    expect(noms(visiteur()->set('query', 'comptabilité')->set('city', $cocody)))->toBe(['Yao Kouassi']);
});

it('filtre par domaine', function () {
    $agriculture = Domain::query()->where('name', 'Agriculture, Pêche et Élevage')->value('id');
    $economie = Domain::query()->where('name', 'Économie et Finances')->value('id');

    expect(noms(visiteur()->set('query', 'comptabilité')->set('domain', $agriculture)))->toBe(['Seydou Bamba'])
        ->and(noms(visiteur()->set('query', 'comptabilité')->set('domain', $economie)))->toBe(['Awa Traoré', 'Yao Kouassi']);
});

it('combine ville et domaine', function () {
    $economie = Domain::query()->where('name', 'Économie et Finances')->value('id');
    $yamoussoukro = City::query()->where('name', 'Yamoussoukro')->value('id');
    $bouake = City::query()->where('name', 'Bouaké')->value('id');

    expect(noms(visiteur()->set('city', $yamoussoukro)->set('domain', $economie)))->toBe(['Awa Traoré'])
        ->and(noms(visiteur()->set('city', $bouake)->set('domain', $economie)))->toBe([]);
});

it('ignore un filtre qui n’est pas un identifiant valide, sans erreur', function () {
    expect(noms(visiteur()->set('city', 'n-importe-quoi')->set('domain', "1' or '1'='1")->set('query', 'comptabilité')))->toHaveCount(4);
});

it('trie par ordre alphabétique des noms : aucun classement de personnes', function () {
    expect(noms(visiteur()->set('query', 'comptabilité')))->toBe(['Awa Traoré', 'Fatoumata Diallo', 'Seydou Bamba', 'Yao Kouassi']);

    $this->get('/personnes?q=comptabilité')->assertDontSeeText('score')->assertDontSeeText('note')->assertDontSeeText('classement');
});

it('affiche le décompte et les capacités de chaque personne, sans « j’aime »', function () {
    visiteur()->set('query', 'comptabilité')
        ->assertSeeText('4 personnes peuvent apporter « comptabilité »')
        ->assertSeeText('Peut apporter')
        ->assertSeeText('Veut apprendre')
        ->assertSeeText('Vente sur WhatsApp')
        ->assertSeeText('Anglais')
        ->assertSeeText('Abidjan, Cocody')
        ->assertSeeText('Voir le profil')
        ->assertDontSeeText('Inviter');

    visiteur()->set('query', 'fiscalité')->assertSeeText('1 personne peut apporter « fiscalité »');
    visiteur()->set('query', 'anglais')->call('setMode', 'wanted')->assertSeeText('1 personne veut apprendre « anglais »');
});

it('pagine par 10 et revient à la première page quand la recherche change', function () {
    foreach (range(1, 12) as $i) {
        person(sprintf('Zoé %02d', $i), ['Poterie']);
    }

    $page = visiteur()->set('query', 'poterie');
    expect(noms($page))->toHaveCount(10)->and(noms($page)[0])->toBe('Zoé 01');
    $page->assertSeeText('Page 1 sur 2')->assertSeeText('12 personnes peuvent apporter');

    $page->call('nextPage');
    expect(noms($page))->toBe(['Zoé 11', 'Zoé 12']);
    $page->assertSeeText('Page 2 sur 2');

    $page->set('query', 'poteri');
    expect(noms($page))->toHaveCount(10);
});

it('n’affiche pas de pagination quand tout tient sur une page', function () {
    visiteur()->set('query', 'comptabilité')->assertDontSeeText('Page 1 sur');
});

it('invite un visiteur à créer un compte quand rien ne correspond', function () {
    visiteur()->set('query', 'zzzz')
        ->assertSeeText('Personne ne correspond à cette recherche.')
        ->assertSeeText('Créer un compte')
        ->assertSeeText('Effacer la recherche')
        ->call('clearFilters')
        ->assertSet('query', '');
});

it('invite une personne sans capacité à compléter son profil quand rien ne correspond', function () {
    $nouveau = User::factory()->create();

    Livewire::actingAs($nouveau)->test(CarrefourPersonnes::class)->set('query', 'zzzz')
        ->assertSeeText('Compléter mon profil')
        ->assertSeeText('Vous n’apparaissez pas encore ici')
        ->assertSee(route('espace.profil'), false);
});

it('n’invite pas à compléter un profil déjà rempli', function () {
    Livewire::actingAs($this->awa)->test(CarrefourPersonnes::class)->set('query', 'zzzz')
        ->assertDontSeeText('Compléter mon profil')
        ->assertSeeText('Essayez une autre capacité');
});

it('montre l’état vide du Carrefour quand personne n’est inscrit', function () {
    Profile::query()->delete();
    User::query()->delete();

    visiteur()->assertSeeText('Personne n’a encore indiqué ce qu’elle peut apporter.')->assertSeeText('Créer un compte');
});

it('ne montre à un visiteur que les profils publics, et à une personne connectée aussi ceux « inscrits seulement »', function () {
    person('Discret', ['Comptabilité'], visibility: 'members');

    expect(noms(visiteur()->set('query', 'comptabilité')))->not->toContain('Discret');

    $connecte = User::factory()->create();
    expect(noms(Livewire::actingAs($connecte)->test(CarrefourPersonnes::class)->set('query', 'comptabilité')))->toContain('Discret');
});

it('ne montre pas les personnes dont l’e-mail n’est pas vérifié', function () {
    person('Non vérifié', ['Comptabilité'], attributes: ['email_verified_at' => null]);

    expect(noms(visiteur()->set('query', 'comptabilité')))->not->toContain('Non vérifié');
});

it('permet à deux comptes de test de se trouver l’un l’autre par capacité et par ville, dans les deux sens', function () {
    $a = person('Compte A', ['Menuiserie'], ['Jardinage'], 'Bouaké');
    $b = person('Compte B', ['Jardinage'], ['Menuiserie'], 'Bouaké');
    $bouake = City::query()->where('name', 'Bouaké')->value('id');

    // A cherche quelqu'un qui peut apporter ce qu'il veut apprendre (jardinage) à Bouaké : il trouve B.
    expect(noms(Livewire::actingAs($a)->test(CarrefourPersonnes::class)->set('mode', 'offered')->set('query', 'jardinage')->set('city', $bouake)))->toBe(['Compte B']);
    // B cherche quelqu'un qui peut apporter la menuiserie : il trouve A.
    expect(noms(Livewire::actingAs($b)->test(CarrefourPersonnes::class)->set('mode', 'offered')->set('query', 'menuiserie')->set('city', $bouake)))->toBe(['Compte A']);
    // Dans l'autre sens : A cherche qui veut apprendre la menuiserie : B ; B cherche qui veut apprendre le jardinage : A.
    expect(noms(Livewire::actingAs($a)->test(CarrefourPersonnes::class)->set('query', 'menuiserie')->set('mode', 'wanted')->set('city', $bouake)))->toBe(['Compte B'])
        ->and(noms(Livewire::actingAs($b)->test(CarrefourPersonnes::class)->set('query', 'jardinage')->set('mode', 'wanted')->set('city', $bouake)))->toBe(['Compte A']);
    // Et ils ne se trouvent pas dans une autre ville.
    $yamoussoukro = City::query()->where('name', 'Yamoussoukro')->value('id');
    expect(noms(Livewire::actingAs($a)->test(CarrefourPersonnes::class)->set('query', 'jardinage')->set('city', $yamoussoukro)))->toBe([]);
});

it('liste les villes et domaines actifs dans les filtres, pas les désactivés', function () {
    $korhogo = City::query()->where('name', 'Korhogo')->firstOrFail();
    $securite = Domain::query()->where('name', 'Sécurité')->firstOrFail();
    $korhogo->update(['active' => false]);
    $securite->update(['active' => false]);

    visiteur()->assertSeeText('Toutes les localités')->assertSeeText('Abidjan, Cocody')->assertSeeText('Bouaké')
        ->assertDontSee('<option value="'.$korhogo->id.'">', false)
        ->assertSeeText('Tous les domaines')
        ->assertDontSee("\$set('domain', '".$securite->id."')", false);
});

it('porte la recherche dans l’adresse, pour que les liens du profil ouvrent le Carrefour déjà filtré', function () {
    $this->get('/personnes?q=comptabilit%C3%A9&sens=offered')->assertOk()->assertSeeText('4 personnes peuvent apporter « comptabilité »');
    $this->get('/personnes?q=anglais&sens=wanted')->assertOk()->assertSeeText('1 personne veut apprendre « anglais »');

    Livewire::withQueryParams(['q' => 'anglais', 'sens' => 'wanted', 'ville' => City::query()->where('name', 'Yamoussoukro')->value('id')])
        ->test(CarrefourPersonnes::class)->assertSet('query', 'anglais')->assertSet('mode', 'wanted');
});

it('ne lance pas de requête fautive avec une recherche démesurée', function () {
    expect(noms(visiteur()->set('query', str_repeat('a', 5000))))->toBe([]);
});
