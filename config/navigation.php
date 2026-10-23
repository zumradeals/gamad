<?php

/*
|--------------------------------------------------------------------------
| Navigation de l'application
|--------------------------------------------------------------------------
|
| Une entrée n'apparaît que si `enabled` vaut true : un module non construit est
| masqué, jamais simulé (CLAUDE.md, règle 3). Chaque lot active ses entrées ici.
| `route` est le nom d'une route Laravel ; il est obligatoire pour une entrée activée. `active` (facultatif) est le motif
| de routes pour lequel l'entrée est marquée active ; à défaut, la route de l'entrée et ses sous-routes.
|
*/

return [
    // Barre du bas, téléphone. « Agir » est le rond jaune surélevé au centre.
    'phone' => [
        ['key' => 'fil', 'label' => 'Fil', 'route' => 'fil', 'icon' => 'feed', 'enabled' => true],
        ['key' => 'decouvrir', 'label' => 'Découvrir', 'route' => 'personnes.index', 'active' => 'personnes.*', 'icon' => 'compass', 'enabled' => true],
        ['key' => 'agir', 'label' => 'Agir', 'route' => 'agir', 'icon' => 'plus', 'enabled' => true, 'prominent' => true],
        ['key' => 'zumra', 'label' => 'ZUMRA', 'route' => 'zumras.index', 'active' => 'zumras.*', 'icon' => 'group', 'enabled' => true],
        ['key' => 'espace', 'label' => 'Espace', 'route' => 'espace', 'icon' => 'user', 'enabled' => true],
    ],

    // Barre du haut, ordinateur. L'avatar mène toujours à Espace.
    'desktop' => [
        ['key' => 'fil', 'label' => 'Fil', 'route' => 'fil', 'enabled' => true],
        ['key' => 'personnes', 'label' => 'Personnes', 'route' => 'personnes.index', 'active' => 'personnes.*', 'enabled' => true],
        ['key' => 'besoins', 'label' => 'Besoins', 'route' => null, 'enabled' => false],
        ['key' => 'projets', 'label' => 'Projets', 'route' => null, 'enabled' => false],
        ['key' => 'zumra', 'label' => 'ZUMRA', 'route' => 'zumras.index', 'active' => 'zumras.*', 'enabled' => true],
    ],
];
