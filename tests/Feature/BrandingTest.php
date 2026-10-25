<?php

use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('réserve la gestion de l’identité au super administrateur', function () {
    $this->get(route('espace.parametres.identite'))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('espace.parametres.identite'))->assertForbidden();
    $this->post(route('espace.parametres.identite.save'), ['kind' => 'icon', 'remove' => '1'])->assertForbidden();
});

it('enregistre sert et retire le logo sans toucher à l’icône', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->get(route('espace.parametres.identite'))->assertOk()->assertSeeText('Identité visuelle');
    $this->post(route('espace.parametres.identite.save'), ['kind' => 'logo', 'image' => UploadedFile::fake()->image('logo.png', 800, 200)])->assertSessionHasNoErrors()->assertRedirect();
    $path = app(Settings::class)->get('brand_logo');
    Storage::disk('local')->assertExists($path);
    expect(app(Settings::class)->get('brand_icon'))->toBeNull();
    $this->get(route('branding.image', 'logo'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('espace.parametres.identite'))->assertSee(route('branding.image', 'logo'), false);
    $this->post(route('espace.parametres.identite.save'), ['kind' => 'logo', 'remove' => '1'])->assertSessionHasNoErrors();
    expect(app(Settings::class)->get('brand_logo'))->toBeNull();
    $this->get(route('branding.image', 'logo'))->assertNotFound();
});

it('refuse les icônes non carrées et fichiers non images sans changer la référence', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->post(route('espace.parametres.identite.save'), ['kind' => 'icon', 'image' => UploadedFile::fake()->image('wide.png', 200, 100)])->assertSessionHasErrors('image');
    $this->post(route('espace.parametres.identite.save'), ['kind' => 'icon', 'image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('image');
    expect(app(Settings::class)->get('brand_icon'))->toBeNull();
});

it('affiche l’icône dans les gabarits publics et conserve la transparence', function () {
    Storage::fake('local');
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->post(route('espace.parametres.identite.save'), ['kind' => 'icon', 'image' => UploadedFile::fake()->image('square.png', 512, 512)])->assertSessionHasNoErrors();
    $this->get(route('espace'))->assertSee('rel="icon"', false)->assertSee('rel="apple-touch-icon"', false);
    auth()->logout();
    $this->get('/login')->assertOk()->assertSee(route('branding.image', 'icon'), false);
});
