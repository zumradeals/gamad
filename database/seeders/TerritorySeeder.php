<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Territoires de départ (D-068) : la Côte d'Ivoire, avec les chefs-lieux de ses régions et de ses districts autonomes,
 * et les communes d'Abidjan. Le super administrateur ajoute ensuite des pays et des villes dans Paramètres.
 *
 * SOURCES
 *  - Décret n° 2011-263 du 28 septembre 2011 portant organisation du territoire national en Districts et en Régions
 *    (les 31 régions et leurs chefs-lieux) ; les districts autonomes d'Abidjan et de Yamoussoukro, issus de la réforme
 *    de 2014 ; les 13 communes du district autonome d'Abidjan.
 *  - Recoupement du 22 octobre 2026 avec des sources publiques secondaires : la liste des 31 régions de loidici.biz,
 *    les pages de région de economie-ivoirienne.ci (Folon, Bounkani, Béré) et la liste des communes d'Abidjan.
 *    Le texte intégral du décret n'a pas pu être ouvert depuis l'environnement de développement : cette liste est à
 *    faire contrôler par le Président avant l'ouverture, et le super administrateur peut corriger ou ajouter tout
 *    nom dans Paramètres › Territoires.
 *  - Aucun nom n'est inventé : tout nom absent de ces sources est absent d'ici.
 *
 * Données de référence réelles, nécessaires en production. Le seeder ne remplit les listes que si elles sont vides.
 */
class TerritorySeeder extends Seeder
{
    public const COUNTRY = 'Côte d’Ivoire';

    /** Chefs-lieux des 31 régions (région : chef-lieu), dans l'ordre alphabétique des régions. */
    public const REGION_CAPITALS = [
        'Agnéby-Tiassa' => 'Agboville',
        'Bafing' => 'Touba',
        'Bagoué' => 'Boundiali',
        'Bélier' => 'Toumodi',
        'Béré' => 'Mankono',
        'Bounkani' => 'Bouna',
        'Cavally' => 'Guiglo',
        'Folon' => 'Minignan',
        'Gbêkê' => 'Bouaké',
        'Gbôklé' => 'Sassandra',
        'Gôh' => 'Gagnoa',
        'Gontougo' => 'Bondoukou',
        'Grands-Ponts' => 'Dabou',
        'Guémon' => 'Duékoué',
        'Hambol' => 'Katiola',
        'Haut-Sassandra' => 'Daloa',
        'Iffou' => 'Daoukro',
        'Indénié-Djuablin' => 'Abengourou',
        'Kabadougou' => 'Odienné',
        'Loh-Djiboua' => 'Divo',
        'Marahoué' => 'Bouaflé',
        'Mé' => 'Adzopé',
        'Moronou' => 'Bongouanou',
        'N’Zi' => 'Dimbokro',
        'Nawa' => 'Soubré',
        'Poro' => 'Korhogo',
        'San-Pédro' => 'San-Pédro',
        'Sud-Comoé' => 'Aboisso',
        'Tchologo' => 'Ferkessédougou',
        'Tonkpi' => 'Man',
        'Worodougou' => 'Séguéla',
    ];

    /** Districts autonomes. */
    public const AUTONOMOUS_DISTRICTS = ['Abidjan', 'Yamoussoukro'];

    /** Communes du district autonome d'Abidjan. */
    public const ABIDJAN_COMMUNES = [
        'Abobo', 'Adjamé', 'Anyama', 'Attécoubé', 'Bingerville', 'Cocody', 'Koumassi',
        'Marcory', 'Plateau', 'Port-Bouët', 'Songon', 'Treichville', 'Yopougon',
    ];

    public function run(): void
    {
        if (Country::query()->exists() || City::query()->exists()) {
            return;
        }

        $country = Country::create(['name' => self::COUNTRY, 'sort_order' => 10]);

        foreach (self::AUTONOMOUS_DISTRICTS as $name) {
            City::create(['country_id' => $country->id, 'name' => $name]);
        }

        foreach (self::ABIDJAN_COMMUNES as $name) {
            City::create(['country_id' => $country->id, 'name' => $name, 'area' => 'Abidjan']);
        }

        foreach (array_values(self::REGION_CAPITALS) as $name) {
            City::create(['country_id' => $country->id, 'name' => $name]);
        }
    }
}
