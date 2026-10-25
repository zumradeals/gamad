<?php

use App\Enums\PublicationType;
use App\Livewire\PersonContact;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\People;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->awa = person('Awa Traoré', ['Comptabilité', 'Tableur Excel'], ['Anglais'], 'Yamoussoukro', domains: ['Économie et Finances'], bio: 'Comptable de formation.');
    $this->moi = person('Moi Même', ['Anglais'], ['Comptabilité'], 'Bouaké');
    $this->url = route('personnes.show', $this->awa->profile->public_id);
});

it('range le contenu en deux colonnes égales sous la carte d’identité, sur une page élargie', function () {
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('lg:grid-cols-2')->not->toContain('lg:grid-cols-[minmax(0,1fr)_320px]')->toContain('max-w-[1480px]');
});

it('répartit les sections : savoir-faire, réalisations, parcours à gauche ; apprendre, publications, intérêts à droite', function () {
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();
    $colonnes = explode('lg:grid-cols-2', $html, 2)[1];

    foreach (['id="savoir-faire"', 'id="realisations"', 'id="parcours"'] as $gauche) {
        expect(strpos($colonnes, $gauche))->toBeInt();
    }
    expect(strpos($colonnes, 'id="savoir-faire"'))->toBeLessThan(strpos($colonnes, 'id="realisations"'))
        ->and(strpos($colonnes, 'id="realisations"'))->toBeLessThan(strpos($colonnes, 'id="parcours"'))
        ->and(strpos($colonnes, 'id="parcours"'))->toBeLessThan(strpos($colonnes, 'id="apprend"'))
        ->and(strpos($colonnes, 'id="apprend"'))->toBeLessThan(strpos($colonnes, 'id="publications"'))
        ->and(strpos($colonnes, 'id="publications"'))->toBeLessThan(strpos($colonnes, 'id="domaines"'));
});

it('affiche au visiteur connecté Contacter, Suivre et le menu ⋯, sans les actions du propriétaire', function () {
    $this->actingAs($this->moi)->get($this->url)->assertOk()
        ->assertSeeText('Contacter')->assertSeeText('Suivre')->assertSee('Autres actions pour ce profil', false)
        ->assertSeeText('Partager le profil')->assertSeeText('Signaler ce profil')
        ->assertDontSeeText('Modifier mon profil')->assertDontSeeText('Partager mon profil');
});

it('affiche au propriétaire Modifier, Partager et Mon espace, sans Contacter ni Suivre', function () {
    $this->actingAs($this->awa)->get($this->url)->assertOk()
        ->assertSeeText('Modifier mon profil')->assertSeeText('Partager mon profil')->assertSee(route('espace'), false)
        ->assertDontSeeText('Contacter')->assertDontSeeText('Suivre')->assertDontSeeText('Signaler ce profil');
});

it('propose à un visiteur sans compte de se connecter et de partager, sans Contacter ni Suivre', function () {
    $this->get($this->url)->assertOk()->assertSeeText('Se connecter pour suivre ou entrer en relation')->assertSeeText('Partager le profil')
        ->assertDontSeeText('Modifier mon profil')->assertDontSeeText('Signaler ce profil');
});

it('suit, ne suit plus et demande un contact depuis la carte d’identité', function () {
    Livewire\Livewire::actingAs($this->moi)->test(PersonContact::class, ['profile' => $this->awa->profile])
        ->call('follow', true)->assertSeeText('Ne plus suivre');
    expect(DB::table('person_follows')->where('user_id', $this->moi->id)->where('followed_id', $this->awa->id)->exists())->toBeTrue();

    Livewire\Livewire::actingAs($this->moi)->test(PersonContact::class, ['profile' => $this->awa->profile])
        ->call('follow', false)->set('note', 'Bonjour, je souhaiterais échanger sur la comptabilité.')->call('request')->assertHasNoErrors();
    expect(DB::table('person_follows')->count())->toBe(0)->and(DB::table('person_connections')->where('state', 'pending')->count())->toBe(1);
});

it('affiche seulement les compteurs publics non nuls et jamais les relations', function () {
    expect($this->actingAs($this->moi)->get($this->url)->assertOk()->viewData('counts'))->toBe([]);

    publication($this->awa, PublicationType::Share, ['body' => 'Un partage']);
    publication($this->awa, PublicationType::PersonalWork, ['body' => "Un titre\nUne description"]);
    app(People::class)->request($this->moi, $this->awa, 'Bonjour');
    $page = $this->actingAs($this->moi)->get($this->url)->assertOk();

    expect($page->viewData('counts'))->toBe(['Publications' => 2, 'Réalisations' => 1]);
    $page->assertDontSeeText('Relations');
});

it('présente jusqu’à trois réalisations en cartes, avec titre et description, et un bouton pour toutes', function () {
    foreach (range(1, 5) as $i) {
        publication($this->awa, PublicationType::PersonalWork, ['body' => "Réalisation $i\nDescription de la réalisation $i."]);
    }
    $page = $this->actingAs($this->moi)->get($this->url)->assertOk();

    expect($page->viewData('showcasePosts'))->toHaveCount(3)->and($page->viewData('moreWorks'))->toHaveCount(2);
    $page->assertSeeText('Voir toutes les réalisations (5)')->assertSeeText('Description de la réalisation 5.');
});

it('affiche un état vide sobre pour les réalisations, adapté au visiteur et au propriétaire', function () {
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Aucune réalisation à afficher pour le moment.')->assertDontSeeText('Partager une réalisation');
    $this->actingAs($this->awa)->get($this->url)->assertSeeText('Aucune réalisation à afficher pour le moment.')->assertSeeText('Partager une réalisation');
});

it('présente le parcours en chronologie avec période, intitulé et description, sans rien inventer', function () {
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Aucune expérience renseignée pour le moment.');

    $this->awa->profile->forceFill(['experiences' => [
        ['kind' => 'professional', 'title' => 'Comptable', 'period' => '2022 – 2024', 'description' => 'Tenue de comptes.'],
        ['kind' => 'volunteer', 'title' => 'Trésorière', 'period' => '2020', 'description' => ''],
        ['kind' => 'learning', 'title' => 'Formation', 'period' => '2019', 'description' => 'Un cours.'],
        ['kind' => 'personal', 'title' => 'Quatrième', 'period' => '2018', 'description' => ''],
    ]])->save();

    $this->actingAs($this->moi)->get($this->url)->assertSeeText('2022 – 2024')->assertSeeText('Comptable')->assertSeeText('Tenue de comptes.')
        ->assertSeeText('Voir tout mon parcours (4)')->assertDontSeeText('Aucune expérience renseignée');
});

it('respecte la visibilité des publications : une publication réservée aux inscrits n’apparaît pas à un visiteur', function () {
    publication($this->awa, PublicationType::Share, ['body' => 'Réservée aux inscrits', 'visibility' => 'members']);
    publication($this->awa, PublicationType::Share, ['body' => 'Ouverte à tous', 'visibility' => 'public']);

    $this->get($this->url)->assertSeeText('Ouverte à tous')->assertDontSeeText('Réservée aux inscrits');
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Réservée aux inscrits')->assertSeeText('Ouverte à tous');
});

it('ne montre jamais une publication masquée', function () {
    $masquee = publication($this->awa, PublicationType::Share, ['body' => 'Publication masquée']);
    $masquee->forceFill(['hidden_at' => now(), 'hidden_reason' => 'Masquée par la modération'])->save();

    $this->actingAs($this->moi)->get($this->url)->assertDontSeeText('Publication masquée');
});

it('calcule les capacités communes à partir des deux profils, seulement quand il y en a', function () {
    $page = $this->actingAs($this->moi)->get($this->url)->assertOk();

    expect($page->viewData('common'))->toBe(['theyBring' => ['Comptabilité'], 'youBring' => ['Anglais']]);
    $page->assertSeeText('Peut vous apporter ce que vous voulez apprendre')->assertSeeText('Veut apprendre ce que vous pouvez apporter');

    $autre = person('Sans lien', ['Couture']);
    $this->actingAs($autre)->get($this->url)->assertDontSeeText('Peut vous apporter ce que vous voulez apprendre');
    expect($this->actingAs($autre)->get($this->url)->viewData('common'))->toBeNull();
    $this->actingAs($this->awa)->get($this->url)->assertDontSeeText('Capacités que vous partagez');
});

it('n’affiche le badge de vérification que pour une identité réellement vérifiée', function () {
    $this->actingAs($this->moi)->get($this->url)->assertDontSeeText('Identité personnelle vérifiée par GAMAD');

    IdentityVerification::query()->create(['user_id' => $this->awa->id, 'state' => 'verified', 'checked_name' => $this->awa->name, 'method' => 'in_person', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Identité personnelle vérifiée par GAMAD');
});

it('n’affiche aucun statut « en ligne », « disponible », e-mail, téléphone ni compteur de popularité', function () {
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();

    expect($html)->not->toContain('En ligne')->not->toContain('Disponible')->not->toContain($this->awa->email)->not->toContain('J’aime')->not->toContain('abonnés');
});

it('navigue entre les quatre rubriques par ancres, avec une rubrique active identifiable', function () {
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();

    foreach (['#savoir-faire', '#realisations', '#parcours', '#publications'] as $ancre) {
        expect($html)->toContain('href="'.$ancre.'"');
    }
    expect($html)->toContain("x-bind:aria-current=\"active === 'realisations'")->toContain('IntersectionObserver');
});

it('conserve la route publique, la photo et le blocage', function () {
    $this->get($this->url)->assertOk();
    app(People::class)->block($this->awa, $this->moi, true);
    $this->actingAs($this->moi)->get($this->url)->assertNotFound();
});

it('affiche un profil minimal sans erreur : aucune capacité, réalisation ni expérience', function () {
    $vide = User::factory()->create();
    $this->actingAs($this->moi)->get(route('personnes.show', $vide->ensureProfile()->public_id))->assertOk()->assertSeeText('n’a pas encore indiqué de capacité');
});
