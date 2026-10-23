<?php

use App\Models\User;
use App\Services\BackupStorage;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

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

// ─── Configuration demandée à l'application, état écrit pour l'application ───

it('demande sa configuration à l’application quand aucun fichier n’est fourni, et supprime le fichier temporaire', function () {
    $env = preparerCopie();
    $conf = file_get_contents("$env[dossier]/rclone.conf");
    unlink("$env[dossier]/rclone.conf");
    // Faux « php artisan » : écrit la configuration préparée, ou sort avec le code demandé.
    file_put_contents("$env[faux]/php", "#!/bin/sh\ncase \"\$FAUX_ARTISAN_CODE\" in\n\"\") cat \"$env[dossier]/conf-appli\";;\n*) exit \"\$FAUX_ARTISAN_CODE\";;\nesac\n");
    chmod("$env[faux]/php", 0755);
    file_put_contents("$env[dossier]/conf-appli", $conf);
    file_put_contents("$env[dossier]/deploy.conf", file_get_contents("$env[dossier]/deploy.conf")."APP_USER=gamad-reseau\nPHP_BIN=php\n");

    $avant = glob(sys_get_temp_dir().'/tmp.*') ?: [];
    $r = lancerCopie($env, 'copier', [], ['GAMAD_RCLONE_CONF' => '']);

    expect($r['code'])->toBe(0)->and($r['sortie'])->toContain('Copie hors serveur réussie et vérifiée')->and(fichiersDistants($env))->toHaveCount(3);
    expect(array_diff(glob(sys_get_temp_dir().'/tmp.*') ?: [], $avant))->toBe([]);

    // Code 2 : pas configurée (ce n'est pas une panne) ; autre code : panne.
    $nonConfiguree = lancerCopie($env, 'copier', [], ['GAMAD_RCLONE_CONF' => '', 'FAUX_ARTISAN_CODE' => '2']);
    expect($nonConfiguree['code'])->toBe(0)->and($nonConfiguree['sortie'])->toContain('n\'est pas configurée');

    $panne = lancerCopie($env, 'copier', [], ['GAMAD_RCLONE_CONF' => '', 'FAUX_ARTISAN_CODE' => '1']);
    expect($panne['code'])->toBe(1)->and($panne['sortie'])->toContain('Impossible de lire la configuration');
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('écrit l’état pour l’application : dernière réussite, dernier essai, dernier incident', function () {
    $env = preparerCopie();
    mkdir("$env[dossier]/etat");
    $variables = ['GAMAD_BACKUP_STATE_FILE' => "$env[dossier]/etat/copie-etat"];

    lancerCopie($env, 'copier', [], $variables);
    $etat = file_get_contents("$env[dossier]/etat/copie-etat");
    expect($etat)->toMatch('/^derniere_reussite=\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/m')->not->toContain('dernier_essai')->not->toContain('dernier_echec');
    expect(decoct(fileperms("$env[dossier]/etat/copie-etat") & 0777))->toBe('644');

    lancerCopie($env, 'essai-restauration', [], $variables);
    expect(file_get_contents("$env[dossier]/etat/copie-etat"))->toMatch('/^dernier_essai=/m')->toMatch('/^derniere_reussite=/m');

    // Un refus s'écrit sans guillemets ni barre verticale ; la dernière réussite reste.
    Process::run(['rm', '-f', ...glob("$env[dossier]/sauvegardes/*.dump")]);
    lancerCopie($env, 'copier', [], $variables);
    $apres = file_get_contents("$env[dossier]/etat/copie-etat");
    expect($apres)->toMatch('/^dernier_echec=\d{4}-[^|]+\|Aucune sauvegarde locale/m')->toMatch('/^derniere_reussite=/m');
    expect(substr_count($apres, 'derniere_reussite='))->toBe(1);
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

it('ne fait pas échouer la copie quand le dossier d’état n’existe pas', function () {
    $env = preparerCopie();

    $r = lancerCopie($env, 'copier', [], ['GAMAD_BACKUP_STATE_FILE' => "$env[dossier]/absent/copie-etat"]);

    expect($r['code'])->toBe(0)->and(file_exists("$env[dossier]/absent"))->toBeFalse();
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

// ─── Chaîne complète avec un vrai serveur compatible S3 (moto) ───────────────

/**
 * Lance moto sur un port libre, crée le bucket, exécute l'essai puis arrête le serveur.
 *
 * @param  callable(string $adresse): mixed  $essai
 */
function avecStockageS3(callable $essai): mixed
{
    $port = random_int(20000, 40000);
    $serveur = Process::start([motoServeur(), '-p', (string) $port]);
    $adresse = "http://127.0.0.1:$port";
    // Un certificat d'autorité de l'environnement de développement fait échouer certaines versions de rclone : hors de portée de l'essai.
    $ancien = getenv('AWS_CA_BUNDLE');
    putenv('AWS_CA_BUNDLE');

    try {
        for ($i = 0; $i < 50; $i++) {
            if (@file_get_contents("$adresse/moto-api/") !== false) {
                break;
            }
            usleep(200000);
        }

        $conf = tempnam(sys_get_temp_dir(), 'gamad-s3-');
        file_put_contents($conf, "[s]\ntype = s3\nprovider = Other\nenv_auth = false\naccess_key_id = cle\nsecret_access_key = secret\nendpoint = $adresse\n");
        Process::env(['NO_PROXY' => '127.0.0.1'])->run(['rclone', '--config', $conf, 'mkdir', 's:gamad-essai'])->throw();
        unlink($conf);

        return $essai($adresse);
    } finally {
        $serveur->stop();
        if ($ancien !== false) {
            putenv("AWS_CA_BUNDLE=$ancien");
        }
    }
}

it('chaîne complète avec un vrai S3 : accès saisis dans l’application, copie chiffrée, rapatriement avec la seule phrase, essai de restauration', function () {
    $admin = User::factory()->superAdmin()->create(['password' => 'motdepasse-solide']);
    $env = preparerCopie(['configure' => false]);

    avecStockageS3(function (string $adresse) use ($admin, $env) {
        $stockage = app(BackupStorage::class);
        $phrase = $stockage->save($admin, ['endpoint' => $adresse, 'bucket' => 'gamad-essai', 'access_key' => 'cle-d-acces', 'secret_key' => 'cle-secrete']);
        expect($phrase)->not->toBeNull()->and($stockage->configured())->toBeTrue();

        // Ce que le script reçoit de l'application, puis la copie et l'essai de restauration à travers le vrai S3.
        file_put_contents("$env[dossier]/rclone.conf", $stockage->rcloneConfig());
        expect(lancerCopie($env)['sortie'])->toContain('Copie hors serveur réussie et vérifiée');
        expect(lancerCopie($env, 'essai-restauration')['sortie'])->toContain('ESSAI DE RESTAURATION RÉUSSI');

        // Chez le fournisseur : rien de lisible.
        $contenu = Process::env(['NO_PROXY' => '127.0.0.1'])->run(['bash', '-c', 'curl -s --noproxy "*" '.escapeshellarg("$adresse/gamad-essai?list-type=2")])->output();
        expect($contenu)->not->toContain('gamad_essai')->not->toContain('photos-2026');

        // Nouveau serveur, mêmes accès, MÊME PHRASE : les copies se relisent. Une autre phrase ne relit rien.
        DB::table('settings')->where('key', 'like', 'backup_%')->delete();
        app()->forgetInstance(Settings::class);
        app()->singleton(Settings::class);
        $nouveau = preparerCopie(['configure' => false, 'sauvegarde' => false, 'env' => false, 'photos' => false]);
        $stockageNeuf = app(BackupStorage::class);
        $stockageNeuf->save($admin, ['endpoint' => $adresse, 'bucket' => 'gamad-essai', 'access_key' => 'cle-d-acces', 'secret_key' => 'cle-secrete', 'passphrase' => $phrase]);
        file_put_contents("$nouveau[dossier]/rclone.conf", $stockageNeuf->rcloneConfig());
        $relu = lancerCopie($nouveau, 'telecharger', ["$nouveau[dossier]/retour"]);
        expect($relu['code'])->toBe(0)->and(file_get_contents("$nouveau[dossier]/retour/gamad_essai-2026-10-23_033000.dump"))->toBe("FAUX-DUMP-SECRET\n");

        DB::table('settings')->where('key', 'like', 'backup_%')->delete();
        app()->forgetInstance(Settings::class);
        app()->singleton(Settings::class);
        $autre = preparerCopie(['configure' => false, 'sauvegarde' => false, 'env' => false, 'photos' => false]);
        $stockageAutre = app(BackupStorage::class);
        $stockageAutre->save($admin, ['endpoint' => $adresse, 'bucket' => 'gamad-essai', 'access_key' => 'cle-d-acces', 'secret_key' => 'cle-secrete', 'passphrase' => 'une-autre-phrase-de-secours-longue']);
        file_put_contents("$autre[dossier]/rclone.conf", $stockageAutre->rcloneConfig());
        $illisible = lancerCopie($autre, 'telecharger', ["$autre[dossier]/retour"]);
        expect($illisible['code'])->toBe(1)->and($illisible['sortie'])->toContain('Aucune copie de la base lisible');
    });
})->skip(fn () => ! rcloneDisponible() || motoServeur() === null, 'rclone ou moto absent');

it('refuse d’enregistrer des accès quand le vrai stockage est injoignable, et n’enregistre rien', function () {
    $admin = User::factory()->superAdmin()->create();

    $erreur = rescue(fn () => app(BackupStorage::class)->save($admin, ['endpoint' => 'http://127.0.0.1:1', 'bucket' => 'gamad-essai', 'access_key' => 'a-a-a', 'secret_key' => 'SECRET-DE-L-ESSAI-RATE']), fn ($e) => $e, false);

    expect($erreur)->toBeInstanceOf(ValidationException::class)->and($erreur->errors()['storage'][0])->toContain('Rien n’a été enregistré')->not->toContain('SECRET-DE-L-ESSAI-RATE');
    expect(DB::table('settings')->where('key', 'like', 'backup_%')->count())->toBe(0);
})->skip(fn () => ! rcloneDisponible(), 'rclone absent');

// ─── Installation ────────────────────────────────────────────────────────────

it('installe la copie hors serveur avec son minuteur nocturne, sa commande et sa désinstallation', function () {
    $install = file_get_contents(base_path('deploy/install.sh'));
    $desinstall = file_get_contents(base_path('deploy/desinstaller.sh'));

    expect($install)
        ->toContain('command -v rclone >/dev/null 2>&1 || A_INSTALLER+=(rclone)')
        ->toContain('"$LIB_DIR/copie-hors-serveur.sh"')
        ->toContain('/usr/local/sbin/gamad-reseau-copie-hors-serveur')
        ->toContain('gamad-reseau-copie-hors-serveur.service')
        ->toContain('OnCalendar=*-*-* 04:30:00')
        ->toContain('After=gamad-reseau-sauvegarde.service')
        ->toContain('gamad-reseau-deploy.timer gamad-reseau-sauvegarde.timer gamad-reseau-copie-hors-serveur.timer')
        ->toContain('LOG_FILE_OFFSITE=$LOG_DIR/copie-hors-serveur.log')
        ->toContain('À CONFIGURER')
        ->toContain('install -d -m 755 -o root -g root /var/lib/gamad-reseau');

    expect($desinstall)->toContain('/var/lib/gamad-reseau')->toContain('gamad-reseau-copie-hors-serveur.timer')->toContain('/usr/local/sbin/gamad-reseau-copie-hors-serveur');
});

it('ne met aucun accès ni aucune phrase de secours dans le dépôt', function () {
    $script = file_get_contents(base_path('deploy/copie-hors-serveur.sh'));

    expect($script)->toContain('gamad:copie-configuration')
        ->toContain('rm -f "$CONF_TEMP"')
        ->and($script)->not->toMatch('/(secret_access_key|access_key_id)\s*=\s*[A-Za-z0-9]{12,}/')
        ->and($script)->not->toContain('configurer()');
});
