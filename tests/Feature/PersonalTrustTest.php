<?php

use App\Enums\NotificationKind;
use App\Enums\PublicationType;
use App\Livewire\AttesterRealisation;
use App\Livewire\MesAttestations;
use App\Livewire\ModerationConfiance;
use App\Livewire\SignalerProfil;
use App\Models\AdminAction;
use App\Models\Conversation;
use App\Models\IdentityVerification;
use App\Models\TrustReport;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkAttestation;
use App\Services\DisplayName;
use App\Services\Moderation;
use App\Services\People;
use App\Services\PersonalTrust;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function verifyIdentityFor(User $user, User $admin): IdentityVerification
{
    $trust = app(PersonalTrust::class);
    $trust->requestIdentity($user, true);
    $row = IdentityVerification::where('user_id', $user->id)->sole();
    $trust->reviewIdentity($admin, $row->id, $row->revision, 'verify', method: 'in_person', nameChecked: true, personChecked: true);

    return $row->fresh();
}
function confirmedWorkFor(User $author, User $witness, User $admin): WorkAttestation
{
    verifyIdentityFor($witness, $admin);
    $people = app(People::class);
    $people->request($author, $witness, 'Nous avons travaillé ensemble.');
    $pair = $people->pair($author, $witness)->sole();
    $people->decide($witness, $author, 'accept', $pair->revision);
    $post = publication($author, PublicationType::PersonalWork, ['body' => 'Une installation achevée.']);
    $trust = app(PersonalTrust::class);
    $trust->requestWork($author, $post, $witness->id, 'J’ai posé les sanitaires de cette boutique.', today()->subDays(5)->toDateString());
    $row = WorkAttestation::sole();
    $trust->decideWork($witness, $row->id, $row->revision, 'confirm', 'client', true);

    return $row->fresh();
}

beforeEach(function () {
    config(['gamad.initial_experience' => true]);
    $this->author = person('Moussa', ['Plomberie']);
    $this->witness = person('Awa');
    $this->stranger = person('Binta');
    $this->admin = User::factory()->superAdmin()->create();
    $this->trust = app(PersonalTrust::class);
});

it('ne donne aucun badge automatiquement et exige un consentement volontaire', function () {
    expect($this->trust->identity($this->author))->toBeNull();
    expect(fn () => $this->trust->requestIdentity($this->author, false))->toThrow(ValidationException::class);
    $this->get(route('personnes.show', $this->author->profile))->assertOk()->assertDontSeeText('Identité vérifiée par GAMAD');
    Livewire::actingAs($this->author)->test(MesAttestations::class)->set('consent', true)->call('requestIdentity')->assertHasNoErrors()->assertSeeText('Contrôle en attente');
    expect(IdentityVerification::sole()->state)->toBe('pending')->and(IdentityVerification::sole()->getAttributes())->not->toHaveKey('document');
});

it('exige un super administrateur distinct et deux contrôles réels avant une attestation', function () {
    $this->trust->requestIdentity($this->author, true);
    $row = IdentityVerification::sole();
    expect(fn () => $this->trust->reviewIdentity($this->stranger, $row->id, $row->revision, 'verify'))->toThrow(HttpException::class);
    expect(fn () => $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'verify', method: 'in_person'))->toThrow(ValidationException::class);
    $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'verify', method: 'live_review', nameChecked: true, personChecked: true);
    expect($this->trust->identity($this->author))->not->toBeNull()->and(AdminAction::where('action', 'trust.identity_verify')->count())->toBe(1);
    $this->get(route('personnes.show', $this->author->profile))->assertOk()->assertSeeText('Identité vérifiée par GAMAD')->assertSeeText('ne certifie pas les prestations');
    $this->trust->requestIdentity($this->admin, true);
    $own = IdentityVerification::where('user_id', $this->admin->id)->sole();
    expect(fn () => $this->trust->reviewIdentity($this->admin, $own->id, $own->revision, 'verify', method: 'in_person', nameChecked: true, personChecked: true))->toThrow(AuthorizationException::class);
});

it('refuse les décisions périmées et communique les modalités sans vérifier automatiquement', function () {
    $this->trust->requestIdentity($this->author, true);
    $row = IdentityVerification::sole();
    $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'instruct', 'Présentez-vous à l’accueil lundi matin.');
    expect(fn () => $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'verify', method: 'in_person', nameChecked: true, personChecked: true))->toThrow(ValidationException::class);
    Livewire::actingAs($this->author)->test(MesAttestations::class)->assertSeeText('Présentez-vous à l’accueil lundi matin.');
    expect($this->trust->identity($this->author))->toBeNull();
    $this->get(route('personnes.show', $this->author->profile))->assertDontSeeText('Présentez-vous à l’accueil lundi matin.');
});

it('invalide le badge lors du changement du nom même si l’ancien nom est repris', function () {
    verifyIdentityFor($this->author, $this->admin);
    DisplayName::change($this->author, 'Autre nom');
    DisplayName::change($this->author, 'Moussa');
    expect($this->trust->identity($this->author->fresh()))->toBeNull()->and(IdentityVerification::sole()->state)->toBe('revoked');
});

it('expire les vérifications et permet un renouvellement sans réactiver les anciennes références', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    $identity = IdentityVerification::where('user_id', $this->witness->id)->sole();
    $this->travelTo($identity->expires_at->copy()->addMinute());
    expect($this->trust->identity($this->witness))->toBeNull()->and(WorkAttestation::visibleTo(null)->count())->toBe(0);
    verifyIdentityFor($this->witness, $this->admin);
    expect(WorkAttestation::visibleTo(null)->count())->toBe(0);
    $this->trust->decideWork($this->witness, $row->id, $row->revision, 'confirm', 'client', true);
    expect(WorkAttestation::visibleTo(null)->count())->toBe(1);
});

it('retire volontairement une vérification et impose un délai avant nouvelle demande', function () {
    $row = verifyIdentityFor($this->author, $this->admin);
    $this->trust->withdrawIdentity($this->author, $row->revision);
    expect($this->trust->identity($this->author))->toBeNull();
    expect(fn () => $this->trust->requestIdentity($this->author, true))->toThrow(ValidationException::class);
    $this->travel(8)->days();
    $this->trust->requestIdentity($this->author, true);
    expect(IdentityVerification::sole()->state)->toBe('pending');
});

it('confirme une intervention précise avec un témoin identifié sans créer une autre conversation', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    expect($row->state)->toBe('confirmed')->and($row->role)->toBe('client')->and(WorkAttestation::visibleTo(null)->count())->toBe(1);
    $this->get(route('personnes.show', $this->author->profile))->assertOk()->assertSeeText('Expérience confirmée')->assertSeeText('J’ai posé les sanitaires de cette boutique.')->assertSeeText('Confirmée par');
    expect(Conversation::count())->toBe(1);
});

it('refuse les demandes vers un inconnu non identifié ou soi-même et les dates futures', function () {
    $post = publication($this->author, PublicationType::PersonalWork);
    expect(fn () => $this->trust->requestWork($this->author, $post, $this->witness->id, 'Un travail concret', today()->toDateString()))->toThrow(ValidationException::class);
    expect(fn () => $this->trust->requestWork($this->author, $post, $this->author->id, 'Un travail concret', today()->toDateString()))->toThrow(HttpException::class);
    expect(fn () => $this->trust->requestWork($this->author, $post, $this->witness->id, 'Un travail concret', today()->addDay()->toDateString()))->toThrow(ValidationException::class);
    verifyIdentityFor($this->witness, $this->admin);
    expect(fn () => $this->trust->requestWork($this->author, $post, $this->witness->id, 'Un travail concret', today()->toDateString()))->toThrow(ValidationException::class);
});

it('réserve la sollicitation à une réalisation personnelle de l’auteur et refuse les textes vides', function () {
    confirmedWorkFor($this->author, $this->witness, $this->admin);
    foreach ([publication($this->author, PublicationType::Share), publication($this->witness, PublicationType::PersonalWork)] as $post) {
        try {
            $this->trust->requestWork($this->author, $post, $this->witness->id, 'Une intervention', today()->toDateString());
            $this->fail('Sollicitation indue');
        } catch (ValidationException|AuthorizationException $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    }
    $post = publication($this->author, PublicationType::PersonalWork);
    expect(fn () => $this->trust->requestWork($this->author, $post, $this->witness->id, ' ', today()->toDateString()))->toThrow(ValidationException::class);
});

it('attend le consentement du témoin et refuse une ancienne réponse', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    $this->trust->decideWork($this->author, $row->id, $row->revision, 'withdraw');
    expect(fn () => $this->trust->decideWork($this->witness, $row->id, $row->revision, 'confirm', 'client', true))->toThrow(ValidationException::class);
    expect(WorkAttestation::visibleTo(null)->count())->toBe(0);
    $this->travel(8)->days();
    $this->trust->requestWork($this->author, $row->publication, $this->witness->id, 'Un second texte à relire', today()->toDateString());
    $row->refresh();
    expect(fn () => $this->trust->decideWork($this->witness, $row->id, $row->revision, 'confirm', 'client', false))->toThrow(ValidationException::class);
    expect(fn () => $this->trust->decideWork($this->author, $row->id, $row->revision, 'confirm', 'client', true))->toThrow(AuthorizationException::class);
    $this->trust->decideWork($this->witness, $row->id, $row->revision, 'decline');
    expect($row->fresh()->state)->toBe('declined');
});

it('respecte visibilité des témoins publications masquées et comptes inactifs', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    $this->witness->profile->update(['visibility' => 'members']);
    expect(WorkAttestation::visibleTo(null)->count())->toBe(0)->and(WorkAttestation::visibleTo($this->stranger)->count())->toBe(1);
    app(Moderation::class)->hide($this->admin, $row->publication, 'Référence à examiner avec la publication.');
    expect(WorkAttestation::visibleTo($this->stranger)->count())->toBe(0);
    app(Moderation::class)->restore($this->admin, $row->publication);
    $this->witness->forceFill(['email_verified_at' => null])->save();
    expect(WorkAttestation::visibleTo($this->stranger)->count())->toBe(0);
});

it('retire définitivement les références au blocage sans les ressusciter au déblocage', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    app(People::class)->block($this->author, $this->witness, true);
    app(People::class)->block($this->author, $this->witness, false);
    expect($row->fresh()->state)->toBe('withdrawn')->and(WorkAttestation::visibleTo(null)->count())->toBe(0);
});

it('permet le retrait motivé sans permettre à GAMAD de confirmer à la place du témoin', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    expect(fn () => $this->trust->revokeWork($this->admin, $row->id, $row->revision, ''))->toThrow(ValidationException::class);
    $this->trust->revokeWork($this->admin, $row->id, $row->revision, 'Intervention contestée, contrôle nécessaire.');
    expect(WorkAttestation::visibleTo(null)->count())->toBe(0)->and(AdminAction::where('action', 'trust.work_revoked')->count())->toBe(1);
    expect(fn () => $this->trust->decideWork($this->admin, $row->id, $row->fresh()->revision, 'confirm', 'client', true))->toThrow(ModelNotFoundException::class);
});

it('réserve les dossiers privés à leurs participants et le contrôle au super administrateur', function () {
    $this->actingAs($this->stranger)->get(route('moderation.confiance'))->assertForbidden();
    Livewire::actingAs($this->stranger)->test(MesAttestations::class)->assertDontSeeText('Nom demandé');
    $this->actingAs($this->admin)->get(route('moderation.confiance'))->assertOk();
    $moderator = User::factory()->moderator()->create();
    $this->actingAs($moderator)->get(route('moderation.confiance'))->assertForbidden();
});

it('enregistre un signalement confidentiel borné et refuse les doublons ouverts', function () {
    $this->trust->report($this->stranger, $this->author, null, 'identity', 'Ce compte semble usurper une identité.');
    expect(TrustReport::sole()->reporter_id)->toBe($this->stranger->id);
    expect(fn () => $this->trust->report($this->stranger, $this->author, null, 'identity', 'Même signalement'))->toThrow(ValidationException::class);
    $this->get(route('personnes.show', $this->author->profile))->assertDontSeeText('Ce compte semble usurper');
    Livewire::actingAs($this->author)->test(MesAttestations::class)->assertDontSeeText('Ce compte semble usurper');
    expect(fn () => $this->trust->report($this->author, $this->author, null, 'identity', 'Mon compte'))->toThrow(ValidationException::class);
    expect(fn () => $this->trust->report($this->stranger, $this->witness, null, 'identity', str_repeat('a', 501)))->toThrow(ValidationException::class);
});

it('ne retire rien sur simple signalement et exige une réponse motivée sans conflit d’intérêt', function () {
    verifyIdentityFor($this->author, $this->admin);
    $this->trust->report($this->stranger, $this->author, null, 'identity', 'Une erreur de nom à contrôler.');
    $report = TrustReport::sole();
    expect($this->trust->identity($this->author))->not->toBeNull();
    expect(fn () => $this->trust->closeReport($this->admin, $report->id, $report->revision, ''))->toThrow(ValidationException::class);
    $this->trust->closeReport($this->admin, $report->id, $report->revision, 'Contrôle effectué, aucun retrait justifié.');
    expect($report->fresh()->state)->toBe('closed')->and(AdminAction::where('action', 'trust.report_closed')->count())->toBe(1);
    Livewire::actingAs($this->stranger)->test(MesAttestations::class)->assertSeeText('Contrôle effectué, aucun retrait justifié.');
});

it('permet un recours puis un nouveau contrôle sans rétablissement automatique', function () {
    $row = verifyIdentityFor($this->author, $this->admin);
    $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'revoke', 'Une vérification complémentaire est nécessaire.');
    $row->refresh();
    expect(fn () => $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'reopen', 'Nouvel examen'))->toThrow(ValidationException::class);
    $this->trust->report($this->author, $this->author, null, 'appeal', 'Je demande le réexamen de mon identité.');
    $this->trust->reviewIdentity($this->admin, $row->id, $row->revision, 'reopen', 'Un nouveau contrôle sera effectué.');
    expect($row->fresh()->state)->toBe('pending')->and($this->trust->identity($this->author))->toBeNull();
});

it('réexamine une référence contestée mais exige de nouveau le consentement du témoin', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    $this->trust->revokeWork($this->admin, $row->id, $row->revision, 'Une contestation à examiner.');
    $row->refresh();
    $this->trust->report($this->author, $this->author, $row, 'appeal', 'L’intervention a bien eu lieu.');
    $this->trust->reopenWork($this->admin, $row->id, $row->revision, 'Après examen, demander une nouvelle confirmation.');
    expect($row->fresh()->state)->toBe('pending')->and(WorkAttestation::visibleTo(null)->count())->toBe(0);
    $this->trust->decideWork($this->witness, $row->id, $row->fresh()->revision, 'confirm', 'client', true);
    expect(WorkAttestation::visibleTo(null)->count())->toBe(1);
});

it('regroupe les notifications internes sans exposer de nom ni texte privé', function () {
    $this->trust->requestIdentity($this->author, true);
    $this->trust->requestIdentity($this->witness, true);
    expect(UserNotification::where('user_id', $this->admin->id)->where('kind', NotificationKind::TrustReview->value)->count())->toBe(1);
    $notification = UserNotification::where('user_id', $this->admin->id)->where('kind', NotificationKind::TrustReview->value)->sole();
    expect($notification->actor_id)->toBeNull()->and($notification->publication_id)->toBeNull();
    $this->actingAs($this->admin)->get(route('notifications.open', $notification))->assertRedirect(route('moderation.confiance'));
});

it('supprime les dossiers et références avec les comptes ou publications concernés', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    $this->trust->report($this->stranger, $this->author, $row, 'reference', 'Une référence à examiner.');
    $row->publication->delete();
    expect(WorkAttestation::count())->toBe(0)->and(TrustReport::count())->toBe(0);
    $this->witness->delete();
    expect(IdentityVerification::count())->toBe(0);
});

it('offre les parcours Livewire de sollicitation confirmation et signalement', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    Livewire::actingAs($this->author)->test(AttesterRealisation::class, ['publication' => $row->publication])->assertSeeText('Expériences confirmées')->assertSeeText('Faire confirmer');
    Livewire::actingAs($this->witness)->test(MesAttestations::class)->call('decide', $row->id, $row->revision, 'withdraw')->assertHasNoErrors()->assertSeeText('Consentement retiré');
    Livewire::actingAs($this->stranger)->test(SignalerProfil::class, ['profile' => $this->author->profile])->set('details', 'Un doute sur ce compte à examiner.')->call('submit')->assertHasNoErrors()->assertSeeText('Votre demande a été enregistrée');
    Livewire::actingAs($this->admin)->test(ModerationConfiance::class)->assertSeeText('Un doute sur ce compte à examiner.');
});

it('borne les sollicitations à trois témoins par réalisation et cinq demandes par jour', function () {
    $post = publication($this->author, PublicationType::PersonalWork);
    foreach (range(1, 4) as $i) {
        $witness = person('Témoin '.$i);
        verifyIdentityFor($witness, $this->admin);
        $people = app(People::class);
        $people->request($this->author, $witness, 'Vous avez constaté mon travail.');
        $pair = $people->pair($this->author, $witness)->sole();
        $people->decide($witness, $this->author, 'accept', $pair->revision);
        if ($i <= 3) {
            $this->trust->requestWork($this->author, $post, $witness->id, 'Une intervention précise', today()->toDateString());
        } else {
            expect(fn () => $this->trust->requestWork($this->author, $post, $witness->id, 'Une intervention précise', today()->toDateString()))->toThrow(ValidationException::class);
        }
    }
    expect(WorkAttestation::count())->toBe(3);
    foreach (range(1, 5) as $i) {
        RateLimiter::hit('trust-work:'.$this->author->id, 86400);
    }
    $otherPost = publication($this->author, PublicationType::PersonalWork);
    expect(fn () => $this->trust->requestWork($this->author, $otherPost, $witness->id, 'Une autre intervention', today()->toDateString()))->toThrow(ValidationException::class);
});

it('borne les signalements quotidiens sans permettre de lire une référence privée devinée', function () {
    $row = confirmedWorkFor($this->author, $this->witness, $this->admin);
    $this->witness->profile->update(['visibility' => 'members']);
    $row->update(['state' => 'pending']);
    expect(fn () => $this->trust->report($this->stranger, $this->author, $row, 'reference', 'Référence devinée'))->toThrow(HttpException::class);
    foreach (range(1, 5) as $i) {
        RateLimiter::hit('trust-report:'.$this->stranger->id, 86400);
    }
    expect(fn () => $this->trust->report($this->stranger, $this->author, null, 'abuse', 'Un abus à examiner'))->toThrow(ValidationException::class);
    expect(TrustReport::count())->toBe(0);
});

it('refuse au super administrateur de clore son propre signalement', function () {
    $this->trust->report($this->admin, $this->author, null, 'identity', 'Une identité à examiner par un autre opérateur.');
    $report = TrustReport::sole();
    expect(fn () => $this->trust->closeReport($this->admin, $report->id, $report->revision, 'Je ferme moi-même'))->toThrow(AuthorizationException::class);
    expect($report->fresh()->state)->toBe('open');
});
