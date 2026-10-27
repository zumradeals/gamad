<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Pays africains sélectionnables (D-217) : les 54 États membres de l'ONU situés en Afrique, avec les lieux du fichier
 * database/data/geo/villes-afrique.json (GeoNames, CC BY 4.0). L'import est idempotent, ajoute sans jamais modifier ni supprimer
 * (les pays et lieux existants, dont la Côte d'Ivoire relue, ne changent pas), et n'est lancé que par le super administrateur
 * depuis Paramètres › Territoires, avec une simulation d'abord. Chaque exécution est inscrite au journal d'administration.
 */
class AfricanGeography
{
    public const SOURCE = 'geonames:cities.json-1.1.65';

    /** Codes ISO 3166-1 alpha-2 des 54 États membres de l'ONU situés en Afrique. */
    public const ISO = [
        'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD', 'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET',
        'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW',
        'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW',
    ];

    /** @var array<string, array<int, array{0: string, 1: string}>>|null */
    private ?array $places = null;

    /** Nom d'un pays en français, d'après les données de langue du système. */
    public function countryName(string $iso): string
    {
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$iso, 'fr') : '';

        return $name !== '' && $name !== $iso ? $name : $iso;
    }

    /**
     * Ce qu'un import ferait, sans rien écrire.
     *
     * @return array{countries_new: int, countries_linked: int, places_new: int, places_existing: int, places_duplicate: int}
     */
    public function plan(): array
    {
        return $this->run(false);
    }

    /**
     * @return array{countries_new: int, countries_linked: int, places_new: int, places_existing: int, places_duplicate: int}
     */
    public function import(User $actor): array
    {
        Gate::forUser($actor)->authorize('super-admin');

        $result = DB::transaction(fn () => $this->run(true));

        AdminJournal::record($actor, 'country.africa_imported', null, null, $result + ['source' => self::SOURCE], subjectType: 'territories', subjectId: 'afrique');

        return $result;
    }

    /** @return array{countries_new: int, countries_linked: int, places_new: int, places_existing: int, places_duplicate: int} */
    private function run(bool $write): array
    {
        $result = ['countries_new' => 0, 'countries_linked' => 0, 'places_new' => 0, 'places_existing' => 0, 'places_duplicate' => 0];
        $now = now();

        foreach (self::ISO as $iso) {
            $country = Country::query()->where('iso2', $iso)->first();

            if (! $country) {
                $name = $this->countryName($iso);
                $country = Country::query()->where('normalized', Normalizer::key($name))->first();

                if ($country) {
                    // Un pays déjà présent sous ce nom (ajouté à la main) : on lui donne son code, sans rien changer d'autre.
                    $result['countries_linked']++;
                    if ($write && $country->iso2 === null) {
                        $country->forceFill(['iso2' => $iso])->saveQuietly();
                    }
                } else {
                    $result['countries_new']++;
                    if ($write) {
                        $country = (new Country)->forceFill(['name' => $name, 'iso2' => $iso, 'sort_order' => 100, 'source' => self::SOURCE, 'active' => true]);
                        $country->save();
                    }
                }
            }

            // 1 : lieu déjà en base ; 2 : lieu ajouté pendant cette exécution (un doublon du fichier).
            $known = $country?->exists ? City::query()->where('country_id', $country->getKey())->pluck('normalized')->mapWithKeys(fn ($key) => [$key => 1])->all() : [];
            $rows = [];

            foreach ($this->placesOf($iso) as [$area, $name]) {
                $label = $area !== '' ? $area.', '.$name : $name;
                $key = Normalizer::key($label);

                if (isset($known[$key])) {
                    $result[$known[$key] === 1 ? 'places_existing' : 'places_duplicate']++;

                    continue;
                }

                $known[$key] = 2;
                $result['places_new']++;

                if ($write) {
                    $rows[] = [
                        'id' => (string) Str::uuid7(), 'country_id' => $country->getKey(), 'name' => Normalizer::display($name),
                        'area' => $area === '' ? null : Normalizer::display($area), 'normalized' => $key, 'active' => true,
                        'source' => self::SOURCE, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                City::query()->insert($chunk);
            }
        }

        return $result;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function placesOf(string $iso): array
    {
        $this->places ??= json_decode((string) file_get_contents(database_path('data/geo/villes-afrique.json')), true, 512, JSON_THROW_ON_ERROR);

        return $this->places[$iso] ?? [];
    }
}
