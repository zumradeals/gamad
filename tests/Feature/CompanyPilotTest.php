<?php

use App\Livewire\CompanyCreate;
use App\Livewire\CompanyDirectory;
use App\Models\AdminAction;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function pilotCompany(User $owner, string $registration = 'CI-ABJ-03-2026-B13-77777'): Company
{
    return app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Entreprise test du pilote',
        'legal_name' => 'Entreprise test du pilote SARL',
        'registration_country' => 'CI',
        'registration_number' => $registration,
        'sector' => 'Maintenance et réseaux',
        'city' => 'Abidjan',
        'summary' => 'Installation et maintenance de réseaux professionnels.',
        'description' => 'Nous présentons notre métier et nos services.',
    ], ['rccm' => UploadedFile::fake()->create('rccm.pdf', 120, 'application/pdf')]);
}

it('conserve un dossier et ses pièces en stockage privé, sans badge automatique', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $company = pilotCompany($owner);

    expect($company->status)->toBe('pending')->and($company->verified_at)->toBeNull();
    $document = $company->documents()->sole();
    expect($document->kind)->toBe('rccm')->and($document->private_path)->toStartWith('companies/');
    Storage::disk('local')->assertExists($document->private_path);

    $this->get(route('entreprises.index'))->assertOk()->assertDontSeeText($company->name);
    $this->get(route('entreprises.show', $company))->assertNotFound();
    $this->actingAs($owner)->get(route('entreprises.show', $company))->assertOk()
        ->assertSeeText('Aperçu privé de votre entreprise')->assertDontSeeText('Immatriculation vérifiée par GAMAD');

    $this->actingAs(User::factory()->create())->get(route('entreprises.show', $company))->assertNotFound();
});

it('permet de soumettre une page par le véritable formulaire Livewire', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    Livewire::actingAs($owner)->test(CompanyCreate::class)
        ->set('name', 'Atelier pilote')
        ->set('legalName', 'Atelier pilote SARL')
        ->set('country', 'CI')
        ->set('registration', 'CI-ABJ-03-2026-B13-11111')
        ->set('sector', 'Bâtiment')
        ->set('city', 'Abidjan')
        ->set('rccm', UploadedFile::fake()->create('registre.pdf', 100, 'application/pdf'))
        ->call('save')->assertHasNoErrors();

    expect(Company::where('name', 'Atelier pilote')->count())->toBe(1)
        ->and(Company::where('name', 'Atelier pilote')->value('status'))->toBe('pending');
    expect(Company::first()->documents()->count())->toBe(1);
});

it('réserve strictement le téléchargement administratif aux super administrateurs', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $company = pilotCompany($owner);
    $document = $company->documents()->sole();
    $url = route('entreprises.document', [$company, $document]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($owner)->get($url)->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->get($url)
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('exige une vraie décision de modération avant toute publication dans l’annuaire', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $company = pilotCompany($owner);

    expect(fn () => app(CompanyRegistry::class)->review($owner, $company, 1, 'verify'))
        ->toThrow(HttpException::class);

    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');
    expect($company->fresh()->verified())->toBeTrue()
        ->and(AdminAction::where('action', 'companies.verify')->count())->toBe(1);

    $this->get(route('entreprises.index'))->assertOk()->assertSeeText($company->name);
    $this->get(route('entreprises.show', $company))->assertOk()
        ->assertSeeText('Immatriculation vérifiée par GAMAD')
        ->assertDontSeeText('justificatif-rccm.pdf');

    Livewire::test(CompanyDirectory::class)->assertSeeText($company->name);
    $this->get(route('entreprises.show', $company))->assertSeeText($company->sector);
});

it('permet de suivre volontairement une entreprise vérifiée sans score public', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $company = pilotCompany($owner);

    $this->actingAs($viewer)->get(route('entreprises.show', $company))->assertNotFound();
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');

    $this->actingAs($viewer)->post(route('entreprises.follow', $company))
        ->assertRedirect(route('entreprises.show', $company));
    expect(DB::table('company_follows')->where('company_id', $company->id)->count())->toBe(1);
    $this->get(route('entreprises.show', $company))->assertSeeText('Abonné à la page')->assertDontSeeText('J’aime');

    $this->post(route('entreprises.follow', $company))->assertRedirect();
    expect(DB::table('company_follows')->where('company_id', $company->id)->count())->toBe(0);
    $this->get(route('entreprises.show', $company))->assertSeeText('Suivre');
});

it('refuse une immatriculation déjà utilisée même avec des espaces différents', function () {
    Storage::fake('local');
    pilotCompany(User::factory()->create(), 'CI-ABJ-2026-555');

    expect(fn () => pilotCompany(User::factory()->create(), 'CI-ABJ-2026-555'))
        ->toThrow(ValidationException::class);

    expect(fn () => pilotCompany(User::factory()->create(), ' CI-ABJ-2026-555 '))
        ->toThrow(ValidationException::class);
});

it('ne confond pas une entreprise suspendue avec une entreprise vérifiée', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $company = pilotCompany($owner);
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');

    app(CompanyRegistry::class)->review($admin, $company->fresh(), 2, 'suspend', 'Usurpation signalée.');
    expect($company->fresh()->verified())->toBeFalse();
    $this->get(route('entreprises.show', $company))->assertNotFound();
    $this->get(route('entreprises.index'))->assertDontSeeText($company->name);
});

it('autorise le super administrateur à vérifier sa propre entreprise et refuse les décisions périmées', function () {
    Storage::fake('local');
    $admin = User::factory()->superAdmin()->create();
    $company = pilotCompany($admin);
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');

    expect($company->fresh()->verified())->toBeTrue()
        ->and(AdminAction::where('action', 'companies.verify')->count())->toBe(1);

    expect(fn () => app(CompanyRegistry::class)->review($admin, $company->fresh(), 1, 'suspend', 'Suspension justifiée'))
        ->toThrow(ValidationException::class);
});

it('efface les pièces privées quand le compte propriétaire est supprimé', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $company = pilotCompany($owner);
    $path = $company->documents()->sole()->private_path;

    $owner->delete();

    Storage::disk('local')->assertMissing($path);
    expect(Company::whereKey($company->id)->exists())->toBeFalse();
});
