<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Administration des listes de référence par le super administrateur : domaines, pays, villes (D-068, D-069).
 * On ajoute, on renomme, on désactive et on réactive ; on ne supprime jamais (une personne peut y être rattachée).
 * Chaque acte est inscrit au journal d'administration, dans la même transaction que le changement.
 *
 * L'appelant vérifie le droit (Gate « super-admin ») ; ce service refuse en plus tout autre acteur.
 */
class ReferenceAdmin
{
    public const NAME_MIN = 2;

    public const NAME_MAX = 100;

    /**
     * @param  array{name: string, country_id?: ?string, area?: ?string}  $data
     */
    public function create(User $actor, string $type, array $data): Model
    {
        $this->assertSuperAdmin($actor);

        $name = $this->cleanName($data['name']);

        return DB::transaction(function () use ($actor, $type, $name, $data) {
            $item = match ($type) {
                'domain' => $this->newDomain($name),
                'country' => $this->newCountry($name),
                'city' => $this->newCity($name, (string) ($data['country_id'] ?? ''), $data['area'] ?? null),
                default => throw new \InvalidArgumentException("Type inconnu : $type"),
            };

            AdminJournal::record($actor, "$type.created", $item, payload: ['nom' => $this->label($item)]);

            return $item;
        });
    }

    /**
     * @return bool vrai si le nom a changé
     */
    public function rename(User $actor, Model $item, string $name): bool
    {
        $this->assertSuperAdmin($actor);

        $name = $this->cleanName($name);

        if ($name === $item->name) {
            return false;
        }

        DB::transaction(function () use ($actor, $item, $name) {
            $old = $this->label($item);
            $this->assertUnique($item, $name, $item instanceof City ? $item->area : null, $item->getKey());

            $item->update(['name' => $name]);

            AdminJournal::record($actor, $this->type($item).'.renamed', $item, payload: ['ancien' => $old, 'nouveau' => $this->label($item)]);
        });

        return true;
    }

    /**
     * @return bool vrai si l'état a changé
     */
    public function setActive(User $actor, Model $item, bool $active): bool
    {
        $this->assertSuperAdmin($actor);

        if ((bool) $item->active === $active) {
            return false;
        }

        DB::transaction(function () use ($actor, $item, $active) {
            $item->update(['active' => $active]);

            AdminJournal::record($actor, $this->type($item).($active ? '.reactivated' : '.deactivated'), $item, payload: ['nom' => $this->label($item)]);
        });

        return true;
    }

    private function newDomain(string $name): Domain
    {
        $this->assertUnique(new Domain, $name);

        return Domain::create(['name' => $name, 'sort_order' => ((int) Domain::query()->max('sort_order')) + 10]);
    }

    private function newCountry(string $name): Country
    {
        $this->assertUnique(new Country, $name);

        return Country::create(['name' => $name, 'sort_order' => ((int) Country::query()->max('sort_order')) + 10]);
    }

    private function newCity(string $name, string $countryId, ?string $area): City
    {
        $country = Country::query()->find($countryId);

        if (! $country) {
            throw ValidationException::withMessages(['country_id' => 'Choisissez le pays de cette ville.']);
        }

        $area = filled($area) ? $this->cleanName($area, 'area') : null;
        $city = new City(['country_id' => $country->id, 'name' => $name, 'area' => $area]);
        $this->assertUnique($city, $name, $area);

        return City::create(['country_id' => $country->id, 'name' => $name, 'area' => $area]);
    }

    /** Un nom est unique sans tenir compte de la casse, des accents ni des espaces superflus (ville : dans son pays). */
    private function assertUnique(Model $item, string $name, ?string $area = null, mixed $ignoreId = null): void
    {
        $probe = $item->replicate();
        $probe->name = $name;

        if ($probe instanceof City) {
            $probe->area = $area;
            $key = Normalizer::key($probe->label());
            $query = City::query()->where('country_id', $item->country_id)->where('normalized', $key);
        } else {
            $key = Normalizer::key($name);
            $query = $item::query()->where('normalized', $key);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'Ce nom existe déjà dans la liste.']);
        }
    }

    private function cleanName(string $name, string $field = 'name'): string
    {
        $name = Normalizer::display($name);
        $length = mb_strlen($name);

        if ($length < self::NAME_MIN || $length > self::NAME_MAX) {
            throw ValidationException::withMessages([$field => 'Le nom comporte de '.self::NAME_MIN.' à '.self::NAME_MAX.' caractères.']);
        }

        return $name;
    }

    private function label(Model $item): string
    {
        return $item instanceof City ? $item->label() : $item->name;
    }

    private function type(Model $item): string
    {
        return match (true) {
            $item instanceof Domain => 'domain',
            $item instanceof Country => 'country',
            $item instanceof City => 'city',
            default => throw new \InvalidArgumentException('Élément inconnu.'),
        };
    }

    private function assertSuperAdmin(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Réservé au super administrateur.');
        }
    }
}
