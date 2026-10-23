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
        };
    }
}
