<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyBranding;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function companyForBranding(User $owner, bool $verified = true): Company
{
    $company = app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Entreprise identité visuelle',
        'legal_name' => 'Entreprise identité visuelle SARL',
        'registration_country' => 'CI',
        'registration_number' => 'CI-ABJ-LOGO-'.str_replace('-', '', $owner->id),
        'sector' => 'Maintenance électrique',
        'city' => 'Abidjan',
        'summary' => 'Présentation initiale.',
    ], ['rccm' => UploadedFile::fake()->create('registre.pdf', 80, 'application/pdf')]);

    if ($verified) {
        app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 1, 'verify');
    }

    return $company->fresh();
}

beforeEach(function () {
    Storage::fake('local');
});

it('affiche un éditeur visuel réservé au gestionnaire actif et son identité administrative intacte', function () {
    $owner = person('Gestionnaire identité');
    $intruder = person('Visiteur identité');
    $company = companyForBranding($owner);

    $this->get(route('entreprises.branding.edit', $company))->assertRedirect(route('login'));
    $this->actingAs($intruder)->get(route('entreprises.branding.edit', $company))->assertForbidden();
    $this->actingAs($intruder)->post(route('entreprises.branding.update', $company), ['summary' => 'Usurpation'])->assertForbidden();

    $this->actingAs($owner)->get(route('entreprises.branding.edit', $company))
        ->assertOk()->assertSeeText('Logo et bannière')->assertSeeText('Notre présentation')
        ->assertSeeText('immatriculation reste protégée');

    expect($company->fresh()->registration_number)->toBe($company->registration_number);
});

it('traite réellement logo et bannière en JPEG sans exposer les fichiers privés', function () {
    $owner = person('Entreprise graphisme');
    $company = companyForBranding($owner);
    $originalRegistration = $company->registration_number;
    $originalStatus = $company->status;

    $this->actingAs($owner)->post(route('entreprises.branding.update', $company), [
        'logo' => UploadedFile::fake()->image('logo.png', 800, 700),
        'banner' => UploadedFile::fake()->image('banniere.jpg', 1900, 850),
        'summary' => 'Installation, dépannage et maintenance.',
        'description' => 'Nous réalisons et entretenons les installations.',
        'website' => 'https://exemple.net',
        'city' => 'Abidjan',
        'email' => 'bonjour@exemple.net',
        'status' => 'verified',
        'registration_number' => 'FAUX-NUMERO',
    ])->assertRedirect(route('entreprises.show', $company));

    $fresh = $company->fresh();
    expect($fresh->verified())->toBeTrue()
        ->and($fresh->status)->toBe($originalStatus)
        ->and($fresh->registration_number)->toBe($originalRegistration)
        ->and($fresh->summary)->toBe('Installation, dépannage et maintenance.');
    foreach (['logo', 'banner'] as $kind) {
        $path = $fresh->{$kind.'_path'};
        expect($path)->toStartWith('company-branding/')->toEndWith('.jpg');
        Storage::disk('local')->assertExists($path);
        $info = getimagesizefromstring(Storage::disk('local')->get($path));
        expect($info['mime'])->toBe('image/jpeg');
        if ($kind === 'logo') {
            expect($info[0])->toBe($info[1])->toBeLessThanOrEqual(512);
        } else {
            expect($info[0])->toBe(1600)->and($info[1])->toBe(600);
        }
        $this->get(route('entreprises.visual', [$fresh, $kind]))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'private, no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    $this->get(route('entreprises.show', $fresh))->assertOk()
        ->assertSee(route('entreprises.visual', [$fresh, 'logo']))
        ->assertSee(route('entreprises.visual', [$fresh, 'banner']));
    $this->get(route('entreprises.index'))->assertSee(route('entreprises.visual', [$fresh, 'logo']));
});

it('empêche les visiteurs de consulter les visuels non vérifiés et après suspension', function () {
    $owner = person('Entreprise non vérifiée');
    $company = companyForBranding($owner, false);
    app(CompanyBranding::class)->update($owner, $company, [
        'logo' => UploadedFile::fake()->image('logo.png', 480, 480),
        'banner' => UploadedFile::fake()->image('banniere.jpg', 1200, 650),
    ]);

    $logo = route('entreprises.visual', [$company, 'logo']);
    $banner = route('entreprises.visual', [$company, 'banner']);
    $this->get($logo)->assertNotFound();
    $this->get($banner)->assertNotFound();
    $this->actingAs($owner)->get($logo)->assertOk();
    $this->actingAs($owner)->get($banner)->assertOk();

    $admin = User::factory()->superAdmin()->create();
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');
    auth()->logout();
    $this->get($logo)->assertOk();
    app(CompanyRegistry::class)->review($admin, $company->fresh(), 2, 'suspend', 'Usurpation signalée.');
    $this->get($logo)->assertNotFound();
    $this->get($banner)->assertNotFound();
    $this->get(route('entreprises.show', $company))->assertNotFound();
});

it('remplace puis retire les anciennes versions sans fichiers orphelins', function () {
    $owner = person('Entreprise remplacement');
    $company = companyForBranding($owner);
    $service = app(CompanyBranding::class);

    $one = $service->update($owner, $company, [
        'logo' => UploadedFile::fake()->image('ancien.png', 500, 500),
        'banner' => UploadedFile::fake()->image('ancienne.jpg', 1400, 550),
    ]);
    $logo = $one->logo_path;
    $banner = $one->banner_path;
    $two = $service->update($owner, $one, [
        'logo' => UploadedFile::fake()->image('nouveau.png', 512, 512),
    ]);
    Storage::disk('local')->assertMissing($logo);
    Storage::disk('local')->assertExists($two->logo_path);
    Storage::disk('local')->assertExists($banner);

    $three = $service->update($owner, $two, [
        'remove_logo' => true,
        'remove_banner' => true,
    ]);
    expect($three->logo_path)->toBeNull()->and($three->banner_path)->toBeNull();
    Storage::disk('local')->assertMissing($two->logo_path);
    Storage::disk('local')->assertMissing($banner);
});

it('refuse les faux fichiers image et les modifications contradictoires', function () {
    $owner = person('Entreprise validation');
    $company = companyForBranding($owner);

    $this->actingAs($owner)->post(route('entreprises.branding.update', $company), [
        'logo' => UploadedFile::fake()->create('document.pdf', 50, 'application/pdf'),
    ])->assertSessionHasErrors('logo');

    $this->post(route('entreprises.branding.update', $company), [
        'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
        'remove_logo' => '1',
    ])->assertSessionHasErrors('logo');
    expect($company->fresh()->logo_path)->toBeNull();
});

it('retire les deux visuels privés quand l’entreprise est supprimée avec son compte', function () {
    $owner = person('Entreprise effacement');
    $company = companyForBranding($owner);
    $updated = app(CompanyBranding::class)->update($owner, $company, [
        'logo' => UploadedFile::fake()->image('logo.png', 520, 520),
        'banner' => UploadedFile::fake()->image('banner.jpg', 1800, 800),
    ]);
    $paths = [$updated->logo_path, $updated->banner_path];

    $owner->delete();

    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
    expect(Company::whereKey($company->id)->exists())->toBeFalse();
});

it('ne déclenche aucune nouvelle vérification de page quand la présentation change', function () {
    $owner = person('Entreprise rédaction');
    $company = companyForBranding($owner);
    $revision = $company->revision;
    $verifiedAt = $company->verified_at->toDateTimeString();

    app(CompanyBranding::class)->update($owner, $company, [
        'summary' => 'Nouvelle présentation simple.',
        'description' => 'Nouveaux services, sans changement juridique.',
    ]);

    expect($company->fresh()->verified())->toBeTrue()
        ->and($company->fresh()->revision)->toBe($revision)
        ->and($company->fresh()->verified_at->toDateTimeString())->toBe($verifiedAt);
});
