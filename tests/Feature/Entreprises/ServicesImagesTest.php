<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyBranding;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function entrepriseAvecVitrine(User $owner, bool $verified = true): Company
{
    $company = app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Entreprise vitrine', 'legal_name' => 'Entreprise vitrine SARL', 'registration_country' => 'CI',
        'registration_number' => 'CI-VITRINE-'.str_replace('-', '', $owner->id), 'sector' => 'Informatique', 'city' => 'Abidjan', 'summary' => 'Présentation.',
    ], ['rccm' => UploadedFile::fake()->create('registre.pdf', 80, 'application/pdf')]);
    if ($verified) {
        app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 1, 'verify');
    }

    return $company->fresh();
}

function enregistrerVitrine(Company $company, array $services): void
{
    app(CompanyBranding::class)->update($company->owner, $company->fresh(), ['services' => $services]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->owner = person('Gestionnaire vitrine');
    $this->company = entrepriseAvecVitrine($this->owner);
});

it('enregistre une image par fiche, recadrée en 800 × 600 JPEG, et la sert avec les droits de la page', function () {
    enregistrerVitrine($this->company, [
        ['name' => 'Maintenance', 'category' => 'Services', 'description' => 'Suivi.', 'image' => UploadedFile::fake()->image('maintenance.png', 1200, 900)],
        ['name' => 'Formation', 'category' => '', 'description' => ''],
    ]);

    $fresh = $this->company->fresh();
    $path = $fresh->services[0]['image_path'];
    expect($path)->toStartWith('company-branding/')->toEndWith('.jpg')->and($fresh->services[1])->not->toHaveKey('image_path');
    Storage::disk('local')->assertExists($path);
    $info = getimagesizefromstring(Storage::disk('local')->get($path));
    expect($info['mime'])->toBe('image/jpeg')->and($info[0])->toBe(800)->and($info[1])->toBe(600);

    $this->get(route('entreprises.visual', [$fresh, 'service-0']))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('entreprises.visual', [$fresh, 'service-1']))->assertNotFound();
    $this->get(route('entreprises.show', $fresh))->assertOk()->assertSee(route('entreprises.visual', [$fresh, 'service-0']), false)
        ->assertDontSee(route('entreprises.visual', [$fresh, 'service-1']), false);
});

it('permet d’ajouter une image depuis le formulaire de la page, réservé au gestionnaire', function () {
    $this->actingAs($this->owner)->get(route('entreprises.branding.edit', $this->company))->assertOk()->assertSee('services[0][image]', false)->assertSeeText('Image (facultatif');

    $intrus = person('Intrus vitrine');
    $this->actingAs($intrus)->post(route('entreprises.branding.update', $this->company), ['services' => [['name' => 'X', 'image' => UploadedFile::fake()->image('x.png', 400, 300)]]])->assertForbidden();
    expect($this->company->fresh()->services)->toBeNull();

    $this->actingAs($this->owner)->post(route('entreprises.branding.update', $this->company), ['services' => [['name' => 'Maintenance', 'image' => UploadedFile::fake()->image('m.png', 640, 480)]]])
        ->assertRedirect(route('entreprises.show', $this->company));
    expect($this->company->fresh()->services[0])->toHaveKey('image_path');
});

it('conserve l’image d’une fiche quand on ne la touche pas, la remplace ou la retire, et efface les anciens fichiers', function () {
    enregistrerVitrine($this->company, [['name' => 'Maintenance', 'image' => UploadedFile::fake()->image('a.png', 800, 600)]]);
    $ancien = $this->company->fresh()->services[0]['image_path'];

    enregistrerVitrine($this->company, [['name' => 'Maintenance renommée']]);
    expect($this->company->fresh()->services[0]['image_path'])->toBe($ancien);
    Storage::disk('local')->assertExists($ancien);

    enregistrerVitrine($this->company, [['name' => 'Maintenance', 'image' => UploadedFile::fake()->image('b.png', 800, 600)]]);
    $nouveau = $this->company->fresh()->services[0]['image_path'];
    expect($nouveau)->not->toBe($ancien);
    Storage::disk('local')->assertMissing($ancien);
    Storage::disk('local')->assertExists($nouveau);

    enregistrerVitrine($this->company, [['name' => 'Maintenance', 'remove_image' => '1']]);
    expect($this->company->fresh()->services[0])->not->toHaveKey('image_path');
    Storage::disk('local')->assertMissing($nouveau);
});

it('efface l’image d’une fiche retirée de la vitrine et celles de la page supprimée', function () {
    enregistrerVitrine($this->company, [
        ['name' => 'Une', 'image' => UploadedFile::fake()->image('a.png', 800, 600)],
        ['name' => 'Deux', 'image' => UploadedFile::fake()->image('b.png', 800, 600)],
    ]);
    [$un, $deux] = collect($this->company->fresh()->services)->pluck('image_path')->all();

    enregistrerVitrine($this->company, [['name' => 'Une'], ['name' => '']]);
    Storage::disk('local')->assertExists($un);
    Storage::disk('local')->assertMissing($deux);

    $this->owner->delete();
    Storage::disk('local')->assertMissing($un);
});

it('refuse un fichier qui n’est pas une image et une image trop lourde, sans rien enregistrer', function () {
    foreach ([UploadedFile::fake()->create('virus.exe', 20), UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'), UploadedFile::fake()->image('lourde.png')->size(6000)] as $fichier) {
        $this->actingAs($this->owner)->post(route('entreprises.branding.update', $this->company), ['services' => [['name' => 'Maintenance', 'image' => $fichier]]])->assertSessionHasErrors('services.0.image');
    }
    expect($this->company->fresh()->services)->toBeNull()->and(Storage::disk('local')->allFiles('company-branding'))->toBe([]);
});

it('ne sert jamais l’image d’une page non vérifiée à un visiteur et refuse un rang inconnu', function () {
    $autre = person('Autre gestionnaire');
    $attente = entrepriseAvecVitrine($autre, false);
    app(CompanyBranding::class)->update($autre, $attente, ['services' => [['name' => 'Privé', 'image' => UploadedFile::fake()->image('p.png', 800, 600)]]]);

    $this->get(route('entreprises.visual', [$attente->fresh(), 'service-0']))->assertNotFound();
    $this->actingAs($autre)->get(route('entreprises.visual', [$attente->fresh(), 'service-0']))->assertOk();
    $this->actingAs($autre)->get(route('entreprises.visual', [$attente->fresh(), 'service-9']))->assertNotFound();
    $this->actingAs($autre)->get('/entreprises/'.$attente->slug.'/visuel/service-12')->assertNotFound();
});
