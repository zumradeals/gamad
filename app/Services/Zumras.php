<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\ZumraFormat;
use App\Enums\ZumraRole;
use App\Enums\ZumraSphere;
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

        // Toutes les erreurs remontent ensemble, chacune sous son champ.
        [$name, $domainId, $cityId, $format, $mission, $sphere] = $this->all(
            fn () => $this->name($data['name'] ?? null),
            fn () => $this->activeId(Domain::class, $data['domain_id'] ?? null, 'domain_id', 'Choisissez un domaine dans la liste.', required: true),
            fn () => $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une ville dans la liste.'),
            fn () => $this->format($data['format'] ?? null),
            fn () => $this->mission($data['mission'] ?? null),
            fn () => $this->sphere($data['sphere'] ?? null),
        );

        $this->throttle($founder);

        try {
            return DB::transaction(function () use ($founder, $name, $domainId, $cityId, $format, $mission, $sphere) {
                if (Zumra::query()->where('name_key', Normalizer::key($name))->exists()) {
                    throw $this->nameTaken();
                }

                $zumra = Zumra::query()->create(['name' => $name, 'domain_id' => $domainId, 'city_id' => $cityId, 'format' => $format, 'mission' => $mission, 'sphere' => $sphere]);

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

        [$cityId, $format, $mission] = $this->all(
            fn () => $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une ville dans la liste.', keep: $zumra->city_id),
            fn () => $this->format($data['format'] ?? null),
            fn () => $this->mission($data['mission'] ?? null),
        );

        $zumra->update(['city_id' => $cityId, 'format' => $format, 'mission' => $mission]);
    }

    /**
     * Exécute chaque contrôle, et ne lève qu'à la fin une seule erreur qui les réunit.
     *
     * @return array<int, mixed>
     *
     * @throws ValidationException
     */
    private function all(callable ...$checks): array
    {
        $values = [];
        $errors = [];

        foreach ($checks as $check) {
            try {
                $values[] = $check();
            } catch (ValidationException $e) {
                $errors = [...$errors, ...$e->errors()];
                $values[] = null;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /** La nature d'une nouvelle ZUMRA est obligatoire (Z1) ; les ZUMRA d'avant Z1 n'en ont pas. */
    private function sphere(?string $sphere): string
    {
        return ZumraSphere::tryFrom((string) $sphere)?->value ?? throw ValidationException::withMessages(['sphere' => 'Choisissez la nature de la ZUMRA.']);
    }

    private function name(?string $name): string
    {
        $name = Normalizer::display((string) $name);

        if (mb_strlen($name) < Zumra::NAME_MIN || mb_strlen($name) > Zumra::NAME_MAX) {
            throw ValidationException::withMessages(['name' => 'Le nom doit avoir entre '.Zumra::NAME_MIN.' et '.Zumra::NAME_MAX.' caractères.']);
        }

        return $name;
    }

    /**
     * Le Président reconnaît officiellement une ZUMRA (badge « Reconnue par GAMAD »). Réservé au super administrateur ; l'acte est
     * inscrit au journal d'administration dans la même transaction, et le fondateur et les responsables en sont informés.
     *
     * @throws ValidationException
     */
    public function recognize(User $actor, Zumra $zumra): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        DB::transaction(function () use ($actor, $zumra) {
            $locked = Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isRecognized()) {
                throw ValidationException::withMessages(['recognition' => 'Cette ZUMRA est déjà reconnue.']);
            }

            $locked->forceFill(['recognized_at' => now(), 'recognized_by' => $actor->getKey()])->save();

            AdminJournal::record($actor, 'zumra.recognized', $locked);

            $this->notifyManagers($locked, NotificationKind::ZumraRecognized, $actor);
            $zumra->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Retire la reconnaissance, avec un motif obligatoire qui est tracé au journal.
     *
     * @throws ValidationException
     */
    public function withdrawRecognition(User $actor, Zumra $zumra, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('super-admin');

        $reason = Normalizer::multiline((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['recognition' => 'Indiquez le motif du retrait.']);
        }

        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['recognition' => 'Le motif ne peut pas dépasser 500 caractères.']);
        }

        DB::transaction(function () use ($actor, $zumra, $reason) {
            $locked = Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isRecognized()) {
                throw ValidationException::withMessages(['recognition' => 'Cette ZUMRA n’est pas reconnue.']);
            }

            $locked->forceFill(['recognized_at' => null, 'recognized_by' => null])->save();

            AdminJournal::record($actor, 'zumra.recognition_withdrawn', $locked, $reason);

            $this->notifyManagers($locked, NotificationKind::ZumraRecognitionWithdrawn, $actor);
            $zumra->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function notifyManagers(Zumra $zumra, NotificationKind $kind, User $actor): void
    {
        ZumraMembership::query()->where('zumra_id', $zumra->getKey())->whereIn('role', ['founder', 'manager'])->with('user')->get()
            ->each(fn (ZumraMembership $manager) => app(Notifier::class)->push($manager->user, $kind, $actor, zumra: $zumra));
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
