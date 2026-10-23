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
            NotificationKind::MessageReceived, NotificationKind::GestureAccepted => $conversation && Gate::forUser($user)->allows('view', $conversation)
                ? route('conversations.show', $conversation)
                : ($publication && Gate::forUser($user)->allows('view', $publication) ? route('publications.show', $publication) : null),
            NotificationKind::GestureReceived => $publication && Gate::forUser($user)->allows('view', $publication) ? route('publications.show', $publication) : null,
            NotificationKind::GratitudeReceived => route('personnes.show', $user->ensureProfile()->public_id),
            NotificationKind::PublicationHidden, NotificationKind::PublicationRestored => $publication && Gate::forUser($user)->allows('view', $publication) ? route('publications.show', $publication) : null,
            // Toutes les notifications d'une ZUMRA mènent à son Monde (la ZUMRA est publique : elle existe pour tous).
            NotificationKind::ZumraRequestReceived, NotificationKind::ZumraRequestAccepted, NotificationKind::ZumraInvitationReceived,
            NotificationKind::ZumraInvitationAccepted, NotificationKind::ZumraRoleGranted, NotificationKind::ZumraMemberRemoved,
            NotificationKind::ZumraFounderTransferred, NotificationKind::ZumraRecognized, NotificationKind::ZumraRecognitionWithdrawn => $notification->zumra ? route('zumras.show', $notification->zumra) : null,
            // Les notifications d'un projet mènent à son Monde, onglet Équipe (le projet est public).
            NotificationKind::ProjectParticipationRequested, NotificationKind::ProjectParticipationAccepted,
            NotificationKind::ProjectMemberAdded, NotificationKind::ProjectMemberRemoved => $notification->project ? route('projets.show', ['project' => $notification->project, 'onglet' => 'equipe']) : null,
        };

        // Ce que la notification annonçait n'existe plus (publication ou conversation supprimée) : retour à la liste.
        return redirect($target ?? route('notifications'));
    }
}
