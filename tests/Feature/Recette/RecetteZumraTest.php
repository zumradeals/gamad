<?php

use App\Enums\NotificationKind;
use App\Enums\ZumraDuty;
use App\Enums\ZumraRole;
use App\Livewire\FormulaireZumra;
use App\Livewire\MesZumras;
use App\Livewire\MondeZumra;
use App\Models\Domain;
use App\Models\JoinRequest;
use App\Models\UserNotification;
use App\Models\Zumra;
use App\Services\Memberships;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * Recette ciblée de ZUMRA (module ouvert, parcours actif) : création libre, adhésion, responsabilités, sortie et limites connues.
 * Elle décrit le comportement constaté, y compris ce qui est volontairement gelé (D-183, D-205).
 */
beforeEach(function () {
    c1ModuleZumra('public');
    $this->fondatrice = person('Salimata Ouattara');
    $this->ibrahim = person('Ibrahim Traoré');
});

function recetteZumraCreee($test): Zumra
{
    Livewire::actingAs($test->fondatrice)->test(FormulaireZumra::class)
        ->set('name', 'Atelier Soleil Couture')->set('sphere', 'social')->set('domain_id', Domain::query()->first()->id)->set('format', 'hybrid')
        ->set('mission', 'Apprendre la couture ensemble et vendre nos créations.')->call('save')->assertHasNoErrors();

    return Zumra::query()->where('name', 'Atelier Soleil Couture')->firstOrFail();
}

it('crée une ZUMRA librement : aucune validation préalable, la personne en est la fondatrice', function () {
    $zumra = recetteZumraCreee($this);

    expect($zumra->roleOf($this->fondatrice))->toBe(ZumraRole::Founder)->and($zumra->memberships()->count())->toBe(1);
    $this->actingAs($this->ibrahim)->get(route('zumras.show', $zumra))->assertOk()->assertSee('Atelier Soleil Couture')->assertSee('Demander à rejoindre');
    $this->actingAs($this->ibrahim)->get(route('zumras.index'))->assertOk()->assertSee('Atelier Soleil Couture');
});

it('ne demande ni projet, ni financement, ni nombre minimal de membres, mais refuse un nom déjà pris et limite les créations du jour', function () {
    recetteZumraCreee($this);

    Livewire::actingAs($this->ibrahim)->test(FormulaireZumra::class)
        ->set('name', 'atelier soleil couture')->set('sphere', 'social')->set('domain_id', Domain::query()->first()->id)->set('format', 'online')->set('mission', 'Doublon.')->call('save')
        ->assertHasErrors('name');

    config(['gamad.limits.zumras_per_day' => 1]);
    Livewire::actingAs($this->fondatrice)->test(FormulaireZumra::class)
        ->set('name', 'Une deuxième ZUMRA')->set('sphere', 'social')->set('domain_id', Domain::query()->first()->id)->set('format', 'online')->set('mission', 'Autre mission.')->call('save')
        ->assertHasErrors('name');
});

it('refuse la création à une personne dont l’adresse e-mail n’est pas vérifiée', function () {
    $non = person('Non Vérifiée', attributes: ['email_verified_at' => null]);
    $this->actingAs($non)->get(route('zumras.create'))->assertRedirect();
});

it('parcourt l’adhésion : demande, acceptation, notification, puis départ et nouvelle demande possible', function () {
    $zumra = recetteZumraCreee($this);

    Livewire::actingAs($this->ibrahim)->test(MondeZumra::class, ['zumra' => $zumra])->call('requestJoin')->assertHasNoErrors();
    $demande = JoinRequest::query()->where('zumra_id', $zumra->id)->sole();
    Livewire::actingAs($this->fondatrice)->test(MondeZumra::class, ['zumra' => $zumra])->set('tab', 'membres')->assertSee('Ibrahim')->call('acceptRequest', $demande->id);

    expect($zumra->fresh()->roleOf($this->ibrahim))->toBe(ZumraRole::Member);
    expect(UserNotification::query()->where('user_id', $this->ibrahim->id)->where('kind', NotificationKind::ZumraRequestAccepted->value)->exists())->toBeTrue();

    Livewire::actingAs($this->ibrahim)->test(MondeZumra::class, ['zumra' => $zumra])->call('leave');
    expect($zumra->fresh()->roleOf($this->ibrahim))->toBeNull();
    Livewire::actingAs($this->ibrahim)->test(MondeZumra::class, ['zumra' => $zumra->fresh()])->assertSee('Demander à rejoindre');
});

it('gère les responsabilités : nomination, poste proposé, acceptation par la personne, publication encore en pause', function () {
    $zumra = recetteZumraCreee($this);
    admis($zumra, $this->ibrahim);
    $service = app(Memberships::class);
    $membership = $zumra->memberships()->where('user_id', $this->ibrahim->id)->firstOrFail();

    $service->promote($this->fondatrice, $zumra, $membership);
    $service->proposeDuty($this->fondatrice, $zumra, $membership->fresh(), ZumraDuty::FirstDeputy);
    expect($membership->fresh()->dutyPending())->toBeTrue();

    Livewire::actingAs($this->ibrahim)->test(MesZumras::class)->assertSee('Premier responsable adjoint')->call('acceptDuty', $membership->id);
    expect($membership->fresh()->dutyPending())->toBeFalse()->and($zumra->fresh()->roleOf($this->ibrahim))->toBe(ZumraRole::Manager);

    // Limite connue (D-183, D-205) : la publication collective au nom d'une ZUMRA reste gelée, même module ouvert.
    $this->actingAs($this->ibrahim)->get(route('zumras.publier', [$zumra, 'savoir']))->assertForbidden();
});

it('protège la ZUMRA : seul un responsable gère ; le fondateur ne peut ni quitter ni être retiré ; un membre ordinaire ne voit pas la gestion', function () {
    $zumra = recetteZumraCreee($this);
    admis($zumra, $this->ibrahim);
    $membership = $zumra->memberships()->where('user_id', $this->ibrahim->id)->firstOrFail();

    $this->actingAs($this->ibrahim)->get(route('zumras.edit', $zumra))->assertNotFound();
    $this->actingAs($this->ibrahim)->get(route('zumras.invite', $zumra))->assertNotFound();
    expect(fn () => app(Memberships::class)->leave($this->fondatrice, $zumra))->toThrow(AuthorizationException::class);
    expect(fn () => app(Memberships::class)->remove($this->ibrahim, $zumra, $zumra->memberships()->where('user_id', $this->fondatrice->id)->first()))->toThrow(Exception::class);
    expect($membership->fresh())->not->toBeNull();
});

it('ferme tout quand le module est désactivé, y compris pour les membres existants', function () {
    $zumra = recetteZumraCreee($this);
    admis($zumra, $this->ibrahim);
    c1ModuleZumra('off');

    $this->actingAs($this->ibrahim)->get(route('zumras.index'))->assertRedirect(route('personnes.index'));
    $this->actingAs($this->ibrahim)->get(route('zumras.show', $zumra))->assertNotFound();
    $this->actingAs($this->ibrahim)->get(route('espace.zumras'))->assertNotFound();
    expect($zumra->fresh()->memberships()->count())->toBe(2);
});

it('compte, sur la page d’une ZUMRA, seulement les propositions de contribution de cette ZUMRA', function () {
    c1Module('public');
    $zumra = recetteZumraCreee($this);
    $autre = zumra($this->fondatrice, 'Seconde ZUMRA');
    foreach ([person('Proposeur Un'), person('Proposeur Deux')] as $proposeur) {
        c1Proposition($proposeur, ['beneficiary' => ['zumra', $autre->id]]);
    }

    $this->actingAs($this->fondatrice)->get(route('zumras.show', $zumra))->assertOk()->assertSee('Propositions reçues')->assertDontSee('Propositions reçues (2)');
    $this->actingAs($this->fondatrice)->get(route('zumras.show', $autre))->assertSee('Propositions reçues (2)');
});
