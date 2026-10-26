<?php

namespace App\Http\Controllers;

use App\Enums\NotificationKind;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/** Ouvrir une notification : elle est marquée lue, puis on va à ce qu'elle annonce. Celle d'une autre personne n'existe pas (404). */
class NotificationController extends Controller
{
    public function open(UserNotification $notification): RedirectResponse
    {
        abort_unless(auth()->id() === $notification->user_id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        $user = auth()->user();
        $conversation = $notification->conversation;
        $publication = $notification->publication;

        $target = match ($notification->kind) {
            NotificationKind::TrustUpdated => route('espace.attestations'),
            NotificationKind::CompanyExpRequested => $notification->company?->verified() && $notification->company->owner_id === $user->id
                ? route('entreprises.experiences.review', $notification->company) : route('notifications'),
            NotificationKind::CompanyExpConfirmed, NotificationKind::CompanyExpDeclined => $notification->companyExperience?->user_id === $user->id
                ? route('espace.company-experiences') : route('notifications'),
            NotificationKind::CompanyReviewUpdated => $notification->company?->owner_id === $user->id
                ? route('entreprises.show', $notification->company) : route('notifications'),
            NotificationKind::CompanyReviewRequested, NotificationKind::CompanyReportReceived => $user->isSuperAdmin()
                ? route('moderation.entreprises') : route('notifications'),
            NotificationKind::OpportunityResponseReceived => $notification->opportunity && $notification->opportunity->company->owner_id === $user->id
                ? route('opportunites.responses', $notification->opportunity) : route('notifications'),
            NotificationKind::OpportunityResponseContacted => $notification->opportunity?->visibleTo($user)
                ? route('opportunites.show', $notification->opportunity) : route('notifications'),
            NotificationKind::TrustReview => $user->isSuperAdmin() ? route('moderation.confiance') : route('espace'),
            NotificationKind::MatchFound => route('espace', ['rubrique' => 'pistes']).'#suggestions',
            NotificationKind::ConnectionRequested => route('espace', ['rubrique' => 'relations']).'#mes-liens',
            NotificationKind::ConnectionAccepted => $conversation && Gate::forUser($user)->allows('view', $conversation) ? route('conversations.show', $conversation) : route('espace', ['rubrique' => 'relations']).'#mes-liens',
            NotificationKind::MessageReceived, NotificationKind::GestureAccepted => $conversation && Gate::forUser($user)->allows('view', $conversation)
                ? route('conversations.show', $conversation)
                : ($publication && Gate::forUser($user)->allows('view', $publication) ? route('publications.show', $publication) : null),
            NotificationKind::GestureReceived => $publication && Gate::forUser($user)->allows('view', $publication) ? route('publications.show', $publication) : null,
            NotificationKind::GratitudeReceived => route('personnes.show', $user->ensureProfile()->public_id),
            NotificationKind::PublicationHidden, NotificationKind::PublicationRestored => $publication && Gate::forUser($user)->allows('view', $publication) ? route('publications.show', $publication) : null,
            NotificationKind::PublicationExplained => route('moderation', ['onglet' => 'masquees']),
            NotificationKind::ModerationNoticeExplained => route('moderation.personnes'),
            NotificationKind::ModerationNoticeIssued => route('avis'),
            NotificationKind::ModerationNoticeClosed => route('espace'),
            NotificationKind::PersonReinstated => route('fil'),
            NotificationKind::AdAccepted, NotificationKind::AdRefused => Route::has('publicite.campagnes') ? route('publicite.campagnes') : null,
            NotificationKind::ZumraDutyProposed => route('espace.zumras'),
            // Toutes les notifications d'une ZUMRA mènent à son Monde (la ZUMRA est publique : elle existe pour tous).
            NotificationKind::ZumraRequestReceived, NotificationKind::ZumraRequestAccepted, NotificationKind::ZumraInvitationReceived,
            NotificationKind::ZumraInvitationAccepted, NotificationKind::ZumraRoleGranted, NotificationKind::ZumraMemberRemoved, NotificationKind::ZumraDutyAccepted, NotificationKind::ZumraDutyDeclined,
            NotificationKind::ZumraFounderTransferred, NotificationKind::ZumraRecognized, NotificationKind::ZumraRecognitionWithdrawn => $notification->zumra ? route('zumras.show', $notification->zumra) : null,
            // Les notifications d'un projet mènent à son Monde, onglet Équipe (le projet est public).
            NotificationKind::ProjectParticipationRequested, NotificationKind::ProjectParticipationAccepted,
            NotificationKind::ProjectMemberAdded, NotificationKind::ProjectMemberRemoved => $notification->project ? route('projets.show', ['project' => $notification->project, 'onglet' => 'equipe']) : null,
            NotificationKind::ProjectHostingAsked => $notification->project ? route('zumras.show', ['zumra' => $notification->project->zumra_id, 'onglet' => 'projets']) : null,
            NotificationKind::ProjectHostingAccepted, NotificationKind::ProjectHostingDeclined, NotificationKind::ProjectInviteAccepted, NotificationKind::ProjectCompanyAnswered => $notification->project ? route('projets.show', ['project' => $notification->project, 'onglet' => 'accueil']) : null,
            NotificationKind::ProjectInvited, NotificationKind::ProjectCompanyAsked => route('espace.projets'),
            // Une proposition de contribution : seules les personnes qui peuvent la voir y accèdent ; sinon retour à la liste.
            NotificationKind::ContributionProposed, NotificationKind::ContributionAccepted, NotificationKind::ContributionDeclined,
            NotificationKind::ContributionDeclared, NotificationKind::ContributionWithdrawn, NotificationKind::ContributionRealized => $notification->contributionProposal && Gate::forUser($user)->allows('view', $notification->contributionProposal)
                ? route('contributions.show', $notification->contributionProposal) : null,
        };

        // Ce que la notification annonçait n'existe plus (publication ou conversation supprimée) : retour à la liste.
        return new RedirectResponse($target ?? route('notifications'));
    }
}
