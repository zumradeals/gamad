<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * La charte de conduite et les mentions légales (D-146). Les textes sont des projets tant que le Président ne les a pas adoptés :
 * ils ne sont alors lisibles que par lui, et rien n'est demandé à l'inscription. L'adoption et son retrait sont des réglages
 * enregistrés au journal d'administration (par Settings).
 */
class LegalTexts
{
    /** Champs des mentions légales que le Président renseigne avant de pouvoir les adopter. */
    public const LEGAL_FIELDS = ['legal_publisher', 'legal_director', 'legal_contact', 'legal_host'];

    public function __construct(private readonly Settings $settings) {}

    public function charterAdoptedAt(): ?Carbon
    {
        return $this->date('charter_adopted_at');
    }

    public function legalAdoptedAt(): ?Carbon
    {
        return $this->date('legal_adopted_at');
    }

    public function charterAdopted(): bool
    {
        return $this->charterAdoptedAt() !== null;
    }

    /** Les mentions légales ne se lisent publiquement qu'adoptées, avec tous leurs champs renseignés. */
    public function legalAdopted(): bool
    {
        return $this->legalAdoptedAt() !== null && $this->legalComplete();
    }

    public function legalComplete(): bool
    {
        return collect(self::LEGAL_FIELDS)->every(fn (string $key) => filled($this->settings->get($key)));
    }

    /**
     * @return array<string, ?string>
     */
    public function legalValues(): array
    {
        return collect(self::LEGAL_FIELDS)->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])->all();
    }

    /**
     * @throws ValidationException
     */
    public function adoptCharter(User $actor): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        $this->settings->update(['charter_adopted_at' => now()->toIso8601String()], $actor);
    }

    public function withdrawCharter(User $actor): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        $this->settings->update(['charter_adopted_at' => null], $actor);
    }

    /**
     * @throws ValidationException
     */
    public function adoptLegal(User $actor): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        if (! $this->legalComplete()) {
            throw ValidationException::withMessages(['legal' => 'Renseignez d’abord l’éditeur, le responsable de la publication, le contact et l’hébergeur.']);
        }

        $this->settings->update(['legal_adopted_at' => now()->toIso8601String()], $actor);
    }

    public function withdrawLegal(User $actor): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        $this->settings->update(['legal_adopted_at' => null], $actor);
    }

    /**
     * Enregistre les champs des mentions légales (journalisés par Settings).
     *
     * @param  array<string, ?string>  $values
     */
    public function saveLegalValues(User $actor, array $values): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        $this->settings->update(collect($values)->only(self::LEGAL_FIELDS)->all(), $actor);
    }

    private function date(string $key): ?Carbon
    {
        $value = $this->settings->get($key);

        return filled($value) ? Carbon::parse($value) : null;
    }
}
