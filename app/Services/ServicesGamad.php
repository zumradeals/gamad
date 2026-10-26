<?php

namespace App\Services;

use App\Models\User;
use App\Support\PubliciteModule;
use Illuminate\Support\Facades\Route;

/**
 * Services GAMAD (D-209) : l'unique porte d'entrée, depuis Mon espace, vers les départements numériques de GAMAD. Un service n'y figure
 * que s'il est disponible (sa page existe), activé (son état d'ouverture l'autorise) et accessible à la personne : un service non ouvert
 * n'est jamais montré. Ajouter un service, c'est ajouter une entrée ici ; aucun nouvel onglet principal n'est créé.
 */
class ServicesGamad
{
    /**
     * Tous les services connus : clé => [libellé, description, route d'entrée, icône, accès].
     *
     * @return array<string, array{label: string, description: string, route: string, icon: string, open: \Closure}>
     */
    public static function catalogue(): array
    {
        return [
            'publicite' => [
                'label' => 'GAMAD Publicité',
                'description' => 'Faites connaître votre activité auprès des personnes qui la recherchent, avec des annonces claires, relues par la modération.',
                'route' => 'publicite.campagnes',
                'icon' => 'megaphone',
                'open' => fn (?User $user) => PubliciteModule::accessibleTo($user),
            ],
        ];
    }

    /** @return array<string, array{label: string, description: string, route: string, icon: string, url: string}> */
    public static function availableFor(?User $user): array
    {
        $services = [];
        foreach (self::catalogue() as $key => $service) {
            if (! Route::has($service['route']) || ! ($service['open'])($user)) {
                continue;
            }
            $services[$key] = [...array_diff_key($service, ['open' => 1]), 'url' => route($service['route'])];
        }

        return $services;
    }
}
