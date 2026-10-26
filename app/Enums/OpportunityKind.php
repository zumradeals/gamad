<?php

namespace App\Enums;

/** Les quatre catégories d'opportunités publiées par les entreprises vérifiées. */
enum OpportunityKind: string
{
    case Job = 'job';
    case Internship = 'internship';
    case Mission = 'mission';
    case Collaboration = 'collaboration';

    public function label(): string
    {
        return match ($this) {
            self::Job => 'Emploi',
            self::Internship => 'Stage',
            self::Mission => 'Mission',
            self::Collaboration => 'Collaboration',
        };
    }

    /** Libellé de filtre : « Emplois », « Stages et apprentissage ». */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Job => 'Emplois',
            self::Internship => 'Stages et apprentissage',
            self::Mission => 'Missions',
            self::Collaboration => 'Collaborations',
        };
    }

    public function chooseLabel(): string
    {
        return match ($this) {
            self::Job => 'Un emploi',
            self::Internship => 'Un stage ou apprentissage',
            self::Mission => 'Une mission',
            self::Collaboration => 'Une collaboration',
        };
    }

    public function chooseHint(): string
    {
        return match ($this) {
            self::Job => 'Un poste salarié ou une fonction professionnelle.',
            self::Internship => 'Apprendre un métier ou acquérir une expérience pratique.',
            self::Mission => 'Un travail ponctuel ou une intervention précise.',
            self::Collaboration => 'Un partenaire, une compétence ou une collaboration professionnelle.',
        };
    }

    public function titleQuestion(): string
    {
        return match ($this) {
            self::Job => 'Quel est le poste ?',
            self::Internship => 'Quel est le stage ou l’apprentissage ?',
            self::Mission => 'Quelle est la mission ?',
            self::Collaboration => 'Quelle collaboration recherchez-vous ?',
        };
    }

    public function titlePlaceholder(): string
    {
        return match ($this) {
            self::Job => 'Ex. Ingénieur réseau',
            self::Internship => 'Ex. Stagiaire en marketing digital',
            self::Mission => 'Ex. Électricien pour projet solaire',
            self::Collaboration => 'Ex. Partenaire de distribution à Bouaké',
        };
    }

    /** Verbe d'action : candidater pour un emploi ou un stage, exprimer son intérêt pour le reste. */
    public function applyLabel(): string
    {
        return in_array($this, [self::Job, self::Internship], true) ? 'Postuler' : 'Je suis intéressé';
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Job => 'bg-type-call-bg text-type-call-fg',
            self::Internship => 'bg-type-knowledge-bg text-type-knowledge-fg',
            self::Mission => 'bg-type-need-bg text-type-need-fg',
            self::Collaboration => 'bg-type-project-bg text-type-project-fg',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Job => 'project',
            self::Internship => 'learn',
            self::Mission => 'tasks',
            self::Collaboration => 'group',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $kind) => [$kind->value => $kind->label()])->all();
    }
}
