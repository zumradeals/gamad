<?php

namespace App\Services;

use App\Enums\ReportCategory;
use App\Models\Publication;
use App\Models\Report;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Signaler une publication (écran 16). La personne qui signale n'est jamais montrée à l'auteur. Règles côté serveur :
 * droit de signaler (PublicationPolicy::report), une seule fois par personne et par publication, raison dans la liste,
 * précision de 280 caractères au plus, limites de débit contre l'abus du signalement lui-même (D-086).
 */
class Reports
{
    /**
     * @throws ValidationException
     */
    public function submit(User $reporter, Publication $publication, ?string $category, ?string $details = null): Report
    {
        Gate::forUser($reporter)->authorize('report', $publication);

        $reason = ReportCategory::tryFrom((string) $category);

        if (! $reason) {
            throw ValidationException::withMessages(['category' => 'Choisissez la raison du signalement.']);
        }

        $details = Normalizer::multiline((string) $details);

        if (mb_strlen($details) > Report::DETAILS_MAX) {
            throw ValidationException::withMessages(['details' => 'La précision ne peut pas dépasser '.Report::DETAILS_MAX.' caractères.']);
        }

        if (Report::query()->where('publication_id', $publication->getKey())->where('reporter_id', $reporter->getKey())->exists()) {
            throw ValidationException::withMessages(['category' => 'Vous avez déjà signalé cette publication. Un modérateur l’examine.']);
        }

        $this->throttle($reporter);

        $report = new Report(['category' => $reason, 'details' => $details === '' ? null : $details]);
        $report->publication()->associate($publication);
        $report->reporter()->associate($reporter);

        try {
            $report->save();
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés : le second est refusé comme un doublon.
            throw ValidationException::withMessages(['category' => 'Vous avez déjà signalé cette publication. Un modérateur l’examine.']);
        }

        return $report;
    }

    private function throttle(User $user): void
    {
        $limits = [
            [config('gamad.limits.reports_per_hour'), 3600, 'Vous signalez trop vite. Réessayez dans un moment.'],
            [config('gamad.limits.reports_per_day'), 86400, 'Vous avez atteint la limite de signalements pour aujourd’hui.'],
        ];

        foreach ($limits as $index => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts("report:{$user->getKey()}:$index", $max)) {
                throw ValidationException::withMessages(['category' => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("report:{$user->getKey()}:$index", $window);
        }
    }
}
