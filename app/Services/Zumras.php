<?php

namespace App\Services;

use App\Enums\ZumraFormat;
use App\Enums\ZumraRole;
use App\Models\City;
use App\Models\Domain;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Créer et modifier une ZUMRA. Toutes les règles sont tenues ici, côté serveur : autorisation (ZumraPolicy), nom unique,
 * un seul domaine tiré de la liste de référence, mission de 500 caractères au plus, limite de création par personne (D-117).
 */
class Zumras
{
    /**
     * @param  array{name?: ?string, domain_id?: ?string, city_id?: ?string, format?: ?string, mission?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function create(User $founder, array $data): Zumra
    {
        Gate::forUser($founder)->authorize('create', Zumra::class);

        $name = Normalizer::display((string) ($data['name'] ?? ''));

        if (mb_strlen($name) < Zumra::NAME_MIN || mb_strlen($name) > Zumra::NAME_MAX) {
            throw ValidationException::withMessages(['name' => 'Le nom doit avoir entre '.Zumra::NAME_MIN.' et '.Zumra::NAME_MAX.' caractères.']);
        }

        $domainId = $this->activeId(Domain::class, $data['domain_id'] ?? null, 'domain_id', 'Choisissez un domaine dans la liste.', required: true);
        $cityId = $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une ville dans la liste.');
        $format = $this->format($data['format'] ?? null);
        $mission = $this->mission($data['mission'] ?? null);

        $this->throttle($founder);

        try {
            return DB::transaction(function () use ($founder, $name, $domainId, $cityId, $format, $mission) {
                if (Zumra::query()->where('name_key', Normalizer::key($name))->exists()) {
                    throw $this->nameTaken();
                }

                $zumra = Zumra::query()->create(['name' => $name, 'domain_id' => $domainId, 'city_id' => $cityId, 'format' => $format, 'mission' => $mission]);

                $membership = new ZumraMembership(['role' => ZumraRole::Founder]);
                $membership->zumra()->associate($zumra);
                $membership->user()->associate($founder);
                $membership->save();

                return $zumra;
            });
        } catch (UniqueConstraintViolationException) {
            // Deux créations simultanées du même nom : la seconde est refusée comme un doublon.
            throw $this->nameTaken();
        }
    }

    /**
     * Modifie la mission, la ville et le format. Le nom et le domaine ne changent pas (D-114).
     *
     * @param  array{city_id?: ?string, format?: ?string, mission?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function update(User $actor, Zumra $zumra, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $zumra);

        $cityId = $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une ville dans la liste.', keep: $zumra->city_id);
        $format = $this->format($data['format'] ?? null);
        $mission = $this->mission($data['mission'] ?? null);

        $zumra->update(['city_id' => $cityId, 'format' => $format, 'mission' => $mission]);
    }

    private function nameTaken(): ValidationException
    {
        return ValidationException::withMessages(['name' => 'Ce nom est déjà pris par une autre ZUMRA.']);
    }

    private function format(?string $format): ZumraFormat
    {
        return ZumraFormat::tryFrom((string) $format)
            ?? throw ValidationException::withMessages(['format' => 'Choisissez où la ZUMRA se retrouve.']);
    }

    private function mission(?string $mission): string
    {
        $mission = Normalizer::multiline((string) $mission);

        if ($mission === '') {
            throw ValidationException::withMessages(['mission' => 'Écrivez la mission de la ZUMRA.']);
        }

        if (mb_strlen($mission) > Zumra::MISSION_MAX) {
            throw ValidationException::withMessages(['mission' => 'La mission ne peut pas dépasser '.Zumra::MISSION_MAX.' caractères.']);
        }

        return $mission;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function activeId(string $model, mixed $id, string $field, string $message, bool $required = false, ?string $keep = null): ?string
    {
        if (blank($id)) {
            return $required ? throw ValidationException::withMessages([$field => $message]) : null;
        }

        // Une ville désactivée depuis reste acceptée pour la ZUMRA qui l'avait déjà choisie.
        if (is_string($id) && $id === $keep) {
            return $id;
        }

        if (! is_string($id) || ! Str::isUuid($id) || ! $model::query()->whereKey($id)->where('active', true)->exists()) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $id;
    }

    /** Limite de débit contre le spam (D-117) : un nombre de créations par jour et par personne. */
    private function throttle(User $user): void
    {
        $key = "zumra-create:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.zumras_per_day'))) {
            throw ValidationException::withMessages(['name' => 'Vous avez atteint la limite de créations de ZUMRA pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
