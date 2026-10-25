<?php

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\User;
use App\Services\CompanyExperienceService;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function verifiedCompanyForCareer(User $owner, bool $verify = true): Company
{
    $company = app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Entreprise pilote carrière',
        'legal_name' => 'Entreprise pilote carrière SARL',
        'registration_country' => 'CI',
        'registration_number' => 'CI-ABJ-CARRIERES-'.substr(str_replace('-', '', $owner->id), -12),
        'sector' => 'Télécommunications',
        'city' => 'Abidjan',
    ], ['rccm' => UploadedFile::fake()->create('rccm.pdf', 80, 'application/pdf')]);

    if ($verify) {
        app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 1, 'verify');
    }

    return $company->fresh();
}

function declareCareer(User $author, Company $company, array $data = []): CompanyExperience
{
    return app(CompanyExperienceService::class)->declare($author, $data + [
        'company_id' => $company->id,
        'relationship' => 'contractor',
        'title' => 'Technicien fibre',
        'period' => '2024 — 2025',
        'description' => 'Installation et maintenance de réseau.',
    ]);
}

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    Storage::fake('local');
});

it('n’autorise jamais une entreprise non vérifiée comme référence structurée', function () {
    $moussa = person('Moussa technicien');
    $pending = verifiedCompanyForCareer(person('Gestionnaire'), false);

    expect(fn () => declareCareer($moussa, $pending))->toThrow(ValidationException::class);
    expect(CompanyExperience::count())->toBe(0);
    $this->actingAs($moussa)->post(route('espace.company-experiences.store'), [
        'company_id' => $pending->id, 'relationship' => 'employee',
        'title' => 'Technicien réseaux', 'period' => '2025',
    ])->assertSessionHasErrors('company_id');
    expect(CompanyExperience::count())->toBe(0);
});

it('affiche la relation professionnelle avec la mention non confirmée, jamais le badge par défaut', function () {
    $owner = person('Page pilote');
    $moussa = person('Moussa Koné');
    $company = verifiedCompanyForCareer($owner);
    $experience = declareCareer($moussa, $company);

    expect($experience->status)->toBe('pending')->and($experience->confirmed())->toBeFalse();
    $this->get(route('personnes.show', $moussa->profile))->assertOk()
        ->assertSeeText('Technicien fibre')
        ->assertSeeText('Non encore confirmée par l’entreprise')
        ->assertDontSeeText('Confirmée par l’entreprise');
    $this->get(route('entreprises.show', $company))->assertOk()->assertDontSeeText('Moussa Koné');
});

it('réserve la confirmation au propriétaire de la page entreprise vérifiée', function () {
    $owner = person('Gestionnaire de page');
    $other = person('Autre membre');
    $moussa = person('Moussa référence');
    $company = verifiedCompanyForCareer($owner);
    $experience = declareCareer($moussa, $company);
    $service = app(CompanyExperienceService::class);

    expect(fn () => $service->decide($other, $experience, 1, 'confirm'))->toThrow(HttpException::class);
    expect($experience->fresh()->confirmed())->toBeFalse();

    $this->actingAs($other)->get(route('entreprises.experiences.review', $company))->assertForbidden();
    $this->actingAs($owner)->get(route('entreprises.experiences.review', $company))
        ->assertOk()->assertSeeText('Moussa référence')->assertSeeText('Technicien fibre');
    $this->actingAs($owner)->post(route('entreprises.experiences.decision', [$company, $experience]), [
        'revision' => 1, 'decision' => 'confirm',
    ])->assertRedirect(route('entreprises.experiences.review', $company));

    expect($experience->fresh()->confirmed())->toBeTrue();
    $this->get(route('personnes.show', $moussa->profile))->assertSeeText('Confirmée par l’entreprise');
    expect(fn () => $service->decide($owner, $experience->fresh(), 1, 'decline'))->toThrow(ValidationException::class);
});

it('respecte le consentement explicite et la visibilité du profil sur la page entreprise', function () {
    $owner = person('Entreprise vérifiée');
    $moussa = person('Prestataire déclaré');
    $company = verifiedCompanyForCareer($owner);
    $experience = declareCareer($moussa, $company);
    app(CompanyExperienceService::class)->decide($owner, $experience, 1, 'confirm');

    expect(contenuPrincipal($this->get(route('entreprises.show', $company))))->not->toContain('Prestataire déclaré');
    $this->actingAs($moussa)->post(route('espace.company-experiences.visibility', $experience), [
        'visible' => 1,
    ])->assertRedirect();
    $this->get(route('entreprises.show', $company))->assertSeeText('Prestataire déclaré');
    $moussa->profile->update(['visibility' => 'members']);
    auth()->logout();
    expect(contenuPrincipal($this->get(route('entreprises.show', $company))))->not->toContain('Prestataire déclaré');
    $this->actingAs($owner)->get(route('entreprises.show', $company))->assertSeeText('Prestataire déclaré');

    $this->actingAs($moussa)->post(route('espace.company-experiences.visibility', $experience), [
        'visible' => 0,
    ]);
    expect(contenuPrincipal($this->get(route('entreprises.show', $company))))->not->toContain('Prestataire déclaré');
});

it('interdit les actions sur le parcours des autres membres et les décisions sur une autre entreprise', function () {
    $ownerA = person('Entreprise Alpha');
    $ownerB = person('Entreprise Beta');
    $moussa = person('Moussa privé');
    $companyA = verifiedCompanyForCareer($ownerA);
    $companyB = verifiedCompanyForCareer($ownerB);
    $experience = declareCareer($moussa, $companyA);

    $this->actingAs($ownerB)->post(route('espace.company-experiences.withdraw', $experience))->assertForbidden();
    $this->actingAs($ownerB)->post(route('espace.company-experiences.visibility', $experience), ['visible' => 1])->assertForbidden();
    $this->actingAs($ownerB)->post(route('entreprises.experiences.decision', [$companyB, $experience]), [
        'revision' => 1, 'decision' => 'confirm',
    ])->assertNotFound();
    expect($experience->fresh()->status)->toBe('pending');
});

it('empêche une confirmation après suspension de l’immatriculation', function () {
    $owner = person('Entreprise suspension');
    $moussa = person('Moussa référence');
    $company = verifiedCompanyForCareer($owner);
    $experience = declareCareer($moussa, $company);
    $admin = User::factory()->superAdmin()->create();
    app(CompanyRegistry::class)->review($admin, $company, 2, 'suspend', 'Usurpation signalée');

    expect(fn () => app(CompanyExperienceService::class)->decide($owner, $experience, 1, 'confirm'))
        ->toThrow(HttpException::class);
    $this->get(route('personnes.show', $moussa->profile))->assertDontSeeText('Entreprise suspension');
});

it('retire une référence et son autorisation publique sans supprimer le parcours historique', function () {
    $owner = person('Entreprise archive');
    $moussa = person('Personne retrait');
    $company = verifiedCompanyForCareer($owner);
    $experience = declareCareer($moussa, $company, ['show_on_company_page' => true]);
    app(CompanyExperienceService::class)->decide($owner, $experience, 1, 'confirm');
    $this->get(route('entreprises.show', $company))->assertSeeText('Personne retrait');

    $this->actingAs($moussa)->post(route('espace.company-experiences.withdraw', $experience))->assertRedirect();
    expect($experience->fresh()->status)->toBe('withdrawn')
        ->and($experience->fresh()->show_on_company_page)->toBeFalse();
    expect(contenuPrincipal($this->get(route('entreprises.show', $company))))->not->toContain('Personne retrait');
    $this->get(route('personnes.show', $moussa->profile))->assertDontSeeText('Entreprise archive');
});

it('permet de refuser une déclaration sans la présenter comme confirmée', function () {
    $owner = person('Entreprise contrôle');
    $moussa = person('Personne non confirmée');
    $company = verifiedCompanyForCareer($owner);
    $experience = declareCareer($moussa, $company);
    app(CompanyExperienceService::class)->decide($owner, $experience, 1, 'decline');

    expect($experience->fresh()->status)->toBe('declined')
        ->and($experience->fresh()->confirmed())->toBeFalse();
    $this->get(route('personnes.show', $moussa->profile))->assertSeeText('Non confirmée par l’entreprise');
});

it('permet d’ajouter un dossier depuis la page Mes expériences et conserve le choix de l’entreprise', function () {
    $owner = person('Propriétaire officiel');
    $moussa = person('Auteure du parcours');
    $company = verifiedCompanyForCareer($owner);
    $this->actingAs($moussa)->get(route('espace.company-experiences'))->assertOk()
        ->assertSeeText($company->name)
        ->assertSeeText('Ajouter une expérience');
    $this->post(route('espace.company-experiences.store'), [
        'company_id' => $company->id, 'relationship' => 'intern',
        'title' => 'Stage de maintenance', 'period' => '2025', 'show_on_company_page' => 0,
    ])->assertRedirect(route('espace.company-experiences'));
    expect(CompanyExperience::sole()->relationship)->toBe('intern')
        ->and(CompanyExperience::sole()->status)->toBe('pending');
});
