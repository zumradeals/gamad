<?php

use App\Livewire\CompanyDirectory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function entreprise(string $name, string $sector, ?string $city, ?User $owner = null, bool $verified = true, ?string $summary = null): Company
{
    $company = new Company(['name' => $name, 'legal_name' => $name.' SARL', 'registration_country' => 'CI',
        'registration_number' => Str::random(12), 'sector' => $sector, 'city' => $city, 'summary' => $summary]);
    $company->slug = Str::slug($name).'-'.strtolower(Str::random(8));
    $company->forceFill([
        'owner_id' => ($owner ?? User::factory()->create())->id,
        'status' => $verified ? Company::VERIFIED : 'pending',
        'verified_at' => $verified ? now() : null,
    ])->save();

    return $company;
}

function annuaire(?User $viewer = null)
{
    return $viewer ? Livewire::actingAs($viewer)->test(CompanyDirectory::class) : Livewire::test(CompanyDirectory::class);
}

function nomsEntreprises($composant): array
{
    return $composant->viewData('companies')->getCollection()->pluck('name')->all();
}

beforeEach(function () {
    $this->moi = person('Moi Même', ['Comptabilité'], [], 'Bouaké');
    $this->alpha = entreprise('Alpha Compta', 'Comptabilité et gestion', 'Bouaké', summary: 'Tenue de comptes pour petits commerces.');
    $this->beta = entreprise('Beta Énergie', 'Énergie solaire', 'Abidjan');
    $this->gamma = entreprise('Gamma Énergie', 'énergie solaire', ' abidjan ');
    $this->attente = entreprise('Delta En Attente', 'Énergie solaire', 'Abidjan', verified: false);
});

it('ne liste que les entreprises vérifiées, par ordre alphabétique, avec le nombre réel', function () {
    expect(nomsEntreprises(annuaire()))->toBe(['Alpha Compta', 'Beta Énergie', 'Gamma Énergie']);
    annuaire()->assertSeeText('3 entreprises trouvées')->assertDontSeeText('Delta En Attente');
});

it('affiche le badge de vérification sur chaque carte listée, jamais pour une page non vérifiée', function () {
    annuaire()->assertSeeText('Immatriculation vérifiée par GAMAD');
    expect(annuaire()->html())->not->toContain('Delta En Attente');
});

it('trie explicitement et retombe sur A→Z pour une valeur inconnue', function () {
    expect(nomsEntreprises(annuaire()->set('sort', 'nom-desc')))->toBe(['Gamma Énergie', 'Beta Énergie', 'Alpha Compta'])
        ->and(nomsEntreprises(annuaire()->set('sort', 'recents')))->toHaveCount(3)
        ->and(nomsEntreprises(annuaire()->set('sort', 'pertinence')))->toBe(['Alpha Compta', 'Beta Énergie', 'Gamma Énergie']);
    annuaire()->assertDontSeeText('Pertinence');
});

it('cherche par nom, secteur, ville ou présentation', function () {
    expect(nomsEntreprises(annuaire()->set('search', 'alpha')))->toBe(['Alpha Compta'])
        ->and(nomsEntreprises(annuaire()->set('search', 'solaire')))->toBe(['Beta Énergie', 'Gamma Énergie'])
        ->and(nomsEntreprises(annuaire()->set('search', 'bouaké')))->toBe(['Alpha Compta'])
        ->and(nomsEntreprises(annuaire()->set('search', 'petits commerces')))->toBe(['Alpha Compta'])
        ->and(nomsEntreprises(annuaire()->set('search', '%')))->toBe([])
        ->and(nomsEntreprises(annuaire()->set('search', "'; drop table companies; --")))->toBe([]);
});

it('compte réellement les secteurs et localités, sans distinguer casse ni espaces, et filtre', function () {
    $p = annuaire();
    expect($p->viewData('sectors')->pluck('total', 'facet_key')->all())->toBe(['comptabilité et gestion' => 1, 'énergie solaire' => 2])
        ->and($p->viewData('cities')->pluck('total', 'facet_key')->all())->toBe(['abidjan' => 2, 'bouaké' => 1]);

    expect(nomsEntreprises($p->set('sector', 'énergie solaire')))->toBe(['Beta Énergie', 'Gamma Énergie'])
        ->and(nomsEntreprises(annuaire()->set('city', 'bouaké')))->toBe(['Alpha Compta'])
        ->and(nomsEntreprises(annuaire()->set('city', 'abidjan')->set('sector', 'comptabilité et gestion')))->toBe([]);

    // Un compteur tient compte des autres filtres, pas du sien.
    expect(annuaire()->set('city', 'bouaké')->viewData('sectors')->pluck('total', 'facet_key')->all())->toBe(['comptabilité et gestion' => 1]);
});

it('ne compte pas les entreprises non vérifiées dans les compteurs', function () {
    expect(annuaire()->viewData('sectors')->sum('total'))->toBe(3);
});

it('paginera par 10 et revient à la première page quand la recherche change', function () {
    foreach (range(1, 12) as $i) {
        entreprise(sprintf('Zeta %02d', $i), 'Poterie', 'Korhogo');
    }

    $p = annuaire()->set('search', 'zeta');
    expect(nomsEntreprises($p))->toHaveCount(10)->and(nomsEntreprises($p)[0])->toBe('Zeta 01');
    $p->assertSeeText('Page 1 sur 2')->assertSeeText('12 entreprises trouvées');
    $p->call('nextPage');
    expect(nomsEntreprises($p))->toBe(['Zeta 11', 'Zeta 12']);
    $p->set('search', 'zet');
    expect(nomsEntreprises($p))->toHaveCount(10);
});

it('ne montre l’onglet « Recherche des compétences » que pour une opportunité publique réellement publiée', function () {
    expect(nomsEntreprises(annuaire()->call('setMode', 'opportunities')))->toBe([]);
    annuaire()->call('setMode', 'opportunities')->assertSeeText('Aucune entreprise ne recherche de compétences pour le moment.');

    $opportunite = opportunite($this->alpha, ['title' => 'Comptable', 'description' => 'Nous cherchons un comptable.']);

    $p = annuaire()->call('setMode', 'opportunities');
    expect(nomsEntreprises($p))->toBe(['Alpha Compta']);
    $p->assertSeeText('Recherche des compétences');

    // Clôturée : n'apparaît plus.
    $opportunite->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
    expect(nomsEntreprises(annuaire()->call('setMode', 'opportunities')))->toBe([]);
});

it('ignore un onglet ou une liste inconnus et réserve les listes personnelles aux personnes connectées', function () {
    annuaire()->call('setMode', 'x')->assertSet('mode', 'all');
    annuaire()->assertDontSeeText('Entreprises suivies')->assertDontSeeText('Autour de moi');
    expect(nomsEntreprises(annuaire()->set('list', 'suivies')))->toHaveCount(3);
    annuaire($this->moi)->assertSeeText('Entreprises suivies')->assertSeeText('Suggestions')->assertSeeText('Autour de moi');
});

it('suit et cesse de suivre une entreprise, et liste les entreprises suivies', function () {
    $p = annuaire($this->moi)->call('follow', $this->beta->id, true);
    expect(DB::table('company_follows')->where('user_id', $this->moi->id)->where('company_id', $this->beta->id)->exists())->toBeTrue();
    expect(nomsEntreprises(annuaire($this->moi)->call('setList', 'suivies')))->toBe(['Beta Énergie']);
    $p->assertSeeText('Suivie');

    $p->call('follow', $this->beta->id, false);
    expect(DB::table('company_follows')->count())->toBe(0);
    annuaire($this->moi)->call('setList', 'suivies')->assertSeeText('Vous ne suivez encore aucune entreprise.');
});

it('refuse de suivre sans compte, avec une adresse non vérifiée ou une entreprise non vérifiée', function () {
    annuaire()->call('follow', $this->beta->id, true)->assertForbidden();
    annuaire(User::factory()->unverified()->create())->call('follow', $this->beta->id, true)->assertForbidden();
    annuaire($this->moi)->call('follow', $this->attente->id, true)->assertNotFound();
    expect(DB::table('company_follows')->count())->toBe(0);
});

it('montre « Autour de moi » par la localité saisie identique à celle de la personne, sans distance', function () {
    expect(nomsEntreprises(annuaire($this->moi)->call('setList', 'autour')))->toBe(['Alpha Compta']);
    annuaire($this->moi)->assertSeeText('Entreprises près de vous')->assertSeeText('Même localité déclarée');

    $sans = person('Sans localité', ['Couture']);
    annuaire($sans)->assertDontSeeText('Entreprises près de vous');
    annuaire($sans)->call('setList', 'autour')->assertSeeText('Indiquez votre localité');
});

it('suggère des entreprises avec une raison explicable, à partir des capacités proposées', function () {
    $suggestions = annuaire($this->moi)->viewData('suggestions');
    expect($suggestions->pluck('name')->all())->toBe(['Alpha Compta'])
        ->and($suggestions->first()->reason)->toBe('Votre capacité « Comptabilité » correspond à son secteur');
    annuaire($this->moi)->assertSeeText('Suggestions pour vous');

    expect(nomsEntreprises(annuaire($this->moi)->call('setList', 'suggestions')))->toBe(['Alpha Compta']);
    $sans = person('Sans capacité');
    annuaire($sans)->assertDontSeeText('Suggestions pour vous');
    annuaire($sans)->call('setList', 'suggestions')->assertSeeText('Aucune suggestion pour le moment.');
});

it('ne propose pas à une personne sa propre entreprise ni de la suivre', function () {
    $mienne = entreprise('Ma Compta', 'Comptabilité', 'Bouaké', $this->moi);

    expect(annuaire($this->moi)->viewData('suggestions')->pluck('name')->all())->not->toContain('Ma Compta');
    $html = annuaire($this->moi)->html();
    expect($html)->toContain('Votre entreprise')->and(substr_count($html, "follow('".$mienne->id."'"))->toBe(0);
});

it('relie le menu d’actions à des fonctions réelles et le masque aux visiteurs', function () {
    $visiteur = annuaire()->html();
    expect($visiteur)->not->toContain('Signaler cette entreprise')->not->toContain('Contacter l’entreprise');

    $html = annuaire($this->moi)->html();
    expect($html)->toContain(route('entreprises.contact', $this->beta))->toContain(route('entreprises.reports.create', $this->beta))
        ->toContain(route('entreprises.show', $this->beta));
});

it('adapte le bloc « Faites partie du réseau » : compte, création ou gestion de l’entreprise', function () {
    annuaire()->assertSeeText('Créer un compte');
    annuaire($this->moi)->assertSeeText('Créer le profil de mon entreprise')->assertSee(route('entreprises.create'), false);

    entreprise('Ma Compta', 'Comptabilité', 'Bouaké', $this->moi);
    annuaire($this->moi)->assertSeeText('Gérer mon entreprise');
});

it('n’expose ni e-mail, ni numéro d’immatriculation, ni pièce, ni taille inventée', function () {
    $html = annuaire($this->moi)->html();

    expect($html)->not->toContain($this->alpha->registration_number)->not->toContain($this->alpha->legal_name)
        ->not->toContain($this->moi->email)->not->toContain('employés')->not->toContain('Recrute');
});

it('élargit la page comme Personnes (1480 px) sans toucher l’en-tête', function () {
    $html = $this->actingAs($this->moi)->get(route('entreprises.index'))->assertOk()->getContent();
    expect($html)->toContain('max-w-[1480px]')->toContain('max-w-[1240px]');
});

it('présente un état vide honnête sans exemple fictif', function () {
    Company::query()->delete();
    annuaire()->assertSeeText('Aucune entreprise à afficher pour l’instant.')->assertSeeText('Aucun exemple fictif');
});
