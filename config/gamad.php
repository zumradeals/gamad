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
        'projects_per_day' => 10,
        'participation_requests_per_day' => 10,
        'supports_per_hour' => 60,
        'zumra_posts_per_minute' => 10,
        'zumra_posts_per_day' => 200,
        'join_requests_per_day' => 10,
        'invitations_per_day' => 30,
        'reports_per_hour' => 10,
        'reports_per_day' => 30,
        'messages_per_minute' => 20,
        'messages_per_day' => 500,
    ],

    'moderation' => [
        // Jours laissés à une personne pour s'expliquer après un avis de la modération, avant qu'une suspension soit possible (D-152).
        'explanation_days' => 7,
    ],

    'backup' => [
        // Fichier d'état écrit par la copie nocturne (deploy/copie-hors-serveur.sh) et lu par Espace → Paramètres → Copie hors serveur.
        'state_file' => env('GAMAD_BACKUP_STATE_FILE', '/var/lib/gamad-reseau/copie-etat'),
    ],
];
