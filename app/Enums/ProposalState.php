<?php

namespace App\Enums;

/**
 * États d'une proposition de contribution (C1) : proposée, acceptée ou refusée par le bénéficiaire, retirée par son auteur, puis
 * déclarée réalisée par la personne qui contribue et confirmée par le bénéficiaire. Une acceptation ne vaut jamais réalisation.
 */
enum ProposalState: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Declared = 'declared';
    case Realized = 'realized';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposée',
            self::Accepted => 'Acceptée',
            self::Declined => 'Refusée',
            self::Withdrawn => 'Retirée',
            self::Declared => 'Déclarée réalisée',
            self::Realized => 'Réalisation confirmée',
        };
    }

    /** Encore en cours : le bénéficiaire peut décider, l'auteur peut retirer ou déclarer la réalisation. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Proposed, self::Accepted, self::Declared], true);
    }

    /** Engagement accepté par le bénéficiaire (réalisé ou non). */
    public function isCommitted(): bool
    {
        return in_array($this, [self::Accepted, self::Declared, self::Realized], true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Proposed => 'bg-type-need-bg text-type-need-fg',
            self::Accepted, self::Declared => 'bg-type-learning-bg text-type-learning-fg',
            self::Realized => 'bg-type-knowledge-bg text-type-knowledge-fg',
            self::Declined, self::Withdrawn => 'bg-ground text-muted',
        };
    }
}
