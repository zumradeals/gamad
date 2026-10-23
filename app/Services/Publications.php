<?php

namespace App\Services;

use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Models\City;
use App\Models\Domain;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use App\Support\SkillList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Publier, et faire passer un besoin à « résolu » ou « fermé sans suite ». Toutes les règles sont tenues ici, côté serveur,
 * quel que soit l'écran qui appelle : autorisation (PublicationPolicy), longueur du texte, listes de référence, limites de
 * débit contre le spam (D-086).
 */
class Publications
{
    /**
     * @param  array{body: ?string, domain_id?: ?string, city_id?: ?string, visibility?: ?string, skills?: array<int, string>}  $data
     *
     * @throws ValidationException
     */
    public function publish(User $author, PublicationType $type, array $data, ?Zumra $zumra = null): Publication
    {
        Gate::forUser($author)->authorize('create', Publication::class);

        if ($zumra) {
            // Publier au nom d'une ZUMRA : son fondateur et ses responsables seulement (D-116).
            Gate::forUser($author)->authorize('manage', $zumra);
        }

        if (! in_array($type, $zumra ? PublicationType::forZumra() : PublicationType::forPerson(), true)) {
            throw ValidationException::withMessages(['body' => $zumra
                ? 'Ce type de publication n’existe pas pour une ZUMRA.'
                : 'Ce type de publication se fait au nom d’une ZUMRA.']);
        }

        $body = Normalizer::multiline((string) ($data['body'] ?? ''));

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Écrivez votre publication.']);
        }

        if (mb_strlen($body) > Publication::BODY_MAX) {
            throw ValidationException::withMessages(['body' => 'Une publication ne peut pas dépasser '.Publication::BODY_MAX.' caractères.']);
        }

        $visibility = $data['visibility'] ?? Publication::PUBLIC;

        // Le niveau « ZUMRA seulement » n'existe que pour une publication de ZUMRA (D-112).
        if (! in_array($visibility, $zumra ? [Publication::PUBLIC, Publication::MEMBERS, Publication::ZUMRA] : [Publication::PUBLIC, Publication::MEMBERS], true)) {
            throw ValidationException::withMessages(['visibility' => 'Choisissez qui peut la voir.']);
        }

        $domainId = $this->activeId(Domain::class, $data['domain_id'] ?? null, 'domain_id', 'Choisissez un domaine dans la liste.');
        $cityId = $this->activeId(City::class, $data['city_id'] ?? null, 'city_id', 'Choisissez une ville dans la liste.');
        $skills = SkillList::clean($data['skills'] ?? [], 'skills', Publication::MAX_SKILLS, 'Trois capacités au plus.');

        $this->throttle("publish:{$author->getKey()}", [
            [config('gamad.limits.publications_per_hour'), 3600, 'Vous publiez trop vite. Réessayez dans un moment.'],
            [config('gamad.limits.publications_per_day'), 86400, 'Vous avez atteint la limite de publications pour aujourd’hui.'],
        ], 'body');

        return DB::transaction(function () use ($author, $type, $body, $visibility, $domainId, $cityId, $skills, $zumra) {
            $publication = new Publication([
                'type' => $type,
                'body' => $body,
                'domain_id' => $domainId,
                'city_id' => $cityId,
                'visibility' => $visibility,
                'need_state' => $type->isNeed() ? NeedState::Open : null,
            ]);
            $publication->author()->associate($author);
            $publication->zumra()->associate($zumra);
            $publication->save();

            $publication->skills()->attach(collect($skills)->mapWithKeys(
                fn (string $name, int $position) => [Skill::findOrCreateByName($name)->id => ['position' => $position]]
            )->all());

            return $publication;
        });
    }

    /** « Marquer comme résolu » : l'auteur seul (D-083). */
    public function resolve(User $actor, Publication $publication): void
    {
        $this->changeNeedState($actor, $publication, NeedState::Resolved);
    }

    /** « Fermer sans suite » : l'auteur seul (D-083). */
    public function close(User $actor, Publication $publication): void
    {
        $this->changeNeedState($actor, $publication, NeedState::Closed);
    }

    private function changeNeedState(User $actor, Publication $publication, NeedState $state): void
    {
        DB::transaction(function () use ($actor, $publication, $state) {
            $locked = Publication::query()->whereKey($publication->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('changeNeedState', $locked);

            $locked->update(['need_state' => $state, 'resolved_at' => $state === NeedState::Resolved ? now() : null]);
            $publication->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function activeId(string $model, mixed $id, string $field, string $message): ?string
    {
        if (blank($id)) {
            return null;
        }

        if (! is_string($id) || ! Str::isUuid($id) || ! $model::query()->whereKey($id)->where('active', true)->exists()) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $id;
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: string}>  $limits  [maximum, fenêtre en secondes, message]
     */
    private function throttle(string $key, array $limits, string $field): void
    {
        foreach ($limits as $index => [$max, $window, $message]) {
            if (RateLimiter::tooManyAttempts("$key:$index", $max)) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("$key:$index", $window);
        }
    }
}
