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
    ],
];
