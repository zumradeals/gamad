<?php

use App\Enums\NotificationKind;
use App\Livewire\CompanyModeration;
use App\Models\Company;
use App\Models\CompanyAppeal;
use App\Models\CompanyExperience;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CompanyExperienceService;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function companyForFinishing(User $owner, bool $verify = true): Company
{
    $company = app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Société pilote finitions',
        'legal_name' => 'Société pilote finitions SARL',
        'registration_country' => 'CI',
        'registration_number' => 'CI-ABJ-FINISH-'.str_replace('-', '', $owner->id),
        'sector' => 'Réseau et maintenance',
    ], ['rccm' => UploadedFile::fake()->create('rccm.pdf', 80, 'application/pdf')]);

    if ($verify) {
        app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 1, 'verify');
    }

    return $company->fresh();
}

beforeEach(function () {
    Storage::fake('local');
});

it('alerte le gestionnaire et le déclarant pendant le parcours de confirmation', function () {
    $owner = person('Propriétaire de page');
    $author = person('Technicien candidat');
    $company = companyForFinishing($owner);
    $experience = app(CompanyExperienceService::class)->declare($author, [
        'company_id' => $company->id,
        'relationship' => 'contractor',
        'title' => 'Maintenance réseau',
        'period' => '2025',
    ]);

    expect(UserNotification::where('user_id', $owner->id)->where('kind', NotificationKind::CompanyRequest->value)->count())->toBe(1);
    app(CompanyExperienceService::class)->decide($owner, $experience, 1, 'confirm');
    expect(UserNotification::where('user_id', $author->id)->where('kind', NotificationKind::CompanyDecision->value)->count())->toBe(1);

    $notification = UserNotification::where('user_id', $author->id)->where('kind', NotificationKind::CompanyDecision->value)->sole();
    $this->actingAs($author)->get(route('notifications.open', $notification))
        ->assertRedirect(route('espace.company-experiences'));
});

it('signale les décisions de modération à l’entreprise sans attribuer automatiquement de badge', function () {
    $owner = person('Entreprise attente');
    $company = companyForFinishing($owner, false);
    $admin = User::factory()->superAdmin()->create();

    app(CompanyRegistry::class)->review($admin, $company, 1, 'decline', 'Dossier incomplet.');
    expect($company->fresh()->verified())->toBeFalse()
        ->and(UserNotification::where('user_id', $owner->id)->where('kind', NotificationKind::CompanyReview->value)->exists())->toBeTrue();
});

it('enregistre un recours de l’entreprise refusée sans exposer la page et permet sa réouverture contrôlée', function () {
    $owner = person('Entreprise contestée');
    $company = companyForFinishing($owner, false);
    $admin = User::factory()->superAdmin()->create();
    app(CompanyRegistry::class)->review($admin, $company, 1, 'decline', 'Pièces non conformes.');

    $this->actingAs($owner)->post(route('entreprises.appeal', $company), [
        'reason' => 'Nous disposons du nouveau registre légal mis à jour.',
    ])->assertRedirect(route('entreprises.show', $company));
    $appeal = CompanyAppeal::where('company_id', $company->id)->sole();
    expect($appeal->status)->toBe('pending')->and($company->fresh()->verified())->toBeFalse();

    $this->actingAs(person('Visiteur quelconque'))->get(route('entreprises.show', $company))->assertNotFound();
    Livewire::actingAs($admin)->test(CompanyModeration::class)
        ->assertSeeText('Recours et signalements')
        ->call('decideAppeal', $appeal->id, 'accept')->assertHasNoErrors();

    expect($appeal->fresh()->status)->toBe('accepted')
        ->and($company->fresh()->status)->toBe('pending')
        ->and($company->fresh()->verified())->toBeFalse();
});

it('permet de signaler une possible usurpation et réserve sa décision au super administrateur', function () {
    $owner = person('Société originale');
    $viewer = person('Membre signaleur');
    $admin = User::factory()->superAdmin()->create();
    $company = companyForFinishing($owner);

    $this->actingAs($viewer)->post(route('entreprises.report', $company), [
        'reason' => 'La page utilise les documents d’une autre société.',
    ])->assertRedirect(route('entreprises.show', $company));
    $report = CompanyAppeal::where('company_id', $company->id)->sole();
    expect($report->kind)->toBe('report')->and($company->fresh()->verified())->toBeTrue();

    $this->actingAs($viewer)->get(route('moderation.entreprises'))->assertForbidden();
    Livewire::actingAs($admin)->test(CompanyModeration::class)->call('decideAppeal', $report->id, 'accept')->assertHasNoErrors();
    expect($company->fresh()->verified())->toBeFalse();
    $this->get(route('entreprises.show', $company))->assertNotFound();
});

it('ne permet pas à l’entreprise propriétaire de se signaler ni aux tiers de déposer un recours', function () {
    $owner = person('Entreprise droits');
    $stranger = person('Membre externe');
    $company = companyForFinishing($owner);
    $this->actingAs($owner)->post(route('entreprises.report', $company), ['reason' => 'Je signale ma propre société.'])->assertNotFound();
    $this->actingAs($stranger)->post(route('entreprises.appeal', $company), ['reason' => 'Demande non autorisée et non recevable.'])->assertForbidden();
});

it('réutilise une relation consentie pour contacter une entreprise sans ouvrir une messagerie automatique', function () {
    $owner = person('Gestionnaire contact');
    $company = companyForFinishing($owner);
    $viewer = person('Partenaire potentiel');
    $this->actingAs($viewer)->get(route('entreprises.show', $company))
        ->assertOk()->assertSeeText('Contacter')
        ->assertSee(route('personnes.show', $owner->profile));
    $this->assertDatabaseCount('conversations', 0);
});

it('distingue la nouvelle confirmation entreprise des anciennes attestations individuelles', function () {
    $author = person('Professionnel avec parcours');
    $this->actingAs($author)->get(route('espace.profil'))
        ->assertOk()->assertSeeText('Attestations personnelles (ancien dispositif)')
        ->assertSeeText('Mes expériences auprès d’entreprises vérifiées');
});
