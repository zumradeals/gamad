<?php

namespace App\Enums;

/** Ce qui peut être signalé à une personne dans l'application (écran 15). Jamais par e-mail ni par SMS à ce stade. */
enum NotificationKind: string
{
    case TrustUpdated = 'trust_updated';
    case CompanyExpRequested = 'company_exp_requested';
    case CompanyExpConfirmed = 'company_exp_confirmed';
    case CompanyExpDeclined = 'company_exp_declined';
    case CompanyReviewUpdated = 'company_review_updated';
    case CompanyReviewRequested = 'company_review_requested';
    case CompanyReportReceived = 'company_report_received';
    case OpportunityResponseReceived = 'opportunity_response_received';
    case OpportunityResponseContacted = 'opportunity_response_contacted';
    case TrustReview = 'trust_review';
    case MatchFound = 'match_found';
    case ConnectionRequested = 'connection_requested';
    case ConnectionAccepted = 'connection_accepted';
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
    case ZumraDutyProposed = 'zumra_duty_proposed';
    case ZumraDutyAccepted = 'zumra_duty_accepted';
    case ZumraDutyDeclined = 'zumra_duty_declined';
    case ZumraMemberRemoved = 'zumra_member_removed';
    case ZumraFounderTransferred = 'zumra_founder_transferred';
    case ZumraRecognized = 'zumra_recognized';
    case ZumraRecognitionWithdrawn = 'zumra_recognition_withdrawn';
    case ZumraSuspended = 'zumra_suspended';
    case PayoutPaid = 'payout_paid';
    case PayoutDeclined = 'payout_declined';
    case ZumraMemberJoined = 'zumra_member_joined';
    case ZumraFounderGiven = 'zumra_founder_given';
    case ZumraReinstated = 'zumra_reinstated';
    case ZumraMessageHidden = 'zumra_message_hidden';
    case ProjectParticipationRequested = 'project_participation_requested';
    case ProjectParticipationAccepted = 'project_participation_accepted';
    case ProjectMemberAdded = 'project_member_added';
    case ProjectMemberRemoved = 'project_member_removed';
    case ProjectHostingAsked = 'project_hosting_asked';
    case ProjectHostingAccepted = 'project_hosting_accepted';
    case ProjectHostingDeclined = 'project_hosting_declined';
    case ProjectInvited = 'project_invited';
    case ProjectInviteAccepted = 'project_invite_accepted';
    case ProjectCompanyAsked = 'project_company_asked';
    case ProjectCompanyAnswered = 'project_company_answered';
    case ContributionProposed = 'contribution_proposed';
    case ContributionAccepted = 'contribution_accepted';
    case ContributionDeclined = 'contribution_declined';
    case ContributionDeclared = 'contribution_declared';
    case ContributionWithdrawn = 'contribution_withdrawn';
    case ContributionRealized = 'contribution_realized';
    case PublicationExplained = 'publication_explained';
    case ModerationNoticeIssued = 'moderation_notice_issued';
    case ModerationNoticeExplained = 'moderation_notice_explained';
    case ModerationNoticeClosed = 'moderation_notice_closed';
    case PersonReinstated = 'person_reinstated';
    case AdAccepted = 'ad_accepted';
    case AdRefused = 'ad_refused';

    /** Une notification qui s'adresse à la modération (et non à l'auteur d'un acte de modération). */
    public function isForModeration(): bool
    {
        return in_array($this, [self::PublicationExplained, self::ModerationNoticeExplained], true);
    }

    /** Le texte de la notification commence-t-il par le nom d'une personne ? (Non pour un transfert de fondation.) */
    public function showsActor(): bool
    {
        return ! in_array($this, [self::ZumraFounderTransferred, self::MatchFound, self::TrustUpdated, self::TrustReview, self::CompanyReviewUpdated, self::CompanyReviewRequested, self::CompanyReportReceived, self::AdAccepted, self::AdRefused], true);
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

    /** Une notification qui parle d'une proposition de contribution (le nom du bénéficiaire prend la place de l'extrait). */
    public function isAboutContribution(): bool
    {
        return str_starts_with($this->value, 'contribution_');
    }

    /** Phrase qui suit le nom de la personne : « Awa Traoré a proposé son aide… ». */
    public function sentence(?string $about): string
    {
        $about = $about === null ? '' : " « {$about} »";

        return match ($this) {
            self::TrustUpdated => 'Votre espace d’attestations a été mis à jour.',
            self::CompanyExpRequested => 'vous demande de confirmer une expérience professionnelle.',
            self::CompanyExpConfirmed => 'a confirmé votre expérience professionnelle.',
            self::CompanyExpDeclined => 'n’a pas confirmé votre expérience professionnelle.',
            self::CompanyReviewUpdated => 'La vérification administrative de votre entreprise a changé. Consultez votre page.',
            self::CompanyReviewRequested => 'Un dossier entreprise attend un contrôle administratif.',
            self::CompanyReportReceived => 'Un signalement concernant une page entreprise attend un examen.',
            self::OpportunityResponseReceived => "a répondu à votre opportunité{$about}.",
            self::OpportunityResponseContacted => "souhaite échanger avec vous au sujet de l'opportunité{$about}.",
            self::TrustReview => 'Des demandes de confiance attendent un examen.',
            self::MatchFound => 'De nouvelles pistes correspondent à vos préférences. Découvrez pourquoi.',
            self::ConnectionRequested => 'souhaite entrer en relation avec vous.',
            self::ConnectionAccepted => 'a accepté votre demande de relation.',
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
            self::ZumraDutyProposed => "vous propose un poste de responsabilité dans{$about} : à accepter ou refuser.",
            self::ZumraDutyAccepted => "a accepté le poste proposé dans{$about}.",
            self::ZumraDutyDeclined => "a refusé le poste proposé dans{$about}.",
            self::ZumraMemberRemoved => "vous a retiré de{$about}.",
            self::ZumraFounderTransferred => "La fondation de{$about} vous a été confiée : son fondateur a supprimé son compte.",
            self::ZumraRecognized => "a reconnu officiellement{$about} : elle porte le badge « Reconnue par GAMAD ».",
            self::ZumraRecognitionWithdrawn => "a retiré la reconnaissance officielle de{$about}.",
            self::ZumraMemberJoined => "a rejoint{$about}.",
            self::ZumraFounderGiven => "vous a confié la fondation de{$about}.",
            self::PayoutPaid => 'Votre reversement a été versé sur le numéro indiqué.',
            self::PayoutDeclined => 'Votre reversement n’a pas pu être effectué : la somme est revenue dans votre Zahab Wallet.',
            self::ZumraSuspended => "a suspendu{$about}. Le motif est affiché sur la page de la ZUMRA.",
            self::ZumraReinstated => "a rétabli{$about}.",
            self::ZumraMessageHidden => "a masqué l’un de vos messages dans{$about}.",
            self::ProjectParticipationRequested => "veut participer au projet{$about}.",
            self::ProjectParticipationAccepted => "a accepté votre participation au projet{$about}.",
            self::ProjectMemberAdded => "vous a ajouté à l'équipe du projet{$about}.",
            self::ProjectMemberRemoved => "vous a retiré de l'équipe du projet{$about}.",
            self::ProjectHostingAsked => "propose d'accueillir dans votre ZUMRA le projet{$about}.",
            self::ProjectHostingAccepted => "a accepté d'accueillir votre projet{$about}.",
            self::ProjectHostingDeclined => "n'a pas retenu la demande d'accueil de votre projet{$about}.",
            self::ProjectInvited => "vous invite à collaborer au projet{$about} : à accepter ou refuser.",
            self::ProjectInviteAccepted => "a accepté de collaborer au projet{$about}.",
            self::ProjectCompanyAsked => "propose de relier le projet{$about} à votre entreprise.",
            self::ProjectCompanyAnswered => "a répondu à votre demande de lien entre le projet{$about} et son entreprise.",
            self::ContributionProposed => "vous propose une contribution{$about}.",
            self::ContributionAccepted => "a accepté votre proposition de contribution{$about}.",
            self::ContributionDeclined => "n’a pas retenu votre proposition de contribution{$about}.",
            self::ContributionWithdrawn => "a retiré sa proposition de contribution{$about}.",
            self::ContributionDeclared => "déclare avoir réalisé sa contribution{$about} : à confirmer.",
            self::ContributionRealized => "a confirmé la réalisation de votre contribution{$about}.",
            self::PublicationExplained => "a écrit son explication sur la publication masquée{$about}.",
            self::ModerationNoticeIssued => 'vous a envoyé un avis de la modération. Vous pouvez vous expliquer.',
            self::ModerationNoticeExplained => 'a répondu à l’avis de la modération.',
            self::ModerationNoticeClosed => 'a classé l’avis de la modération : aucune suite.',
            self::PersonReinstated => 'a rétabli votre compte.',
            self::AdAccepted => 'Votre annonce a été acceptée par la modération. Elle reste « Prête » tant que la diffusion n’est pas ouverte.',
            self::AdRefused => 'Votre annonce n’a pas été acceptée par la modération. Consultez le motif et corrigez-la.',
        };
    }
}
