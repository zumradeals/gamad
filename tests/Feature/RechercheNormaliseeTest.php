<?php

use App\Enums\PublicationType;
use App\Livewire\CompanyDirectory;
use App\Livewire\Fil;
use App\Models\Company;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function entrepriseDeRecherche(string $name, string $sector, ?string $city, ?string $summary = null): Company
{
    $company = new Company(['name' => $name, 'legal_name' => $name.' SARL', 'registration_country' => 'CI',
        'registration_number' => Str::random(12), 'sector' => $sector, 'city' => $city, 'summary' => $summary]);
    $company->slug = Str::slug($name).'-'.strtolower(Str::random(8));
    $company->forceFill(['owner_id' => User::factory()->create()->id, 'status' => Company::VERIFIED, 'verified_at' => now()])->save();

    return $company;
}

function nomsDeRecherche($composant): array
{
    return $composant->viewData('companies')->getCollection()->pluck('name')->all();
}

// Recherche normalisée unifiée (audit A7, lot C7) : même résultat avec ou sans accents, en majuscules ou en minuscules, dans le
// Fil, l'annuaire des entreprises et le Carrefour Besoins — comme dans Personnes et Opportunités.

it('donne en SQL la même clé que Normalizer::key pour l’écriture courante du français', function () {
    foreach (['Comptabilité', 'ÉLECTRICIEN', 'garçon', 'cœur', 'L’atelier de Noël', 'À côté', 'naïve', 'Œuvre', 'Ça va', 'zone d’Abidjan'] as $texte) {
        $sql = DB::selectOne('select '.Normalizer::sqlKey('?::text').' as k', [$texte])->k;

        expect($sql)->toBe(Normalizer::key($texte), "« $texte »");
    }
});

it('trouve une publication du Fil sans tenir compte des accents ni de la casse dans le texte', function () {
    $viewer = person('Moi Même');
    $auteur = person('Awa Traoré');
    publication($auteur, PublicationType::Need, ['body' => 'Besoin d’un électricien à Abidjan pour une installation.']);
    publication($auteur, PublicationType::Need, ['body' => 'Autre texte sans rapport.']);

    foreach (['electricien', 'ÉLECTRICIEN', 'Electricien a abidjan', 'l’électricien'] as $saisie) {
        $texte = Livewire::actingAs($viewer)->test(Fil::class)->set('query', $saisie)->viewData('posts')->pluck('body')->all();
        if ($saisie === 'l’électricien') {
            expect($texte)->toBe([], 'une expression qui n’est pas dans le texte ne trouve rien');

            continue;
        }
        expect($texte)->toHaveCount(1, $saisie)->and($texte[0])->toContain('électricien');
    }
});

it('trouve les entreprises de l’annuaire sans accents ni casse (nom, secteur, localité, présentation)', function () {
    entrepriseDeRecherche('Société Générale d’Électricité', 'Énergie et télécommunications', 'Yamoussoukro', summary: 'Installation et dépannage électrique.');
    entrepriseDeRecherche('Autre Compagnie', 'Commerce', 'Abidjan');

    foreach (['societe generale', 'ENERGIE', 'telecommunications', 'yamoussoukro', 'electrique', 'd’électricité'] as $saisie) {
        $noms = nomsDeRecherche(Livewire::test(CompanyDirectory::class)->set('search', $saisie));
        expect($noms)->toBe(['Société Générale d’Électricité'], $saisie);
    }
    expect(nomsDeRecherche(Livewire::test(CompanyDirectory::class)->set('search', 'inconnu')))->toBe([]);
});

it('neutralise les caractères spéciaux de la saisie dans la recherche', function () {
    entrepriseDeRecherche('Alpha 100% Bio', 'Agriculture', 'Abidjan');
    entrepriseDeRecherche('Beta Services', 'Commerce', 'Abidjan');

    expect(nomsDeRecherche(Livewire::test(CompanyDirectory::class)->set('search', '100%')))->toBe(['Alpha 100% Bio'])
        ->and(nomsDeRecherche(Livewire::test(CompanyDirectory::class)->set('search', '_')))->toBe([])
        ->and(Str::length(Normalizer::sqlKey('x')))->toBeGreaterThan(0);
});
