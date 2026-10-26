<?php

/*
 * Connexion faible (Lot 5c, D-157) : ce que le serveur et les gabarits garantissent pour que les pages restent légères.
 * Mesures de référence (téléphone, 390 px, connexion simulée à 400 kb/s et 400 ms) : voir docs/TESTER.md.
 */

function modeleNginxHttps(): string
{
    $install = file_get_contents(base_path('deploy/install.sh'));
    $debut = strpos($install, 'listen 443 ssl;');

    return substr($install, $debut, strpos($install, "\nE\n", $debut) - $debut);
}

it('compresse les feuilles de style et les scripts, mais pas le JSON des échanges Livewire', function () {
    $nginx = modeleNginxHttps();

    expect($nginx)->toContain('gzip on;')->toContain('gzip_vary on;')->toContain('gzip_types text/css application/javascript image/svg+xml;')
        ->not->toContain('application/json');
});

it('garde un an en cache les fichiers compilés, sans perdre les en-têtes de sécurité', function () {
    $nginx = modeleNginxHttps();

    preg_match('#location /build/ \{(.*?)\n    \}#s', $nginx, $bloc);

    expect($bloc)->not->toBeEmpty()->and($bloc[1])->toContain('expires 1y;')
        // Un « add_header » dans ce bloc ferait oublier à nginx ceux du serveur (HSTS, X-Frame-Options…).
        ->not->toContain('add_header');
    expect($nginx)->toContain('add_header Strict-Transport-Security')->toContain('add_header X-Frame-Options');
});

it('n’appelle aucune ressource externe depuis les gabarits : ni police, ni feuille de style, ni script, ni image distants', function () {
    $fichiers = glob(base_path('resources/views/components/layouts/*.blade.php'));
    expect($fichiers)->not->toBeEmpty();

    foreach ($fichiers as $fichier) {
        expect(file_get_contents($fichier))->not->toMatch('#(?:src|href)\s*=\s*"https?://#i')->not->toMatch('#@import\s+url\(\s*[\'"]?https?://#i');
    }

    expect(file_get_contents(base_path('resources/css/app.css')))->not->toMatch('#url\(\s*[\'"]?https?://#i');
});

it('charge les polices avec font-display: swap, pour que le texte reste lisible pendant le chargement', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect(substr_count($css, '@font-face'))->toBeGreaterThan(0)->and(substr_count($css, 'font-display: swap'))->toBe(substr_count($css, '@font-face'));
});

it('garde la feuille de style compilée sous 14 ko compressés', function () {
    $manifeste = base_path('public/build/manifest.json');
    if (! file_exists($manifeste)) {
        $this->markTestSkipped('Le front n’est pas compilé (npm run build).');
    }

    $css = collect(json_decode(file_get_contents($manifeste), true))->pluck('file')->filter(fn ($f) => str_ends_with($f, '.css'))->first();
    $taille = strlen(gzencode(file_get_contents(base_path("public/build/$css")), 6));

    expect($taille)->toBeLessThan(14 * 1024);
});

it('n’interroge le serveur que toutes les 30 secondes pour les notifications, et pas en plus par page', function () {
    $cloche = file_get_contents(base_path('resources/views/livewire/notification-bell.blade.php'));

    expect($cloche)->toContain('wire:poll.30s');
    expect(substr_count(file_get_contents(base_path('resources/views/components/layouts/app.blade.php')), 'wire:poll'))->toBe(0);
});
