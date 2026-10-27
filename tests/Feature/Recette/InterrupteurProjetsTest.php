<?php

use App\Livewire\MesProjets;
use App\Livewire\MondeZumra;
use App\Livewire\ParametresProjets;
use App\Models\AdminAction;
use App\Models\Project;
use App\Models\User;
use App\Services\Settings;
use App\Support\ProjectsModule;
use Livewire\Livewire;

/** Interrupteur du module Projets (recette ZUMRA, D-213) : jusqu'ici les Projets suivaient les ZUMRA sans réglage propre. */
function projetsReglage(string $etat, string $zumra = 'public', string $testeurs = ''): void
{
    app(Settings::class)->update(['projets_module' => $etat, 'zumra_module' => $zumra, 'zumra_testers' => $testeurs], User::factory()->superAdmin()->create());
}

beforeEach(function () {
    projetsReglage('inherit', 'public');
    $this->yao = person('Yao Kouassi');
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->projet = projet($this->yao, $this->zumra);
});

it('suit le module ZUMRA par défaut, comme avant : rien ne change pour l’existant', function () {
    expect(ProjectsModule::setting())->toBe('inherit');

    projetsReglage('inherit', 'off');
    expect(ProjectsModule::accessibleTo($this->yao))->toBeFalse();
    $this->actingAs($this->yao)->get(route('espace.projets'))->assertNotFound();

    projetsReglage('inherit', 'public');
    expect(ProjectsModule::accessibleTo($this->yao))->toBeTrue();
    $this->actingAs($this->yao)->get(route('espace.projets'))->assertOk();
    $this->actingAs($this->yao)->get(route('projets.show', $this->projet))->assertOk();
});

it('ferme les Projets sans fermer les ZUMRA quand le Président les désactive, et conserve toutes les données', function () {
    projetsReglage('off');

    expect(ProjectsModule::accessibleTo($this->yao))->toBeFalse();
    $this->actingAs($this->yao)->get(route('espace.projets'))->assertNotFound();
    $this->actingAs($this->yao)->get(route('projets.show', $this->projet))->assertNotFound();
    $this->actingAs($this->yao)->get(route('projets.edit', $this->projet))->assertNotFound();
    $this->actingAs($this->yao)->get(route('projets.create', $this->zumra))->assertNotFound();
    Livewire::actingAs($this->yao)->test(MesProjets::class)->assertNotFound();

    // Les ZUMRA restent ouvertes, sans onglet Projets, même appelé directement.
    $this->actingAs($this->yao)->get(route('zumras.show', $this->zumra))->assertOk();
    Livewire::actingAs($this->yao)->test(MondeZumra::class, ['zumra' => $this->zumra])->set('tab', 'projets')->assertDontSee('Demandes d’accueil')->assertDontSeeHtml("wire:click=\"setTab('projets')\"");

    // Données conservées, retrouvées à la réouverture.
    expect(Project::query()->whereKey($this->projet->id)->exists())->toBeTrue();
    projetsReglage('public');
    $this->actingAs($this->yao)->get(route('projets.show', $this->projet))->assertOk();
});

it('retire « Mes projets » de Mon espace quand le module est fermé', function () {
    projetsReglage('public');
    $this->actingAs($this->yao)->get(route('espace'))->assertSee('Mes projets');
    projetsReglage('off');
    $this->actingAs($this->yao)->get(route('espace'))->assertDontSee('Mes projets');
});

it('limite le test privé aux testeurs, et ne s’ouvre jamais sans les ZUMRA', function () {
    $testeur = person('Testeuse Un', attributes: ['email' => 'testeuse@exemple.test']);
    $autre = person('Autre Personne');

    projetsReglage('test', 'public', 'testeuse@exemple.test');
    expect(ProjectsModule::accessibleTo($testeur))->toBeTrue()->and(ProjectsModule::accessibleTo($autre))->toBeFalse();

    projetsReglage('public', 'off');
    expect(ProjectsModule::accessibleTo($this->yao))->toBeFalse();
});

it('réserve le réglage au super administrateur, le valide et l’inscrit au journal', function () {
    $this->get(route('espace.parametres.projets'))->assertRedirect('/login');
    $this->actingAs($this->yao)->get(route('espace.parametres.projets'))->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get(route('espace.parametres.projets'))->assertForbidden();

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->get(route('espace.parametres.projets'))->assertOk()->assertSee('Module Projets')->assertSee('Suit le module ZUMRA');
    $page = Livewire::actingAs($admin)->test(ParametresProjets::class)->assertSet('state', 'inherit');
    $page->set('state', 'ouvert-a-tous')->call('save')->assertHasErrors('state');
    $page->set('state', 'off')->call('save')->assertHasNoErrors()->assertSee('enregistré');

    expect(ProjectsModule::setting())->toBe('off');
    expect(AdminAction::query()->where('action', 'setting.changed')->where('subject_id', 'projets_module')->exists())->toBeTrue();
});

it('propose le réglage Projets dans Paramètres', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('espace.parametres'))->assertSee('Module Projets')->assertSee(route('espace.parametres.projets'));
});
