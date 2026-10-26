<?php

use App\Livewire\ParametresPublicite;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\ServicesGamad;
use App\Services\Settings;
use App\Support\PubliciteModule;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/** Services GAMAD (D-209) et ouverture contrôlée du service GAMAD Publicité (D-210). */
function ouvrirPublicite(string $etat, string $testeurs = ''): void
{
    app(Settings::class)->update(['publicite_module' => $etat, 'publicite_testers' => $testeurs], User::factory()->superAdmin()->create());
}

beforeEach(function () {
    // La page d'entrée du service est livrée avec la PR suivante : on déclare une route d'essai pour le « disponible ».
    if (! Route::has('publicite.campagnes')) {
        Route::middleware(['web', 'auth'])->get('/espace/publicite-essai', fn () => 'ok')->name('publicite.campagnes');
        app('router')->getRoutes()->refreshNameLookups();
    }
});

it('désactive le service par défaut : ni menu, ni page, ni service listé', function () {
    $moi = User::factory()->create();
    expect(PubliciteModule::state())->toBe('off')->and(ServicesGamad::availableFor($moi))->toBe([]);

    $this->actingAs($moi)->get('/espace/services')->assertNotFound();
    $this->actingAs($moi)->get('/espace')->assertDontSee('Services GAMAD');
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace/services')->assertNotFound();
});

it('ouvre le service à un testeur vérifié et au super administrateur seulement en test privé', function () {
    $testeur = User::factory()->create(['email' => 'testeur@exemple.test']);
    $autre = User::factory()->create();
    ouvrirPublicite('test', "testeur@exemple.test\nnon-verifie@exemple.test");

    $this->actingAs($autre)->get('/espace/services')->assertNotFound();
    $this->actingAs($testeur)->get('/espace/services')->assertOk()->assertSee('GAMAD Publicité')->assertSee('Ouvrir');
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace/services')->assertOk();
    expect(PubliciteModule::isTester(User::factory()->unverified()->create(['email' => 'non-verifie@exemple.test'])))->toBeFalse();
});

it('ouvre le service à tous les membres connectés en public, jamais à un visiteur', function () {
    ouvrirPublicite('public');
    $this->get('/espace/services')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/espace/services')->assertOk()->assertSee('GAMAD Publicité');
});

it('place Services GAMAD dans le groupe Gestion de Mon espace, seulement quand un service est disponible', function () {
    ouvrirPublicite('public');
    $html = $this->actingAs(User::factory()->create())->get('/espace')->getContent();
    expect($html)->toContain('Services GAMAD')->toContain(route('espace.services'));
    expect(strpos($html, 'Gestion'))->toBeLessThan(strpos($html, 'Services GAMAD'));
});

it('ne liste pas un service dont la page n’existe pas encore', function () {
    ouvrirPublicite('public');
    $catalogue = ServicesGamad::catalogue();
    expect($catalogue)->toHaveKey('publicite')->and($catalogue['publicite']['route'])->toBe('publicite.campagnes');
});

it('réserve la page Paramètres du service au super administrateur et inscrit chaque changement au journal', function () {
    $this->get('/espace/parametres/publicite')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/espace/parametres/publicite')->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get('/espace/parametres/publicite')->assertForbidden();

    $admin = User::factory()->superAdmin()->create();
    Livewire::actingAs($admin)->test(ParametresPublicite::class)
        ->set('state', 'test')->set('testers', "Test@Exemple.test\nautre@exemple.test")->call('save')->assertHasNoErrors()->assertSee('enregistrés');
    expect(PubliciteModule::state())->toBe('test')->and(PubliciteModule::testers())->toBe(['test@exemple.test', 'autre@exemple.test']);
    expect(AdminAction::query()->where('action', 'setting.changed')->where('subject_id', 'publicite_module')->exists())->toBeTrue();
});

it('refuse un état ou une adresse invalide', function () {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(ParametresPublicite::class)
        ->set('state', 'ouvert-a-tous')->call('save')->assertHasErrors('state')
        ->set('state', 'test')->set('testers', 'pas-une-adresse')->call('save')->assertHasErrors('testers');
});

it('affiche le bon titre sur la page Paramètres des Contributions (et non celui de ZUMRA)', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/espace/parametres/contributions')->assertOk()->assertSee('Module Contributions')->assertDontSee('Module ZUMRA');
});
