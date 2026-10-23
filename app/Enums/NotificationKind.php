<?php

namespace App\Enums;

/** Ce qui peut être signalé à une personne dans l'application (écran 15). Jamais par e-mail ni par SMS à ce stade. */
enum NotificationKind: string
{
    case GestureReceived = 'gesture_received';
    case GestureAccepted = 'gesture_accepted';
    case MessageReceived = 'message_received';
    case GratitudeReceived = 'gratitude_received';
    case PublicationHidden = 'publication_hidden';
    case PublicationRestored = 'publication_restored';
    case ZumraRequestReceived = 'zumra_request_received';
    case ZumraRequestAccepted = 'zumra_request_accepted';
    case ZumraInvitationReceived = 'zumra_invitation_received';
    case ZumraInvitationAccepted = 'zumra_invitation_accepted';
    case ZumraRoleGranted = 'zumra_role_granted';
    case ZumraMemberRemoved = 'zumra_member_removed';
    case ZumraFounderTransferred = 'zumra_founder_transferred';
    case ZumraRecognized = 'zumra_recognized';
    case ZumraRecognitionWithdrawn = 'zumra_recognition_withdrawn';
    case ProjectParticipationRequested = 'project_participation_requested';
    case ProjectParticipationAccepted = 'project_participation_accepted';
    case ProjectMemberAdded = 'project_member_added';
    case ProjectMemberRemoved = 'project_member_removed';

    /** Le texte de la notification commence-t-il par le nom d'une personne ? (Non pour un transfert de fondation.) */
    public function showsActor(): bool
    {
        return $this !== self::ZumraFounderTransferred;
    }

    /** Une notification qui parle d'une ZUMRA (son nom prend la place de l'extrait de publication). */
    public function isAboutZumra(): bool
    {
        return str_starts_with($this->value, 'zumra_');
    }

    /** Une notification qui parle d'un projet (son nom prend la place de l'extrait de publication). */
    public function isAboutProject(): bool
    {
        return str_starts_with($this->value, 'project_');
    }

    /** Phrase qui suit le nom de la personne : « Awa Traoré a proposé son aide… ». */
    public function sentence(?string $about): string
    {
        $about = $about === null ? '' : " « {$about} »";

        return match ($this) {
            self::GestureReceived => "a répondu à votre publication{$about}.",
            self::GestureAccepted => "a accepté votre réponse{$about}.",
            self::MessageReceived => 'vous a écrit un message.',
            self::GratitudeReceived => 'vous remercie sur votre profil.',
            self::PublicationHidden => "a masqué votre publication{$about}. Vous pouvez vous expliquer.",
            self::PublicationRestored => "a rétabli votre publication{$about}.",
            self::ZumraRequestReceived => "demande à rejoindre{$about}.",
            self::ZumraRequestAccepted => "a accepté votre demande de rejoindre{$about}.",
            self::ZumraInvitationReceived => "vous invite à rejoindre{$about}.",
            self::ZumraInvitationAccepted => "a accepté votre invitation à rejoindre{$about}.",
            self::ZumraRoleGranted => "vous a confié un rôle de responsable dans{$about}.",
            self::ZumraMemberRemoved => "vous a retiré de{$about}.",
            self::ZumraFounderTransferred => "La fondation de{$about} vous a été confiée : son fondateur a supprimé son compte.",
            self::ZumraRecognized => "a reconnu officiellement{$about} : elle porte le badge « Reconnue par GAMAD ».",
            self::ZumraRecognitionWithdrawn => "a retiré la reconnaissance officielle de{$about}.",
            self::ProjectParticipationRequested => "veut participer au projet{$about}.",
            self::ProjectParticipationAccepted => "a accepté votre participation au projet{$about}.",
            self::ProjectMemberAdded => "vous a ajouté à l'équipe du projet{$about}.",
            self::ProjectMemberRemoved => "vous a retiré de l'équipe du projet{$about}.",
        };
    }
}
