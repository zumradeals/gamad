<?php

namespace App\Enums;

/**
 * Types de publication (D-081). « Appel à rejoindre » est propre aux ZUMRA (Lot 3), « Avancée de projet » et « Réalisation »
 * aux projets (Lot 4).
 */
enum PublicationType: string
{
    case Need = 'need';
    case Knowledge = 'knowledge';
    case LearningWish = 'learning_wish';
    case Offer = 'offer';
    case ZumraCall = 'zumra_call';
    case ProjectUpdate = 'project_update';
    case Achievement = 'achievement';

    /** Libellé affiché (glossaire de docs/PRODUIT.md §5). */
    public function label(): string
    {
        return match ($this) {
            self::Need => "J'ai besoin",
            self::Knowledge => 'Je partage un savoir',
            self::LearningWish => 'Je veux apprendre',
            self::Offer => 'Je propose mon aide',
            self::ZumraCall => 'Appel à rejoindre',
            self::ProjectUpdate => 'Avancée de projet',
            self::Achievement => 'Réalisation',
        };
    }

    /** Fin d'adresse de l'écran « Publier » : /agir/{slug}. */
    public function slug(): string
    {
        return match ($this) {
            self::Need => 'besoin',
            self::Knowledge => 'savoir',
            self::LearningWish => 'apprendre',
            self::Offer => 'aide',
            self::ZumraCall => 'appel',
            self::ProjectUpdate => 'avancee',
            self::Achievement => 'realisation',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->slug() === $slug) {
                return $type;
            }
        }

        return null;
    }

    /** Geste principal que les autres personnes envoient en réponse (PRODUIT §5). */
    public function gestureLabel(): string
    {
        return match ($this) {
            self::Need => 'Je peux aider',
            self::Knowledge => 'Je veux apprendre',
            self::LearningWish => 'Je peux transmettre',
            self::Offer => "Ça m'intéresse",
            self::ZumraCall => 'Je veux rejoindre',
            // Une avancée ou une réalisation n'a pas encore de geste (« Je soutiens » vient avec la PR 4c).
            self::ProjectUpdate, self::Achievement => '',
        };
    }

    /** Fait d'engagement réel, compté à partir des gestes (jamais d'approbation) : « 2 personnes proposent leur aide ». */
    public function factLabel(int $count): string
    {
        $plural = $count > 1;

        return $count.' '.match ($this) {
            self::Need => $plural ? 'personnes proposent leur aide' : 'personne propose son aide',
            self::Knowledge => $plural ? 'personnes veulent apprendre' : 'personne veut apprendre',
            self::LearningWish => $plural ? 'personnes peuvent transmettre' : 'personne peut transmettre',
            self::Offer => $plural ? 'personnes sont intéressées' : 'personne est intéressée',
            self::ZumraCall => $plural ? 'personnes veulent rejoindre' : 'personne veut rejoindre',
            self::ProjectUpdate, self::Achievement => '',
        };
    }

    /** Titre de la liste des gestes reçus, sur l'écran de l'auteur. */
    public function receivedTitle(): string
    {
        return match ($this) {
            self::Need => 'Propositions reçues',
            self::Knowledge => 'Personnes qui veulent apprendre',
            self::LearningWish => 'Personnes qui peuvent transmettre',
            self::Offer => 'Personnes intéressées',
            self::ZumraCall => 'Demandes pour rejoindre',
            self::ProjectUpdate, self::Achievement => '',
        };
    }

    /** Titre de l'écran de l'auteur sur sa propre publication. */
    public function ownTitle(): string
    {
        return match ($this) {
            self::Need => 'Mon besoin',
            self::Knowledge => 'Mon savoir partagé',
            self::LearningWish => 'Mon souhait d’apprendre',
            self::Offer => 'Mon offre d’aide',
            self::ZumraCall => 'Notre appel à rejoindre',
            self::ProjectUpdate => 'Notre avancée de projet',
            self::Achievement => 'Notre réalisation',
        };
    }

    /** Classes Tailwind du badge (jetons de DESIGN.md §1). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Need => 'bg-type-need-bg text-type-need-fg',
            self::Knowledge => 'bg-type-knowledge-bg text-type-knowledge-fg',
            self::LearningWish => 'bg-type-learning-bg text-type-learning-fg',
            self::Offer => 'bg-type-offer-bg text-type-offer-fg',
            self::ZumraCall => 'bg-type-call-bg text-type-call-fg',
            self::ProjectUpdate => 'bg-type-project-bg text-type-project-fg',
            self::Achievement => 'bg-type-knowledge-bg text-type-knowledge-fg',
        };
    }

    /** Types qu'une personne publie en son nom (D-081) : l'Appel à rejoindre n'existe que pour une ZUMRA. */
    public static function forPerson(): array
    {
        return [self::Need, self::Knowledge, self::LearningWish, self::Offer];
    }

    /** Types publiés au nom d'une ZUMRA (D-116) : « Je veux apprendre » reste personnel, les avancées et réalisations se font au nom d'un projet (forProject). */
    public static function forZumra(): array
    {
        return [self::Need, self::Knowledge, self::Offer, self::ZumraCall];
    }

    /** Types publiés au nom d'un projet (D-134) : l'avancée de projet et la réalisation. */
    public static function forProject(): array
    {
        return [self::ProjectUpdate, self::Achievement];
    }

    /** Publiée au nom d'un projet. */
    public function isProject(): bool
    {
        return $this === self::ProjectUpdate || $this === self::Achievement;
    }

    /** Ce type reçoit des gestes : ni l'Appel à rejoindre (demande d'adhésion), ni les publications de projet (PR 4b). */
    public function takesGestures(): bool
    {
        return ! $this->isCall() && ! $this->isProject();
    }

    /** Une réponse à cette publication est une demande d'adhésion (D-116), pas un geste. */
    public function isCall(): bool
    {
        return $this === self::ZumraCall;
    }

    public function isNeed(): bool
    {
        return $this === self::Need;
    }

    /** Phrase d'invitation de l'écran « Agir » (écran 8). */
    public function pitch(): string
    {
        return match ($this) {
            self::Need => "Dites ce qui vous manque. Quelqu'un peut vous aider.",
            self::Knowledge => 'Partagez un conseil, une fiche ou un atelier.',
            self::LearningWish => "Dites ce que vous voulez apprendre. Quelqu'un peut vous transmettre.",
            self::Offer => 'Proposez votre aide à qui en a besoin.',
            self::ZumraCall => 'Invitez des personnes à rejoindre votre ZUMRA.',
            self::ProjectUpdate => 'Dites où en est le projet : ce qui avance, ce qui change.',
            self::Achievement => 'Montrez ce qui a été fait.',
        };
    }
}
