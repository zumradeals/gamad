<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * La présentation publique limitée d'un projet (P1, D-206) : une vitrine volontaire, composée seulement de ce que le porteur a écrit pour
 * elle (un résumé, comment contribuer) et des deux choix qu'il fait (montrer le porteur, montrer les compétences recherchées). Elle ne
 * copie jamais le dossier privé : ni but détaillé, ni équipe, ni étapes, ni documents. Elle exige un accueil accepté. Le porteur la retire quand il veut.
 */
class ProjectShowcase
{
    /**
     * @param  array{summary?: ?string, contribute?: ?string, show_owner?: mixed, show_needs?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function publish(User $owner, Project $project, array $data): void
    {
        Gate::forUser($owner)->authorize('update', $project);

        if (! $project->isHosted()) {
            throw ValidationException::withMessages(['summary' => 'Une ZUMRA doit d’abord accepter d’accueillir le projet avant de le présenter publiquement.']);
        }

        $summary = Normalizer::multiline((string) ($data['summary'] ?? ''));
        $contribute = Normalizer::multiline((string) ($data['contribute'] ?? ''));
        $errors = [];

        if ($summary === '') {
            $errors['summary'] = 'Écrivez le résumé public : c’est ce que tout le monde lira.';
        } elseif (mb_strlen($summary) > Project::SUMMARY_MAX) {
            $errors['summary'] = 'Le résumé ne peut pas dépasser '.Project::SUMMARY_MAX.' caractères.';
        }

        if (mb_strlen($contribute) > Project::CONTRIBUTE_MAX) {
            $errors['contribute'] = 'Ce texte ne peut pas dépasser '.Project::CONTRIBUTE_MAX.' caractères.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $project->forceFill([
            'showcase_summary' => $summary,
            'showcase_contribute' => $contribute === '' ? null : $contribute,
            'showcase_show_owner' => (bool) ($data['show_owner'] ?? false),
            'showcase_show_needs' => (bool) ($data['show_needs'] ?? false),
            'showcase_published_at' => $project->showcase_published_at ?? now(),
        ])->save();
    }

    /** Retire la présentation publique : la page disparaît aussitôt, le texte préparé est conservé pour une republication. */
    public function unpublish(User $owner, Project $project): void
    {
        Gate::forUser($owner)->authorize('update', $project);

        $project->forceFill(['showcase_published_at' => null])->save();
    }
}
