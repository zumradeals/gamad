<?php

use App\Models\User;

function onglets(string $html): array
{
    preg_match('/<nav aria-label="Onglets du profil".*?<\/nav>/s', $html, $nav);
    preg_match_all('/<a [^>]*>(.*?)<\/a>/s', $nav[0] ?? '', $liens);

    return array_map(fn ($texte) => trim(preg_replace('/\s+/', ' ', strip_tags($texte))), $liens[1]);
}

beforeEach(function () {
    $this->awa = person('Awa Traoré', ['Comptabilité', 'Gestion de petit commerce'], ['Vente sur WhatsApp', 'Anglais'], 'Yamoussoukro',
        domains: ['Économie et Finances', 'Affaires commerciales'], bio: "Comptable de formation.\nJ’aide les petits commerces.");
    $this->awa->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();
    $this->url = route('personnes.show', $this->awa->profile->public_id);
});

it('affiche le Monde d’une personne : nom, lieu, ancienneté, présentation, capacités et domaines', function () {
    $this->get($this->url)->assertOk()
        ->assertSeeText('Awa Traoré')
        ->assertSeeText('Yamoussoukro, Côte d’Ivoire')
        ->assertSeeText('Sur GAMAD depuis octobre 2026')
        ->assertSeeText('Comptable de formation.')
        ->assertSeeText('Je peux apporter')
        ->assertSeeText('Comptabilité')
        ->assertSeeText('Gestion de petit commerce')
        ->assertSeeText('Je veux apprendre')
        ->assertSeeText('Vente sur WhatsApp')
        ->assertSeeText('Anglais')
        ->assertSeeText('Domaines qui l’intéressent')
        ->assertSeeText('Économie et Finances')
        ->assertSeeText('Affaires commerciales')
        ->assertSeeText('Touchez une capacité pour trouver d’autres personnes qui l’apportent.');
});

it('affiche un lieu à plusieurs niveaux pour une commune d’Abidjan', function () {
    $yao = person('Yao Kouassi', ['Comptabilité'], city: 'Cocody');

    $this->get(route('personnes.show', $yao->profile->public_id))->assertSeeText('Abidjan, Cocody, Côte d’Ivoire');
});

it('relie les quatre sections du profil et propose Suivre sans invitation collective', function () {
    $html = $this->actingAs(User::factory()->create())->get($this->url)->assertOk()->getContent();

    expect(onglets($html))->toBe(['Savoir-faire', 'Réalisations', 'Parcours', 'Publications']);

    $this->actingAs(User::factory()->create())->get($this->url)
        ->assertSeeText('Suivre')->assertSeeText('Entrer en relation')
        ->assertDontSeeText('Inviter')
        ->assertDontSeeText('Remerciements')
        ->assertDontSeeText('Lot 2')
        ->assertDontSeeText('Lot 3');
});

it('montre « Modifier mon profil » au propriétaire seulement', function () {
    $this->actingAs($this->awa)->get($this->url)->assertSeeText('Modifier mon profil')->assertSee(route('espace.profil'), false);
    $this->actingAs(User::factory()->create())->get($this->url)->assertDontSeeText('Modifier mon profil');
    $this->get($this->url)->assertDontSeeText('Modifier mon profil');
});

it('prévient le propriétaire que son profil n’est visible que des inscrits', function () {
    $this->awa->profile->update(['visibility' => 'members']);

    $this->actingAs($this->awa)->get($this->url)->assertSeeText('Votre profil n’est visible que des personnes inscrites.');
});

it('ouvre le Carrefour Personnes filtré sur la capacité touchée, dans le même sens', function () {
    $html = $this->get($this->url)->getContent();

    expect($html)->toContain(e(route('personnes.index', ['q' => 'Comptabilité', 'sens' => 'offered'])))
        ->and($html)->toContain(e(route('personnes.index', ['q' => 'Anglais', 'sens' => 'wanted'])));

    $this->get(route('personnes.index', ['q' => 'Comptabilité', 'sens' => 'offered']))->assertOk()->assertSeeText('Awa Traoré');
});

it('invite le propriétaire d’un profil vide à le compléter, et dit simplement la vérité aux autres', function () {
    $vide = User::factory()->create(['name' => 'Personne Vide']);
    $url = route('personnes.show', $vide->profile->public_id);

    $this->actingAs($vide)->get($url)->assertSeeText('Votre profil est encore vide.')->assertSeeText('Compléter mon profil');
    $this->actingAs(User::factory()->create())->get($url)->assertSeeText('n’a pas encore indiqué de capacité')->assertDontSeeText('Compléter mon profil');
});

it('n’affiche ni domaines ni lieu ni présentation quand ils manquent', function () {
    $simple = person('Simple', ['Couture']);
    $page = $this->get(route('personnes.show', $simple->profile->public_id))->assertOk();

    $page->assertDontSeeText('Domaines qui l’intéressent');
    expect($page->getContent())->not->toContain('Côte d’Ivoire');
});

it('protège contre l’injection de HTML dans le nom, la présentation et les capacités', function () {
    $pirate = person('<script>alert(1)</script>', ['<img src=x onerror=alert(2)>'], bio: '<b onmouseover=alert(3)>gras</b>');
    $page = $this->get(route('personnes.show', $pirate->profile->public_id))->assertOk()->getContent();

    expect($page)->not->toContain('<script>alert(1)')->not->toContain('<img src=x')->not->toContain('<b onmouseover')
        ->and($page)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');

    $liste = $this->get(route('personnes.index', ['q' => 'img']))->assertOk()->getContent();
    expect($liste)->not->toContain('<img src=x');
});

it('garde les retours à la ligne de la présentation sans accepter de HTML', function () {
    $this->get($this->url)->assertSee('whitespace-pre-line', false)->assertSee("Comptable de formation.\nJ’aide", false);
});

it('affiche les initiales quand il n’y a pas de photo, la photo sinon', function () {
    $this->get($this->url)->assertSee('>AT</span>', false);

    $this->awa->profile->update(['photo_path' => 'profile-photos/x.jpg']);
    $this->get($this->url)->assertSee(route('personnes.photo', ['profile' => $this->awa->profile->public_id, 'v' => substr(md5('profile-photos/x.jpg'), 0, 8)]), false)->assertDontSee('>AT</span>', false);
});

it('donne une ou deux initiales', function () {
    expect(User::factory()->make(['name' => 'Awa Traoré'])->initials())->toBe('AT')
        ->and(User::factory()->make(['name' => 'zakaria'])->initials())->toBe('Z')
        ->and(User::factory()->make(['name' => 'Jean Pierre Kouassi'])->initials())->toBe('JP')
        ->and(User::factory()->make(['name' => '  élodie   écot '])->initials())->toBe('ÉÉ');
});

it('répond 404 pour un identifiant inconnu', function () {
    $this->get('/personnes/zzzzzzzzzzzz')->assertNotFound();
});

it('ramène au Carrefour par le lien de retour', function () {
    $this->get($this->url)->assertSee('href="'.route('personnes.index').'"', false)->assertSeeText('Carrefour Personnes');
});
