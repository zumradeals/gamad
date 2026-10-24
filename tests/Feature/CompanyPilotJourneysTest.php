<?php

use App\Enums\NotificationKind;
use App\Livewire\CompanyModeration;
use App\Livewire\CompanyResubmit;
use App\Models\Company;
use App\Models\CompanyReport;
use App\Models\PersonConnection;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CompanyExperienceService;
use App\Services\CompanyRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function pilot50Company(User $owner): Company
{
    return app(CompanyRegistry::class)->submit($owner, [
        'name' => 'Entreprise pilote notifications',
        'legal_name' => 'Entreprise pilote notifications SARL',
        'registration_country' => 'CI',
        'registration_number' => 'CI-PR50-'.str_replace('-', '', $owner->id),
        'sector' => 'Installation télécom',
        'email' => 'contact@example.test',
    ], ['rccm' => UploadedFile::fake()->create('registre.pdf', 80, 'application/pdf')]);
}

beforeEach(function () {
    Storage::fake('local');
    config(['gamad.initial_experience' => true]);
});

it('notifie le gestionnaire d’une expérience, puis son auteur de la décision avec des liens privés', function () {
    $owner = person('Entreprise gestionnaire');
    $author = person('Demandeur professionnel');
    $company = pilot50Company($owner);
    $admin = User::factory()->superAdmin()->create();

    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');
    $experience = app(CompanyExperienceService::class)->declare($author, [
        'company_id' => $company->id, 'relationship' => 'intern',
        'title' => 'Stage fibre optique', 'period' => '2025',
    ]);

    $request = UserNotification::where('user_id', $owner->id)
        ->where('kind', NotificationKind::CompanyExpRequested->value)->sole();
    expect($request->company_id)->toBe($company->id)
        ->and($request->company_experience_id)->toBe($experience->id);

    $this->actingAs($owner)->get(route('notifications.open', $request))
        ->assertRedirect(route('entreprises.experiences.review', $company));
    $this->actingAs($author)->get(route('notifications.open', $request))->assertNotFound();

    app(CompanyExperienceService::class)->decide($owner, $experience, 1, 'confirm');
    $answer = UserNotification::where('user_id', $author->id)
        ->where('kind', NotificationKind::CompanyExpConfirmed->value)->sole();
    $this->actingAs($author)->get(route('notifications.open', $answer))
        ->assertRedirect(route('espace.company-experiences'));
    expect($answer->fresh()->read_at)->not->toBeNull();
});

it('notifie les responsables GAMAD des dossiers puis le créateur de la décision', function () {
    $owner = person('Créateur entreprise');
    $admin = User::factory()->superAdmin()->create();
    $company = pilot50Company($owner);

    $pending = UserNotification::where('user_id', $admin->id)
        ->where('kind', NotificationKind::CompanyReviewRequested->value)->sole();
    $this->actingAs($admin)->get(route('notifications.open', $pending))
        ->assertRedirect(route('moderation.entreprises'));

    app(CompanyRegistry::class)->review($admin, $company, 1, 'decline', 'RCCM peu lisible');
    $decision = UserNotification::where('user_id', $owner->id)
        ->where('kind', NotificationKind::CompanyReviewUpdated->value)->sole();
    $this->actingAs($owner)->get(route('notifications.open', $decision))
        ->assertRedirect(route('entreprises.show', $company));
});

it('autorise le redépôt d’un RCCM après un refus tout en protégeant le dossier et ses pièces', function () {
    $owner = person('Société correction');
    $admin = User::factory()->superAdmin()->create();
    $company = pilot50Company($owner);
    $old = $company->documents()->where('kind', 'rccm')->sole()->private_path;
    app(CompanyRegistry::class)->review($admin, $company, 1, 'decline', 'Le justificatif doit être plus lisible.');

    $this->actingAs($owner)->get(route('entreprises.resubmit', $company))
        ->assertOk()->assertSeeText('Corriger les justificatifs');
    Livewire::actingAs($owner)->test(CompanyResubmit::class, ['company' => $company])
        ->set('rccm', UploadedFile::fake()->create('corrige.pdf', 100, 'application/pdf'))
        ->call('submit')->assertHasNoErrors();

    $row = $company->fresh();
    expect($row->status)->toBe('pending')->and($row->revision)->toBe(3)
        ->and($row->registration_number)->toBe($company->registration_number);
    $new = $row->documents()->where('kind', 'rccm')->sole()->private_path;
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($new);
    $this->actingAs($admin)->get(route('entreprises.document', [$row, $row->documents()->sole()]))->assertOk();
    expect(UserNotification::where('kind', NotificationKind::CompanyReviewRequested->value)->count())->toBeGreaterThan(1);
});

it('refuse le redépôt d’un compte tiers ou d’une entreprise déjà validée', function () {
    $owner = person('Entreprise protégée');
    $intruder = person('Intrus');
    $company = pilot50Company($owner);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($intruder)->get(route('entreprises.resubmit', $company))->assertForbidden();
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');
    $this->actingAs($owner)->get(route('entreprises.resubmit', $company))->assertForbidden();

    expect(fn () => app(CompanyRegistry::class)->resubmit($owner, $company, 2, [
        'rccm' => UploadedFile::fake()->create('doc.pdf', 60, 'application/pdf'),
    ]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('reçoit un signalement sans le dévoiler à l’entreprise et interdit les doublons', function () {
    $owner = person('Page signalée');
    $reporter = person('Signalant');
    $admin = User::factory()->superAdmin()->create();
    $company = pilot50Company($owner);
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');

    $this->actingAs($reporter)->get(route('entreprises.reports.create', $company))->assertOk();
    $this->post(route('entreprises.reports.store', $company), [
        'reason' => 'impersonation',
        'details' => 'Cette page semble utiliser sans accord notre identité d’entreprise.',
    ])->assertRedirect(route('entreprises.show', $company));

    $report = CompanyReport::sole();
    expect($report->status)->toBe('open')->and($report->reporter_id)->toBe($reporter->id);
    $this->actingAs($owner)->get(route('entreprises.show', $company))
        ->assertDontSeeText('Cette page semble utiliser sans accord');
    $this->actingAs($reporter)->post(route('entreprises.reports.store', $company), [
        'reason' => 'impersonation',
        'details' => 'Signalement doublon pour cette même page entreprise.',
    ])->assertSessionHasErrors('details');
    expect(CompanyReport::count())->toBe(1);
    expect(UserNotification::where('user_id', $admin->id)
        ->where('kind', NotificationKind::CompanyReportReceived->value)->count())->toBe(1);
});

it('laisse les seuls super administrateurs clôturer les signalements avec une décision motivée', function () {
    $owner = person('Entreprise avertie');
    $reporter = person('Témoin');
    $admin = User::factory()->superAdmin()->create();
    $company = pilot50Company($owner);
    app(CompanyRegistry::class)->review($admin, $company, 1, 'verify');
    $this->actingAs($reporter)->post(route('entreprises.reports.store', $company), [
        'reason' => 'wrong_information',
        'details' => 'La présentation décrit une activité manifestement erronée.',
    ]);
    $report = CompanyReport::sole();

    $this->actingAs($reporter)->get(route('moderation.entreprises'))->assertForbidden();
    Livewire::actingAs($admin)->test(CompanyModeration::class)
        ->assertSeeText('La présentation décrit une activité')
        ->set('reportNotes.'.$report->id, 'Après contrôle de la situation, le dossier est clos.')
        ->call('resolveReport', $report->id)->assertHasNoErrors();

    expect($report->fresh()->status)->toBe('resolved')
        ->and($report->fresh()->reviewer_id)->toBe($admin->id);
});

it('contacte une entreprise par la relation existante sans ouvrir de conversation sans consentement', function () {
    $owner = person('Gestionnaire contact');
    $visitor = person('Professionnel contact');
    $company = pilot50Company($owner);
    app(CompanyRegistry::class)->review(User::factory()->superAdmin()->create(), $company, 1, 'verify');

    $this->actingAs($visitor)->get(route('entreprises.contact', $company))
        ->assertOk()->assertSeeText('mise en relation');
    $this->post(route('entreprises.contact.store', $company), [
        'note' => 'Je souhaiterais échanger sur votre service fibre optique.',
    ])->assertRedirect(route('entreprises.show', $company));

    $pair = app(\App\Services\People::class)->pair($visitor, $owner)->sole();
    expect($pair->state)->toBe('pending')->and($pair->conversation_id)->toBeNull()
        ->and($pair->note)->toContain('entreprise');
    $this->post(route('entreprises.contact.store', $company), [
        'note' => 'Je souhaite vous écrire encore.',
    ])->assertSessionHasErrors('note');
});

it('ne permet ni contact ni signalement d’une page non vérifiée', function () {
    $owner = person('Entreprise privée');
    $visitor = person('Autre utilisateur');
    $company = pilot50Company($owner);

    $this->actingAs($visitor)->get(route('entreprises.contact', $company))->assertNotFound();
    $this->actingAs($visitor)->get(route('entreprises.reports.create', $company))->assertNotFound();
    $this->actingAs($visitor)->post(route('entreprises.contact.store', $company), [
        'note' => 'Un message pour entrer en relation.',
    ])->assertNotFound();
    $this->actingAs($visitor)->post(route('entreprises.reports.store', $company), [
        'reason' => 'other', 'details' => 'Signalement privé non possible à ce stade.',
    ])->assertNotFound();
});
