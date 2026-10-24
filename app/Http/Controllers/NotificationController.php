<?php

namespace App\Http\Controllers;

use App\Enums\NotificationKind;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

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
            NotificationKind::CompanyRequest => route('entreprises.create'),
            NotificationKind::CompanyDecision => route('espace.company-experiences'),
            NotificationKind::CompanyReview => route('entreprises.create'),
            NotificationKind::CompanyAppeal => $user->isSuperAdmin() ? route('moderation.entreprises') : route('espace'),
            NotificationKind::TrustReview => $user->isSuperAdmin() ? route('moderation.confiance') : route('espace'),
            NotificationKind::MatchFound => route('espace').'#suggestions',
            NotificationKind::ConnectionRequested => route('espace').'#mes-liens',
            NotificationKind::ConnectionAccepted => $conversation && Gate::forUser($user)->allows('view', $conversation) ? route('conversations.show', $conversation) : route('espace').'#mes-liens',
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
            // Toutes les notifications d'une ZUMRA mènent à son Monde (la ZUMRA est publique : elle existe pour tous).
            NotificationKind::ZumraRequestReceived, NotificationKind::ZumraRequestAccepted, NotificationKind::ZumraInvitationReceived,
            NotificationKind::ZumraInvitationAccepted, NotificationKind::ZumraRoleGranted, NotificationKind::ZumraMemberRemoved,
            NotificationKind::ZumraFounderTransferred, NotificationKind::ZumraRecognized, NotificationKind::ZumraRecognitionWithdrawn => $notification->zumra ? route('zumras.show', $notification->zumra) : null,
            // Les notifications d'un projet mènent à son Monde, onglet Équipe (le projet est public).
            NotificationKind::ProjectParticipationRequested, NotificationKind::ProjectParticipationAccepted,
            NotificationKind::ProjectMemberAdded, NotificationKind::ProjectMemberRemoved => $notification->project ? route('projets.show', ['project' => $notification->project, 'onglet' => 'equipe']) : null,
        };

        // Ce que la notification annonçait n'existe plus (publication ou conversation supprimée) : retour à la liste.
        return new RedirectResponse($target ?? route('notifications'));
    }
}
