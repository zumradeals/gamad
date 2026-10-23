<?php

use Illuminate\Support\Facades\Process;

/*
 * Contrôles statiques des scripts de deploy/ : mêmes règles que ArretReelTest, vérifiées sur tout le code, pas seulement
 * sur des scénarios.
 */

/** @return array<int, string> */
function scriptsDeploy(): array
{
    // Appelée avant le démarrage de l'application (jeux de données Pest) : base_path() n'est pas disponible.
    return glob(dirname(__DIR__, 3).'/deploy/*.sh');
}

/**
 * Lignes logiques (les lignes terminées par « \ » sont jointes), commentaires exclus.
 *
 * @return array<int, array{numero: int, texte: string}>
 */
function lignesLogiques(string $fichier): array
{
    $logiques = [];
    $courante = '';
    $debut = 0;

    foreach (file($fichier, FILE_IGNORE_NEW_LINES) as $i => $ligne) {
        if ($courante === '') {
            $debut = $i + 1;
        }
        if (str_ends_with(rtrim($ligne), '\\')) {
            $courante .= rtrim(substr(rtrim($ligne), 0, -1)).' ';

            continue;
        }
        $texte = $courante.$ligne;
        $courante = '';
        if (! str_starts_with(ltrim($texte), '#')) {
            $logiques[] = ['numero' => $debut, 'texte' => $texte];
        }
    }

    return $logiques;
}

it('passe la vérification de syntaxe de bash pour chaque script', function (string $script) {
    $resultat = Process::run(['bash', '-n', $script]);

    $this->assertTrue($resultat->successful(), $resultat->errorOutput());
})->with(fn () => collect(scriptsDeploy())->mapWithKeys(fn ($s) => [basename($s) => $s])->all());

/** @return array<int, string> */
function scriptsAvecPiegeErr(): array
{
    return array_values(array_filter(
        scriptsDeploy(),
        fn (string $script) => preg_match('/^\s*trap\s+(?!-\s)\S.*\bERR\b/m', file_get_contents($script)) === 1,
    ));
}

it('compte au moins les trois scripts qui installent un piège d’erreur (ERR)', function () {
    $noms = array_map('basename', scriptsAvecPiegeErr());
    sort($noms);

    expect($noms)->toBe(['deploy.sh', 'install.sh', 'sauvegarde.sh']);
});

it('garde chaque piège d’erreur (ERR) contre l’exécution dans un sous-shell', function (string $script) {
    $contenu = file_get_contents($script);

    preg_match_all('/^\s*trap\s+(?:\'([^\']+)\'|"([^"]+)"|(\S+))\s+ERR\b/m', $contenu, $pieges, PREG_SET_ORDER);

    foreach ($pieges as $piege) {
        $commande = $piege[1] ?: ($piege[2] ?: $piege[3]);
        $fonction = strtok($commande, ' ');

        if ($fonction === '-') {
            continue; // « trap - ERR » retire le piège : ce n'est pas un gestionnaire
        }

        $this->assertSame(
            1,
            preg_match('/^'.preg_quote($fonction, '/').'\(\)\s*\{(.*?)^\}/ms', $contenu, $corps),
            "Le piège ERR de $script appelle « $fonction », introuvable : il doit être une fonction du script.",
        );
        $this->assertStringContainsString('$BASHPID', $corps[1], "La fonction $fonction ($script) doit sortir sans bruit hors du shell principal.");
    }
})->with(fn () => collect(scriptsAvecPiegeErr())->mapWithKeys(fn ($s) => [basename($s) => $s])->all());

it('empêche arreter() d’afficher un message sans arrêter le script principal', function () {
    $install = file_get_contents(base_path('deploy/install.sh'));

    expect($install)->toContain('kill -TERM "$$"')
        ->and($install)->toContain("trap 'exit 1' TERM");
});

it('ne place aucune commande pouvant échouer dans un message des scripts qui s’arrêtent sur erreur', function (string $script) {
    $autorisees = ['date', 'basename', 'dirname', 'id', 'hostname', 'wc'];
    $violations = [];

    foreach (lignesLogiques($script) as $ligne) {
        if (! preg_match('/^\s*(ok|info|avertir|arreter|journal|echo|printf)\b/', $ligne['texte'])) {
            continue;
        }
        preg_match_all('/\$\((?!\()\s*([A-Za-z_\/.\-]+)/', $ligne['texte'], $commandes);
        foreach ($commandes[1] as $commande) {
            if (! in_array($commande, $autorisees, true)) {
                $violations[] = basename($script).':'.$ligne['numero'].' $('.$commande.' …) dans un message : '.trim($ligne['texte']);
            }
        }
    }

    $this->assertSame([], $violations, "Calculez la valeur dans une variable avant le message (avec « || true » si l'échec est sans gravité) :\n".implode("\n", $violations));
})->with(fn () => collect(scriptsDeploy())
    ->reject(fn ($s) => basename($s) === 'diagnostic.sh')
    ->mapWithKeys(fn ($s) => [basename($s) => $s])
    ->all());

it('exempte diagnostic.sh de cette règle parce qu’il ne peut jamais afficher de message d’arrêt', function () {
    $diagnostic = file_get_contents(base_path('deploy/diagnostic.sh'));

    // Pas de « set -e » ni de piège ERR : une substitution qui échoue y donne un texte vide, jamais un faux arrêt.
    expect($diagnostic)->not->toMatch('/^\s*set\s+-[A-Za-z]*e/m')
        ->and($diagnostic)->not->toMatch('/\btrap\b/')
        ->and($diagnostic)->not->toContain('arreter');
});

it('exige l’extension PHP gd (photos de profil du Lot 1) et sait quel paquet l’installe', function () {
    $install = file_get_contents(dirname(__DIR__, 3).'/deploy/install.sh');

    expect($install)->toMatch('/for ext in [^;]*\bgd\b[^;]*; do/')->toContain('gd) paquet="php$PHP_V-gd"');
});

it('sauvegarde les photos de profil, avec la même rotation et sans rien toucher d’autre', function () {
    $script = file_get_contents(dirname(__DIR__, 3).'/deploy/sauvegarde.sh');

    expect($script)
        ->toContain('$APP_DIR/shared/storage/app/private')
        ->toContain('profile-photos')
        ->toContain('photos-$HORODATAGE.tar.gz')
        ->toContain('for motif in "$DB_NAME-*.dump" "env-*" "photos-*.tar.gz"; do')
        ->toContain('tar -tzf')
        // Aucune écriture hors du dossier de sauvegarde, aucun autre service : ni systemctl, ni nginx, ni apt.
        ->not->toMatch('/\b(systemctl|nginx|apt|apt-get|service)\b/');
});

it('documente la restauration des photos dans LISEZMOI.md', function () {
    $lisezmoi = file_get_contents(dirname(__DIR__, 3).'/deploy/LISEZMOI.md');

    expect($lisezmoi)->toContain('photos-AAAA-MM-JJ_HHMMSS.tar.gz')->toContain('tar -xzf')->toContain('profile-photos');
});
