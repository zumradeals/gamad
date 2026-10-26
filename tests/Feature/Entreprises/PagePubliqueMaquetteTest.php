<?php

use App\Enums\PublicationType;
use App\Models\Company;
use App\Models\User;
use App\Services\Publications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function laEntreprise(string $name = 'Atelier Numérique', ?User $owner = null, bool $verified = true, array $extra = []): Company
{
    $company = new Company(['name' => $name, 'legal_name' => $name.' SARL', 'registration_country' => 'CI', 'registration_number' => 'RCCM-'.Str::random(10),
        'sector' => 'Informatique et réseaux', 'city' => 'Yamoussoukro', 'summary' => 'Installation et maintenance informatique.'] + $extra);
    $company->slug = Str::slug($name).'-'.strtolower(Str::random(8));
    $company->forceFill([
        'owner_id' => ($owner ?? User::factory()->create())->id,
        'status' => $verified ? Company::VERIFIED : 'pending',
        'verified_at' => $verified ? now() : null,
    ])->save();

    return $company;
}

function publieEntreprise(Company $company, PublicationType $type, string $body, string $visibility = 'public')
{
    return app(Publications::class)->publish($company->owner, $type, ['body' => $body, 'visibility' => $visibility], company: $company);
}

beforeEach(function () {
    $this->moi = User::factory()->create(['name' => 'Moi Même']);
    $this->entreprise = laEntreprise('Atelier Numérique');
    $this->url = route('entreprises.show', $this->entreprise);
});

it('range le contenu en deux colonnes égales sous la carte d’identité, sur une page élargie', function () {
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('lg:grid-cols-2')->not->toContain('lg:grid-cols-[minmax(0,1fr)_320px]')->toContain('max-w-[1480px]');
});

it('affiche le badge bleu seulement pour une entreprise vérifiée ; une non vérifiée n’apparaît jamais comme vérifiée', function () {
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Immatriculation vérifiée par GAMAD')->assertSee('bg-link');

    $attente = laEntreprise('En attente', verified: false);
    $this->actingAs($this->moi)->get(route('entreprises.show', $attente))->assertNotFound();
    $this->actingAs($attente->owner)->get(route('entreprises.show', $attente))->assertOk()
        ->assertDontSeeText('Immatriculation vérifiée par GAMAD')->assertSeeText('Contrôle administratif en attente');
});

it('montre au visiteur Contacter, Suivre et le menu ⋯ ; au propriétaire Modifier la page et Partager, sans Contacter', function () {
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Contacter')->assertSeeText('Suivre')
        ->assertSee('Autres actions pour cette page', false)->assertSeeText('Partager la page')->assertSeeText('Signaler cette page entreprise')
        ->assertDontSeeText('Modifier la page');

    $this->actingAs($this->entreprise->owner)->get($this->url)->assertSeeText('Modifier la page')->assertSeeText('Partager la page')
        ->assertSee(route('entreprises.branding.edit', $this->entreprise), false)
        ->assertDontSeeText('Contacter')->assertDontSeeText('Signaler cette page entreprise');
});

it('invite un visiteur sans compte à se connecter pour contacter ou suivre', function () {
    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('href="'.route('login').'"')->not->toContain('Modifier la page');
});

it('suit et ne suit plus l’entreprise par le formulaire existant', function () {
    $this->actingAs($this->moi)->post(route('entreprises.follow', $this->entreprise))->assertRedirect();
    expect(DB::table('company_follows')->where('user_id', $this->moi->id)->count())->toBe(1);
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Abonné à la page');
    $this->actingAs($this->moi)->post(route('entreprises.follow', $this->entreprise));
    expect(DB::table('company_follows')->count())->toBe(0);
});

it('affiche des statistiques réelles seulement quand elles ne sont pas nulles', function () {
    $page = $this->actingAs($this->moi)->get($this->url)->assertOk();
    expect($page->viewData('stats'))->toBe([]);

    publieEntreprise($this->entreprise, PublicationType::Share, "Une actualité\nDétail");
    publieEntreprise($this->entreprise, PublicationType::PersonalWork, "Une réalisation\nDétail");
    publieEntreprise($this->entreprise, PublicationType::Share, "Une autre actualité\nDétail");
    opportunite($this->entreprise);
    DB::table('company_follows')->insert(['user_id' => $this->moi->id, 'company_id' => $this->entreprise->id, 'created_at' => now()]);

    expect($this->actingAs($this->moi)->get($this->url)->viewData('stats'))->toBe(['Publications' => 3, 'Réalisations' => 1, 'Opportunités' => 1, 'Abonnés' => 1]);
});

it('sépare réalisations, opportunités et publications : les opportunités viennent du module Opportunités', function () {
    publieEntreprise($this->entreprise, PublicationType::PersonalWork, "Réseau d’un marché\nInstallation en deux jours.");
    opportunite($this->entreprise, ['title' => 'Technicien réseau recherché', 'kind' => 'mission']);
    publieEntreprise($this->entreprise, PublicationType::Share, "Nouveaux locaux\nNous avons déménagé.");

    $page = $this->actingAs($this->moi)->get($this->url)->assertOk();
    expect($page->viewData('works'))->toHaveCount(1)->and($page->viewData('offers'))->toHaveCount(1)->and($page->viewData('companyPosts'))->toHaveCount(2);
    $page->assertSeeText('Réseau d’un marché')->assertSeeText('Technicien réseau recherché')->assertSeeText('Installation en deux jours.')
        ->assertSeeText('Voir la réalisation');
});

it('ne montre plus une opportunité clôturée, brouillon ou expirée, ni une publication masquée', function () {
    opportunite($this->entreprise, ['title' => 'Offre à clore', 'status' => 'closed']);
    opportunite($this->entreprise, ['title' => 'Offre en brouillon', 'status' => 'draft']);
    opportunite($this->entreprise, ['title' => 'Offre expirée', 'deadline' => now('Africa/Abidjan')->subDay()->toDateString()]);
    $masquee = publieEntreprise($this->entreprise, PublicationType::Share, "Actualité masquée\nDétail");
    $masquee->forceFill(['hidden_at' => now(), 'hidden_reason' => 'Modération'])->save();

    $page = $this->actingAs($this->moi)->get($this->url)->assertOk();
    expect($page->viewData('offers'))->toHaveCount(0);
    $page->assertDontSeeText('Actualité masquée')->assertSeeText('Aucune opportunité en cours pour le moment.');
});

it('refuse toute publication d’entreprise non publique et la montre à un visiteur sans compte', function () {
    expect(fn () => publieEntreprise($this->entreprise, PublicationType::Share, "Réservée\nAux inscrits.", 'members'))->toThrow(ValidationException::class);
    publieEntreprise($this->entreprise, PublicationType::Share, "Ouverte\nÀ tous.");

    $this->get($this->url)->assertSeeText('Ouverte')->assertDontSeeText('Réservée');
});

it('affiche des états vides sobres, sans offre ni réalisation inventée', function () {
    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Aucune réalisation publiée pour le moment.')->assertSeeText('Aucune opportunité en cours pour le moment.')
        ->assertSeeText('n’a pas encore présenté ses produits et services')->assertSeeText('n’a pas encore publié')
        ->assertDontSeeText('Publier une réalisation');
    $this->actingAs($this->entreprise->owner)->get($this->url)->assertSeeText('Publier une réalisation')->assertSeeText('Présenter mes produits et services');
});

it('présente les produits et services déclarés par l’entreprise, sans panier ni paiement', function () {
    $this->entreprise->forceFill(['services' => [['name' => 'Maintenance', 'category' => 'Services', 'description' => 'Suivi du parc informatique.'], ['name' => 'Formation', 'category' => '', 'description' => '']]])->save();

    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->assertSeeText('Maintenance')->assertSeeText('Suivi du parc informatique.')->assertSeeText('Formation')->getContent();
    expect($html)->not->toContain('Ajouter au panier')->not->toContain('Acheter')->toContain('GAMAD ne vend rien');
});

it('permet au propriétaire seulement d’enregistrer ses produits et services, validés et plafonnés', function () {
    $services = [['name' => '  Maintenance  ', 'category' => 'Services', 'description' => 'Suivi.'], ['name' => '', 'category' => 'Vide', 'description' => 'Sans nom']];

    $this->actingAs($this->moi)->post(route('entreprises.branding.update', $this->entreprise), ['services' => $services])->assertForbidden();
    expect($this->entreprise->fresh()->services)->toBeNull();

    $this->actingAs($this->entreprise->owner)->post(route('entreprises.branding.update', $this->entreprise), ['services' => $services])->assertRedirect();
    expect($this->entreprise->fresh()->services)->toBe([['name' => 'Maintenance', 'category' => 'Services', 'description' => 'Suivi.']]);

    $trop = array_fill(0, Company::MAX_SERVICES + 1, ['name' => 'X', 'category' => '', 'description' => '']);
    $this->actingAs($this->entreprise->owner)->post(route('entreprises.branding.update', $this->entreprise), ['services' => $trop])->assertSessionHasErrors('services');
    $this->actingAs($this->entreprise->owner)->post(route('entreprises.branding.update', $this->entreprise), ['services' => [['name' => str_repeat('a', 81)]]])->assertSessionHasErrors('services.0.name');

    // Une mise à jour sans la clé « services » laisse la vitrine intacte.
    $this->actingAs($this->entreprise->owner)->post(route('entreprises.branding.update', $this->entreprise), ['summary' => 'Nouveau résumé'])->assertRedirect();
    expect($this->entreprise->fresh()->services)->toHaveCount(1);
});

it('affiche la rubrique Équipe seulement pour les personnes qui l’ont choisi, jamais par simple déduction', function () {
    $this->actingAs($this->moi)->get($this->url)->assertDontSeeText('Équipe');
    $html = $this->actingAs($this->moi)->get($this->url)->getContent();
    expect($html)->not->toContain('href="#equipe"');

    $membre = person('Membre Confirmé', ['Couture']);
    DB::table('company_experiences')->insert(['id' => (string) Str::uuid(), 'company_id' => $this->entreprise->id, 'user_id' => $membre->id, 'relationship' => 'employee', 'title' => 'Technicienne', 'period' => '2024',
        'description' => null, 'status' => 'confirmed', 'show_on_company_page' => true, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($this->moi)->get($this->url)->assertSeeText('Équipe')->assertSeeText('Membre Confirmé')->assertSee('href="#equipe"', false);
})->skip(fn () => ! Schema::hasColumn('company_experiences', 'show_on_company_page'), 'colonne absente');

it('ne montre ni numéro d’immatriculation, ni pièce, ni e-mail privé', function () {
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();

    expect($html)->not->toContain($this->entreprise->registration_number)->not->toContain($this->entreprise->owner->email)->not->toContain('rccm')->not->toContain('private_path');
});

it('relie les six rubriques par ancres avec une rubrique active identifiable', function () {
    publieEntreprise($this->entreprise, PublicationType::Share, "Une actualité\nDétail");
    $html = $this->actingAs($this->moi)->get($this->url)->assertOk()->getContent();

    foreach (['#presentation', '#realisations', '#produits-services', '#opportunites', '#publications'] as $ancre) {
        expect($html)->toContain('href="'.$ancre.'"');
    }
    expect($html)->toContain("x-bind:aria-current=\"active === 'opportunites'")->toContain('IntersectionObserver');
});

it('conserve la route publique et le blocage d’une page suspendue', function () {
    $this->get($this->url)->assertOk();
    $this->entreprise->forceFill(['status' => 'suspended', 'verified_at' => null])->save();
    $this->get($this->url)->assertNotFound();
});
