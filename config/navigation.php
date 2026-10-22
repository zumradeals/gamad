<?php

/*
|--------------------------------------------------------------------------
| Navigation de l'application
|--------------------------------------------------------------------------
|
| Une entrée n'apparaît que si `enabled` vaut true : un module non construit est
| masqué, jamais simulé (CLAUDE.md, règle 3). Chaque lot active ses entrées ici.
| `route` est le nom d'une route Laravel ; il est obligatoire pour une entrée activée.
|
*/

return [
    // Barre du bas, téléphone. « Agir » est le rond jaune surélevé au centre.
    'phone' => [
        ['key' => 'fil', 'label' => 'Fil', 'route' => 'fil', 'icon' => 'feed', 'enabled' => true],
        ['key' => 'decouvrir', 'label' => 'Découvrir', 'route' => null, 'icon' => 'compass', 'enabled' => false],
        ['key' => 'agir', 'label' => 'Agir', 'route' => null, 'icon' => 'plus', 'enabled' => false, 'prominent' => true],
        ['key' => 'zumra', 'label' => 'ZUMRA', 'route' => null, 'icon' => 'group', 'enabled' => false],
        ['key' => 'espace', 'label' => 'Espace', 'route' => 'espace', 'icon' => 'user', 'enabled' => true],
    ],

    // Barre du haut, ordinateur. L'avatar mène toujours à Espace.
    'desktop' => [
        ['key' => 'fil', 'label' => 'Fil', 'route' => 'fil', 'enabled' => true],
        ['key' => 'personnes', 'label' => 'Personnes', 'route' => null, 'enabled' => false],
        ['key' => 'besoins', 'label' => 'Besoins', 'route' => null, 'enabled' => false],
        ['key' => 'projets', 'label' => 'Projets', 'route' => null, 'enabled' => false],
        ['key' => 'zumra', 'label' => 'ZUMRA', 'route' => null, 'enabled' => false],
    ],
];
