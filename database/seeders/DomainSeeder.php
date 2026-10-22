<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Seeder;

/**
 * Les 17 domaines d'action (D-069, qui confirme D-014) : les départements de l'article 3 du Règlement intérieur de GAMAD,
 * dans l'ordre de docs/PRODUIT.md §7, plus « Artisanat » (qui ne figure pas dans l'article 3).
 *
 * Données de référence réelles, nécessaires en production. Le seeder ne remplit la liste que si elle est vide : il ne
 * recrée jamais un domaine que le super administrateur a renommé ou désactivé.
 */
class DomainSeeder extends Seeder
{
    public const NAMES = [
        'Santé et Action sociale',
        'Religion',
        'Formation et Éducation',
        'Technologie et Sciences naturelles',
        'Économie et Finances',
        'Construction et Industries',
        'Promotion immobilière',
        'Génie civil et BTP',
        'Travail, Ressources humaines et Développement',
        'Sécurité',
        'Sport et Culture',
        'Communication et Communauté',
        'Transport et Automobile',
        'Justice et Droits de l’homme',
        'Affaires commerciales',
        'Agriculture, Pêche et Élevage',
        'Artisanat',
    ];

    public function run(): void
    {
        if (Domain::query()->exists()) {
            return;
        }

        foreach (self::NAMES as $index => $name) {
            Domain::create(['name' => $name, 'sort_order' => ($index + 1) * 10]);
        }
    }
}
