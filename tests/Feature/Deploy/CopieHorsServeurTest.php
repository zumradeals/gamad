<?php

use Illuminate\Support\Facades\Process;

/*
 * Essais de deploy/copie-hors-serveur.sh avec le vrai rclone : le « stockage distant » est un dossier local derrière le même
 * chiffrement (type crypt) que celui du serveur, ou, pour « configurer », un vrai serveur compatible S3 (moto) lancé le temps du
 * test. PostgreSQL est remplacé par de faux outils, comme dans SauvegardeTest. Sans rclone, ces essais sont ignorés ; l'intégration
 * continue l'installe.
 */

function rcloneDisponible(): bool
{
    return trim((string) shell_exec('command -v rclone')) !== '';
}

function motoServeur(): ?string
{
    $chemin = getenv('GAMAD_MOTO_SERVER') ?: trim((string) shell_exec('command -v moto_server'));

    return $chemin !== '' && file_exists($chemin) ? $chemin : null;
}

/**
 * Prépare un faux serveur : dossier de sauvegarde avec une sauvegarde du jour, faux outils PostgreSQL, stockage distant chiffré local.
 *
 * @return array<string, string>
 */
function preparerCopie(array $options = []): array
{
    $dossier = sys_get_temp_dir().'/gamad-copie-'.bin2hex(random_bytes(4));
    $faux = "$dossier/faux";
    mkdir($faux, 0777, true);
    mkdir("$dossier/sauvegardes", 0700, true);
    mkdir("$dossier/distant", 0700, true);

    $script = fn (string $nom, string $contenu) => tap(file_put_contents("$faux/$nom", "#!/bin/sh\n$contenu\n"), fn () => chmod("$faux/$nom", 0755));
    $script('runuser', 'shift 3; exec "$@"');
    $script('pg_restore', '[ "$1" = "--list" ] && exit 0; [ -n "$FAUX_RESTORE_ECHOUE" ] && exit 1; cat >/dev/null; exit 0');
    $script('createdb', 'echo "createdb $*" >>"$FAUX_LOG"');
    $script('dropdb', 'echo "dropdb $*" >>"$FAUX_LOG"');
    $script('psql', 'case "$*" in *information_schema*) echo "${FAUX_TABLES:-38}";; *"from migrations"*) echo "${FAUX_MIGRATIONS:-19}";; *"from users"*) echo "${FAUX_PERSONNES:-7}";; esac');

    $horodatage = '2026-10-23_033000';
    if ($options['sauvegarde'] ?? true) {
        file_put_contents("$dossier/sauvegardes/gamad_essai-$horodatage.dump", "FAUX-DUMP-SECRET\n");
    }
    if ($options['env'] ?? true) {
        file_put_contents("$dossier/sauvegardes/env-$horodatage", "APP_KEY=base64:cle-secrete-de-test\n");
    }
    if ($options['photos'] ?? true) {
        file_put_contents("$dossier/photo.jpg", 'PHOTO-SECRETE');
        Process::run(['tar', '-czf', "$dossier/sauvegardes/photos-$horodatage.tar.gz", '-C', $dossier, 'photo.jpg'])->throw();
    }
    if (isset($options['age_jours'])) {
        foreach (glob("$dossier/sauvegardes/*") as $fichier) {
            touch($fichier, time() - 86400 * $options['age_jours']);
        }
    }

    file_put_contents("$dossier/deploy.conf", implode("\n", [
        "APP_DIR=$dossier/app",
        'DB_NAME=gamad_essai',
        "BACKUP_DIR=$dossier/sauvegardes",
        "LOG_FILE_OFFSITE=$dossier/copie.log",
    ])."\n");

    if ($options['configure'] ?? true) {
        $mot1 = trim(Process::input('phrase-de-test')->run(['rclone', 'obscure', '-'])->output());
        $mot2 = trim(Process::input('sel-de-test')->run(['rclone', 'obscure', '-'])->output());
        file_put_contents("$dossier/rclone.conf", "[gamad-copie]\ntype = crypt\nremote = $dossier/distant\nfilename_encryption = standard\ndirectory_name_encryption = true\npassword = $mot1\npassword2 = $mot2\n");
    }

    return ['dossier' => $dossier, 'faux' => $faux];
}

/**
 * @param  array<string, string>  $env
 * @param  array<int, string>  $args
 * @return array{code: int, sortie: string, journal: string}
 */
function lancerCopie(array $env, string $commande = 'copier', array $args = [], array $variables = []): array
{
    $dossier = $env['dossier'];
    $resultat = Process::env($variables + [
        'GAMAD_CONF' => "$dossier/deploy.conf",
        'GAMAD_RCLONE_CONF' => "$dossier/rclone.conf",
        'FAUX_LOG' => "$dossier/faux.log",
        'PATH' => $env['faux'].':'.getenv('PATH'),
        // Hors de portée de l'essai : un certificat ou un proxy de l'environnement de développement.
        'AWS_CA_BUNDLE' => '', 'AWS_ACCESS_KEY_ID' => '', 'AWS_SECRET_ACCESS_KEY' => '',
        'NO_PROXY' => '127.0.0.1,localhost', 'no_proxy' => '127.0.0.1,localhost',
    ])->timeout(120)->run(['bash', base_path('deploy/copie-hors-serveur.sh'), $commande, ...$args]);

    return [
        'code' => $resultat->exitCode(),
        'sortie' => $resultat->output().$resultat->errorOutput(),
        'journal' => file_exists("$dossier/copie.log") ? file_get_contents("$dossier/copie.log") : '',
    ];
}

/** @return array<int, string> Tous les fichiers du stockage distant (noms tels que le fournisseur les voit). */
function fichiersDistants(array $env): array
{
    return array_map('strval', array_values(array_filter(
        explode("\n", trim((string) shell_exec('find '.escapeshellarg($env['dossier'].'/distant').' -type f'))),
    )));
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/gamad-copie-*') ?: [] as $dossier) {
        Process::run(['rm', '-rf', $dossier]);
    }
});

// ─── Copier ──────────────────────────────────────────────────────────────────

it('ne copie rien et le dit tant que la copie n’est pas configurée', function () {
    $env = preparerCopie(['configure' => false]);

    $r = lancerCopie($env);

    expect($r['code'])->toBe(0)->and($r['sortie'])->toContain('n\'est pas configurée')->and($r['journal'])->toContain('non configurée');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('copie la dernière sauvegarde, chiffrée : ni le contenu ni les noms ne sont lisibles chez le fournisseur', function () {
    $env = preparerCopie();

    $r = lancerCopie($env);

    expect($r['code'])->toBe(0)->and($r['sortie'])->toContain('Copie hors serveur réussie et vérifiée')->and($r['journal'])->toContain('gamad_essai-2026-10-23_033000.dump');

    $fichiers = fichiersDistants($env);
    expect($fichiers)->toHaveCount(3);

    foreach ($fichiers as $fichier) {
        $contenu = file_get_contents($fichier);
        expect($contenu)->not->toContain('FAUX-DUMP')->not->toContain('APP_KEY')->not->toContain('PHOTO-SECRETE')->not->toContain('cle-secrete')
            ->and($fichier)->not->toContain('gamad_essai')->not->toContain('env-2026')->not->toContain('photos-2026')->not->toContain('.dump');
    }
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('copie sans photos quand la sauvegarde n’en contient pas', function () {
    $env = preparerCopie(['photos' => false]);

    $r = lancerCopie($env);

    expect($r['code'])->toBe(0)->and(fichiersDistants($env))->toHaveCount(2)->and($r['journal'])->not->toContain('photos-');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('refuse de copier sans sauvegarde locale, avec une sauvegarde trop ancienne, ou sans .env, et ne supprime rien', function (array $options, string $message) {
    $env = preparerCopie($options);
    // Une ancienne copie existe déjà chez le fournisseur : elle doit survivre à ce refus.
    file_put_contents("$env[dossier]/ancienne", 'ancienne copie');
    touch("$env[dossier]/ancienne", time() - 86400 * 40);
    Process::run(['rclone', '--config', "$env[dossier]/rclone.conf", 'copyto', "$env[dossier]/ancienne", 'gamad-copie:base/ancienne'])->throw();

    $r = lancerCopie($env);

    expect($r['code'])->toBe(1)->and($r['sortie'])->toContain($message)->and($r['journal'])->toContain('REFUS (copier)');
    expect(fichiersDistants($env))->toHaveCount(1);
})->with([
    'sauvegarde absente' => [['sauvegarde' => false], 'Aucune sauvegarde locale'],
    'sauvegarde trop ancienne' => [['age_jours' => 3], 'date de plus de 2 jours'],
    '.env absent' => [['env' => false], 'Aucune copie du fichier .env'],
])->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('retire les copies de plus de 15 jours seulement après une nouvelle copie vérifiée', function () {
    $env = preparerCopie();
    foreach ([40 => 'tres-ancienne', 5 => 'recente'] as $age => $nom) {
        file_put_contents("$env[dossier]/$nom", $nom);
        touch("$env[dossier]/$nom", time() - 86400 * $age);
        Process::run(['rclone', '--config', "$env[dossier]/rclone.conf", 'copyto', "$env[dossier]/$nom", "gamad-copie:base/$nom"])->throw();
    }
    expect(fichiersDistants($env))->toHaveCount(2);

    lancerCopie($env)['code'];

    $liste = Process::run(['rclone', '--config', "$env[dossier]/rclone.conf", 'lsf', '--files-only', 'gamad-copie:base'])->output();
    expect($liste)->toContain('recente')->toContain('gamad_essai-2026-10-23_033000.dump')->not->toContain('tres-ancienne');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('échoue franchement quand la vérification de la copie échoue, sans prétendre à une réussite', function () {
    $env = preparerCopie();
    // Un stockage dont la configuration de chiffrement est invalide : la copie ne peut pas réussir.
    file_put_contents("$env[dossier]/rclone.conf", "[gamad-copie]\ntype = crypt\nremote = /dossier/qui/n/existe/pas/et/ne/peut/etre/cree\npassword = pas-obscurci\n");

    $r = lancerCopie($env);

    expect($r['code'])->toBe(1)->and($r['sortie'])->not->toContain('réussie')->and($r['journal'])->toContain('ÉCHEC (copier')->not->toContain('Copie hors serveur réussie');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

// ─── Rapatrier et restaurer ──────────────────────────────────────────────────

it('rapatrie la dernière copie déchiffrée et exige un dossier neuf', function () {
    $env = preparerCopie();
    lancerCopie($env);

    $r = lancerCopie($env, 'telecharger', ["$env[dossier]/retour"]);

    expect($r['code'])->toBe(0)
        ->and(file_get_contents("$env[dossier]/retour/gamad_essai-2026-10-23_033000.dump"))->toBe("FAUX-DUMP-SECRET\n")
        ->and(file_get_contents("$env[dossier]/retour/env-2026-10-23_033000"))->toContain('APP_KEY=base64:cle-secrete-de-test')
        ->and(file_exists("$env[dossier]/retour/photos-2026-10-23_033000.tar.gz"))->toBeTrue();

    $deuxieme = lancerCopie($env, 'telecharger', ["$env[dossier]/retour"]);
    expect($deuxieme['code'])->toBe(1)->and($deuxieme['sortie'])->toContain('n\'est pas vide');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('rapatrie la copie la plus récente quand il y en a plusieurs', function () {
    $env = preparerCopie();
    lancerCopie($env);
    file_put_contents("$env[dossier]/sauvegardes/gamad_essai-2026-10-24_033000.dump", "FAUX-DUMP-DU-LENDEMAIN\n");
    file_put_contents("$env[dossier]/sauvegardes/env-2026-10-24_033000", "APP_KEY=base64:lendemain\n");
    touch("$env[dossier]/sauvegardes/gamad_essai-2026-10-24_033000.dump", time() + 10);
    touch("$env[dossier]/sauvegardes/env-2026-10-24_033000", time() + 10);
    lancerCopie($env);

    lancerCopie($env, 'telecharger', ["$env[dossier]/retour"]);

    expect(file_get_contents("$env[dossier]/retour/gamad_essai-2026-10-24_033000.dump"))->toBe("FAUX-DUMP-DU-LENDEMAIN\n");
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('refuse de rapatrier quand rien n’a été copié', function () {
    $env = preparerCopie();

    $r = lancerCopie($env, 'telecharger', ["$env[dossier]/retour"]);

    expect($r['code'])->toBe(1)->and($r['sortie'])->toContain('Aucune copie de la base lisible');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('refuse de rapatrier sans configuration ou sans dossier', function () {
    $env = preparerCopie(['configure' => false]);
    expect(lancerCopie($env, 'telecharger', ["$env[dossier]/retour"])['sortie'])->toContain('n\'est pas configurée');

    $env = preparerCopie();
    expect(lancerCopie($env, 'telecharger')['sortie'])->toContain('Indiquez le dossier');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('réussit l’essai de restauration dans une base temporaire, la supprime, et le dit au journal', function () {
    $env = preparerCopie();
    lancerCopie($env);

    $r = lancerCopie($env, 'essai-restauration');

    expect($r['code'])->toBe(0)->and($r['sortie'])->toContain('ESSAI DE RESTAURATION RÉUSSI')->toContain('38 tables, 19 migrations, 7 personnes')
        ->and($r['journal'])->toContain('ESSAI DE RESTAURATION RÉUSSI')->toContain('base temporaire');

    // La base d'essai est créée puis supprimée ; la base de production n'est jamais touchée.
    $commandes = file_get_contents("$env[dossier]/faux.log");
    expect($commandes)->toContain('createdb gamad_essai_essai_restauration')->toContain('dropdb gamad_essai_essai_restauration')
        ->and($commandes)->not->toMatch('/(createdb|dropdb)( --if-exists)? gamad_essai$/m');
    expect(array_values(array_diff(scandir(sys_get_temp_dir()), ['.', '..'])))->not->toContain('copie-restauree');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('refuse de déclarer réussi l’essai quand la base restaurée est vide ou illisible', function (array $variables, string $message) {
    $env = preparerCopie();
    lancerCopie($env);

    $r = lancerCopie($env, 'essai-restauration', [], $variables);

    expect($r['code'])->toBe(1)->and($r['sortie'])->toContain($message)->and($r['journal'])->not->toContain('ESSAI DE RESTAURATION RÉUSSI');
})->with([
    'base vide' => [['FAUX_TABLES' => '0'], 'La base restaurée est vide'],
    'sans migrations' => [['FAUX_MIGRATIONS' => '0'], 'La base restaurée est vide'],
    'restauration refusée' => [['FAUX_RESTORE_ECHOUE' => '1'], 'La restauration dans la base d\'essai a échoué'],
])->skip(fn () => ! rcloneDisponible(), 'rclone absent');

// ─── État ────────────────────────────────────────────────────────────────────

it('dit que la copie n’est pas configurée, et qu’aucune copie n’existe hors du serveur', function () {
    $env = preparerCopie(['configure' => false]);

    $r = lancerCopie($env, 'etat');

    expect($r['code'])->toBe(0)->and($r['sortie'])->toContain('NON CONFIGURÉE')->toContain('Aucune copie n\'existe hors du serveur');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('rapporte la dernière réussite, le dernier essai, et signale un essai jamais fait', function () {
    $env = preparerCopie();

    expect(lancerCopie($env, 'etat')['sortie'])->toContain('Dernière réussite : aucune pour le moment')->toContain('JAMAIS FAIT');

    lancerCopie($env);
    $apres = lancerCopie($env, 'etat')['sortie'];
    expect($apres)->toContain('Copie hors serveur réussie et vérifiée')->toContain('JAMAIS FAIT');

    lancerCopie($env, 'essai-restauration');
    expect(lancerCopie($env, 'etat')['sortie'])->not->toContain('JAMAIS FAIT')->toContain('ESSAI DE RESTAURATION RÉUSSI');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('présente un échec plus ancien que la dernière réussite comme dépassé', function () {
    $env = preparerCopie(['sauvegarde' => false]);
    lancerCopie($env); // refus : pas de sauvegarde
    expect(lancerCopie($env, 'etat')['sortie'])->toContain('Dernier échec ou refus : [')->toContain('REFUS (copier)');

    file_put_contents("$env[dossier]/sauvegardes/gamad_essai-2026-10-23_033000.dump", "FAUX-DUMP-SECRET\n");
    sleep(1);
    lancerCopie($env);

    expect(lancerCopie($env, 'etat')['sortie'])->toContain('Dernier échec (depuis : une copie a réussi)');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('refuse une commande inconnue', function () {
    $env = preparerCopie();

    $r = lancerCopie($env, 'supprimer-tout');

    expect($r['code'])->toBe(1)->and($r['sortie'])->toContain('Commande inconnue');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

// ─── Configurer (vrai serveur compatible S3 pour l'essai) ────────────────────

/**
 * Lance moto sur un port libre, crée le bucket, exécute le callback puis arrête le serveur.
 *
 * @param  callable(string $adresse): mixed  $essai
 */
function avecStockageS3(callable $essai): mixed
{
    $port = random_int(20000, 40000);
    $serveur = Process::start([motoServeur(), '-p', (string) $port]);
    $adresse = "http://127.0.0.1:$port";

    try {
        for ($i = 0; $i < 50; $i++) {
            if (@file_get_contents("$adresse/moto-api/") !== false) {
                break;
            }
            usleep(200000);
        }

        $conf = tempnam(sys_get_temp_dir(), 'gamad-s3-');
        file_put_contents($conf, "[s]\ntype = s3\nprovider = Other\nenv_auth = false\naccess_key_id = cle\nsecret_access_key = secret\nendpoint = $adresse\n");
        Process::env(['AWS_CA_BUNDLE' => '', 'NO_PROXY' => '127.0.0.1'])->run(['rclone', '--config', $conf, 'mkdir', 's:gamad-essai'])->throw();
        unlink($conf);

        return $essai($adresse);
    } finally {
        $serveur->stop();
    }
}

function configurerS3(array $env, string $adresse, array $variables = []): array
{
    return lancerCopie($env, 'configurer', [], $variables + [
        'GAMAD_S3_ENDPOINT' => $adresse, 'GAMAD_S3_BUCKET' => 'gamad-essai',
        'GAMAD_S3_ACCESS_KEY' => 'cle-d-acces-de-test', 'GAMAD_S3_SECRET_KEY' => 'SECRET-TRES-SECRET',
    ]);
}

it('configure un vrai stockage S3 : essai d’écriture, phrase de secours affichée une fois, accès protégés, copie puis rapatriement', function () {
    $env = preparerCopie(['configure' => false]);

    avecStockageS3(function (string $adresse) use ($env) {
        $r = configurerS3($env, $adresse);

        expect($r['code'])->toBe(0)->and($r['sortie'])->toContain('l\'essai d\'écriture et de relecture a réussi')->toContain('PHRASE DE SECOURS');
        preg_match('/^\s{4}([A-Za-z0-9]{4}(?:-[A-Za-z0-9]{4}){7})$/m', $r['sortie'], $phrase);
        expect($phrase)->not->toBeEmpty();

        // Les accès sont dans un fichier lisible par l'administrateur seul ; la phrase n'y figure pas en clair, ni au journal.
        $conf = "$env[dossier]/rclone.conf";
        expect(decoct(fileperms($conf) & 0777))->toBe('600')
            ->and(file_get_contents($conf))->toContain('cle-d-acces-de-test')->not->toContain($phrase[1]);
        expect($r['journal'])->not->toContain($phrase[1])->not->toContain('SECRET-TRES-SECRET');

        // Copie et rapatriement à travers le vrai S3.
        expect(lancerCopie($env)['code'])->toBe(0);
        expect(lancerCopie($env, 'essai-restauration')['sortie'])->toContain('ESSAI DE RESTAURATION RÉUSSI');

        // Nouveau serveur : mêmes accès, même phrase → les copies sont lisibles. Autre phrase → rien de lisible.
        $nouveau = preparerCopie(['configure' => false, 'sauvegarde' => false, 'env' => false, 'photos' => false]);
        $ok = configurerS3($nouveau, $adresse, ['GAMAD_PHRASE_SECOURS' => $phrase[1]]);
        expect($ok['code'])->toBe(0)->and($ok['sortie'])->not->toContain('PHRASE DE SECOURS');
        $relu = lancerCopie($nouveau, 'telecharger', ["$nouveau[dossier]/retour"]);
        expect($relu['code'])->toBe(0)->and(file_get_contents("$nouveau[dossier]/retour/gamad_essai-2026-10-23_033000.dump"))->toBe("FAUX-DUMP-SECRET\n");

        $autre = preparerCopie(['configure' => false, 'sauvegarde' => false, 'env' => false, 'photos' => false]);
        configurerS3($autre, $adresse, ['GAMAD_PHRASE_SECOURS' => 'une-autre-phrase-de-secours-tres-longue']);
        $illisible = lancerCopie($autre, 'telecharger', ["$autre[dossier]/retour"]);
        expect($illisible['code'])->toBe(1)->and($illisible['sortie'])->toContain('Aucune copie de la base lisible');
    });
})->skip(fn () => ! rcloneDisponible() || motoServeur() === null, 'rclone ou moto absent');

it('refuse de reconfigurer sans --forcer, et garde l’ancienne configuration si le nouvel essai échoue', function () {
    $env = preparerCopie(['configure' => false]);

    avecStockageS3(function (string $adresse) use ($env) {
        expect(configurerS3($env, $adresse)['code'])->toBe(0);
        $avant = file_get_contents("$env[dossier]/rclone.conf");

        $refus = configurerS3($env, $adresse);
        expect($refus['code'])->toBe(1)->and($refus['sortie'])->toContain('déjà configurée')->toContain('--forcer')->and(file_get_contents("$env[dossier]/rclone.conf"))->toBe($avant);

        $echec = lancerCopie($env, 'configurer', ['--forcer'], [
            'GAMAD_S3_ENDPOINT' => 'http://127.0.0.1:1', 'GAMAD_S3_BUCKET' => 'gamad-essai',
            'GAMAD_S3_ACCESS_KEY' => 'a', 'GAMAD_S3_SECRET_KEY' => 'SECRET-DE-L-ESSAI-RATE',
        ]);
        expect($echec['code'])->toBe(1)->and($echec['sortie'])->toContain('Rien n\'a été enregistré')
            ->and(file_get_contents("$env[dossier]/rclone.conf"))->toBe($avant)
            ->and($echec['journal'])->not->toContain('SECRET-DE-L-ESSAI-RATE');
    });
})->skip(fn () => ! rcloneDisponible() || motoServeur() === null, 'rclone ou moto absent');

it('n’enregistre rien quand le stockage refuse l’essai d’écriture', function () {
    $env = preparerCopie(['configure' => false]);

    $r = configurerS3($env, 'http://127.0.0.1:1');

    expect($r['code'])->toBe(1)->and($r['sortie'])->toContain('Rien n\'a été enregistré')->and(file_exists("$env[dossier]/rclone.conf"))->toBeFalse()
        ->and($r['journal'])->not->toContain('SECRET-TRES-SECRET');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('valide les informations du stockage avant tout essai', function (array $variables, string $message) {
    $env = preparerCopie(['configure' => false]);

    $r = lancerCopie($env, 'configurer', [], $variables + [
        'GAMAD_S3_ENDPOINT' => 'https://s3.exemple.test', 'GAMAD_S3_BUCKET' => 'gamad-copies',
        'GAMAD_S3_ACCESS_KEY' => 'cle', 'GAMAD_S3_SECRET_KEY' => 'secret',
    ]);

    expect($r['code'])->toBe(1)->and($r['sortie'])->toContain($message)->and(file_exists("$env[dossier]/rclone.conf"))->toBeFalse();
})->with([
    'adresse sans chiffrement' => [['GAMAD_S3_ENDPOINT' => 'http://s3.exemple.test'], 'doit commencer par https://'],
    'adresse locale lookalike' => [['GAMAD_S3_ENDPOINT' => 'http://127.0.0.1.exemple.test'], 'doit commencer par https://'],
    'bucket invalide' => [['GAMAD_S3_BUCKET' => 'Mauvais Nom/avec espace'], 'Le nom du bucket n\'est pas valide'],
    'clé avec espace' => [['GAMAD_S3_SECRET_KEY' => 'a b'], 'ne doivent contenir aucun espace'],
    'phrase trop courte' => [['GAMAD_PHRASE_SECOURS' => 'courte'], 'trop courte'],
    'information manquante' => [['GAMAD_S3_ACCESS_KEY' => ''], 'Information manquante'],
])->skip(fn () => ! rcloneDisponible(), 'rclone absent');

// ─── Installation ────────────────────────────────────────────────────────────

it('installe la copie hors serveur avec son minuteur nocturne, sa commande et sa désinstallation', function () {
    $install = file_get_contents(base_path('deploy/install.sh'));
    $desinstall = file_get_contents(base_path('deploy/desinstaller.sh'));

    expect($install)
        ->toContain('command -v rclone >/dev/null 2>&1 || A_INSTALLER+=(rclone)')
        ->toContain('copie-hors-serveur.sh "$LIB_DIR/copie-hors-serveur.sh"')
        ->toContain('/usr/local/sbin/gamad-reseau-copie-hors-serveur')
        ->toContain('gamad-reseau-copie-hors-serveur.service')
        ->toContain('OnCalendar=*-*-* 04:30:00')
        ->toContain('After=gamad-reseau-sauvegarde.service')
        ->toContain('gamad-reseau-deploy.timer gamad-reseau-sauvegarde.timer gamad-reseau-copie-hors-serveur.timer')
        ->toContain('LOG_FILE_OFFSITE=$LOG_DIR/copie-hors-serveur.log')
        ->toContain('À CONFIGURER');

    expect($desinstall)->toContain('gamad-reseau-copie-hors-serveur.timer')->toContain('/usr/local/sbin/gamad-reseau-copie-hors-serveur');
});

it('ne met aucun accès ni aucune phrase de secours dans le dépôt', function () {
    $script = file_get_contents(base_path('deploy/copie-hors-serveur.sh'));

    expect($script)->toContain('chmod 600 "$RCLONE_CONF"')
        ->and($script)->toContain('rclone obscure -')
        ->and($script)->not->toMatch('/(secret_access_key|access_key_id)\s*=\s*[A-Za-z0-9]{12,}/');
});
