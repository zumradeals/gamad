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
    'initial' => [
        'phone' => [
            ['key' => 'fil', 'label' => 'Accueil', 'route' => 'fil', 'active' => ['fil'], 'icon' => 'feed', 'enabled' => true],
            ['key' => 'personnes', 'label' => 'Personnes', 'route' => 'personnes.index', 'active' => 'personnes.*', 'icon' => 'group', 'enabled' => true],
            ['key' => 'agir', 'label' => 'Agir', 'route' => 'agir', 'icon' => 'plus', 'enabled' => true, 'prominent' => true],
            ['key' => 'entreprises', 'label' => 'Entreprises', 'route' => 'entreprises.index', 'active' => 'entreprises.*', 'icon' => 'project', 'enabled' => true],
            ['key' => 'espace', 'label' => 'Mon espace', 'route' => 'espace', 'active' => ['espace', 'espace.*', 'conversations.*'], 'icon' => 'user', 'enabled' => true],
        ],
        'desktop' => [
            ['key' => 'fil', 'label' => 'Accueil', 'route' => 'fil', 'active' => ['fil'], 'icon' => 'home', 'enabled' => true],
            ['key' => 'personnes', 'label' => 'Personnes', 'route' => 'personnes.index', 'active' => 'personnes.*', 'icon' => 'group', 'enabled' => true],
            ['key' => 'entreprises', 'label' => 'Entreprises', 'route' => 'entreprises.index', 'active' => 'entreprises.*', 'icon' => 'project', 'enabled' => true],
            ['key' => 'espace', 'label' => 'Mon espace', 'route' => 'espace', 'active' => ['espace', 'espace.*', 'conversations.*'], 'icon' => 'user', 'enabled' => true],
        ],
    ],

    // Barre du bas, téléphone. « Agir » est le rond jaune surélevé au centre.
    'phone' => [
        ['key' => 'fil', 'label' => 'Accueil', 'route' => 'fil', 'icon' => 'feed', 'enabled' => true],
        // « Découvrir » ouvre une fenêtre à trois choix (`sheet`, D-160) ; sans JavaScript, il mène à la page /decouvrir.
        ['key' => 'decouvrir', 'label' => 'Découvrir', 'route' => 'decouvrir', 'active' => ['decouvrir', 'personnes.*', 'besoins.*', 'projets.*'], 'icon' => 'compass', 'enabled' => true, 'sheet' => true],
        ['key' => 'agir', 'label' => 'Agir', 'route' => 'agir', 'icon' => 'plus', 'enabled' => true, 'prominent' => true],
        ['key' => 'zumra', 'label' => 'ZUMRA', 'route' => 'zumras.index', 'active' => 'zumras.*', 'icon' => 'group', 'enabled' => true],
        ['key' => 'espace', 'label' => 'Espace', 'route' => 'espace', 'icon' => 'user', 'enabled' => true],
    ],

    // Barre du haut, ordinateur. L'avatar mène toujours à Espace.
    'desktop' => [
        ['key' => 'fil', 'icon' => 'home', 'label' => 'Accueil', 'route' => 'fil', 'enabled' => true],
        ['key' => 'personnes', 'icon' => 'group', 'label' => 'Personnes', 'route' => 'personnes.index', 'active' => 'personnes.*', 'enabled' => true],
        ['key' => 'besoins', 'icon' => 'heart', 'label' => 'Besoins', 'route' => 'besoins.index', 'active' => 'besoins.*', 'enabled' => true],
        ['key' => 'projets', 'icon' => 'project', 'label' => 'Projets', 'route' => 'projets.index', 'active' => 'projets.*', 'enabled' => true],
        ['key' => 'zumra', 'icon' => 'community', 'label' => 'ZUMRA', 'route' => 'zumras.index', 'active' => 'zumras.*', 'enabled' => true],
    ],

    // Les trois choix de « Découvrir » (fenêtre du téléphone et page /decouvrir) : chacun mène à son Carrefour (D-160).
    'discover' => [
        ['key' => 'personnes', 'label' => 'Personnes', 'description' => 'Trouver des capacités et des personnes qui peuvent aider.', 'route' => 'personnes.index', 'icon' => 'group'],
        ['key' => 'besoins', 'label' => 'Besoins', 'description' => 'Voir ce qui manque pour faire avancer une action.', 'route' => 'besoins.index', 'icon' => 'heart'],
        ['key' => 'projets', 'label' => 'Projets', 'description' => 'Découvrir les initiatives et leur prochaine étape.', 'route' => 'projets.index', 'icon' => 'project'],
        ['key' => 'entreprises', 'label' => 'Entreprises', 'description' => 'Découvrir les entreprises administrativement vérifiées.', 'route' => 'entreprises.index', 'icon' => 'project'],
    ],
];
