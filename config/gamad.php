<?php

/*
|--------------------------------------------------------------------------
| Valeurs par défaut des réglages métier GAMAD
|--------------------------------------------------------------------------
|
| Ces valeurs s'appliquent tant que le super administrateur n'a rien changé dans la page
| « Paramètres » (D-049). Les réglages enregistrés en base les remplacent au démarrage
| (voir App\Services\Settings::applyToConfig).
|
*/

return [
    'limits' => [
        // Inscriptions par heure et par adresse IP (D-045).
        'register_per_hour' => 20,
        // Tentatives de connexion par minute, par adresse e-mail et adresse IP.
        'login_per_minute' => 5,

        // Protection contre le spam (D-086), par personne. Une publication ou un geste de trop est refusé avec un message clair.
        'publications_per_hour' => 10,
        'publications_per_day' => 30,
        'gestures_per_hour' => 30,
        'gestures_per_day' => 100,
        'zumras_per_day' => 3,
        'reports_per_hour' => 10,
        'reports_per_day' => 30,
        'messages_per_minute' => 20,
        'messages_per_day' => 500,
    ],
];
