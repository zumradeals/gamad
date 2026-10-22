<?php

use Illuminate\Support\Facades\Process;

/**
 * Exécute deploy/sauvegarde.sh dans un dossier temporaire, avec de faux outils (runuser, pg_dump, pg_restore, du).
 *
 * @return array{code: int, dossier: string, journal: string, sortie: string}
 */
function lancerSauvegarde(array $options = []): array
{
    $dossier = sys_get_temp_dir().'/gamad-sauvegarde-'.bin2hex(random_bytes(4));
    $faux = "$dossier/faux";
    mkdir("$faux", 0777, true);
    mkdir("$dossier/app/shared", 0777, true);
    file_put_contents("$dossier/app/shared/.env", "APP_KEY=base64:cle-de-test\n");

    $script = fn (string $nom, string $contenu) => tap(file_put_contents("$faux/$nom", "#!/bin/sh\n$contenu\n"), fn () => chmod("$faux/$nom", 0755));
    $script('runuser', 'shift 3; exec "$@"');                                   // runuser -u postgres -- commande…
    $script('pg_dump', ($options['pg_dump_echoue'] ?? false) ? 'exit 1' : 'echo FAUX-DUMP');
    $script('pg_restore', 'exit 0');
    if ($options['du_echoue'] ?? false) {
        $script('du', 'exit 1');
    }

    file_put_contents("$dossier/deploy.conf", implode("\n", [
        "APP_DIR=$dossier/app",
        'DB_NAME=gamad_essai',
        "BACKUP_DIR=$dossier/sauvegardes",
        "LOG_FILE_BACKUP=$dossier/sauvegarde.log",
        'BACKUP_KEEP='.($options['garder'] ?? 7),
    ])."\n");

    // Anciennes sauvegardes (la plus ancienne en premier), pour tester la rotation.
    mkdir("$dossier/sauvegardes", 0700, true);
    foreach ($options['anciennes'] ?? [] as $i => $nom) {
        file_put_contents("$dossier/sauvegardes/$nom", 'ancien');
        touch("$dossier/sauvegardes/$nom", time() - 86400 * (10 - $i));
    }

    $resultat = Process::env(['GAMAD_CONF' => "$dossier/deploy.conf", 'PATH' => "$faux:".getenv('PATH')])
        ->run(['bash', base_path('deploy/sauvegarde.sh')]);

    return [
        'code' => $resultat->exitCode(),
        'dossier' => $dossier,
        'journal' => file_exists("$dossier/sauvegarde.log") ? file_get_contents("$dossier/sauvegarde.log") : '',
        'sortie' => $resultat->output().$resultat->errorOutput(),
    ];
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/gamad-sauvegarde-*') ?: [] as $dossier) {
        Process::run(['rm', '-rf', $dossier]);
    }
});

it('sauvegarde la base et le .env, vérifie le fichier et journalise la réussite', function () {
    $r = lancerSauvegarde();

    expect($r['code'])->toBe(0)
        ->and($r['journal'])->toContain('Sauvegarde réussie')->not->toContain('ÉCHEC');

    $dumps = glob($r['dossier'].'/sauvegardes/gamad_essai-*.dump');
    $envs = glob($r['dossier'].'/sauvegardes/env-*');
    expect($dumps)->toHaveCount(1)
        ->and(file_get_contents($dumps[0]))->toContain('FAUX-DUMP')
        ->and($envs)->toHaveCount(1)
        ->and(file_get_contents($envs[0]))->toContain('cle-de-test')
        ->and(decoct(fileperms($dumps[0]) & 0777))->toBe('600')
        ->and(decoct(fileperms($r['dossier'].'/sauvegardes') & 0777))->toBe('700');
});

it('ne fait pas passer pour ratée une sauvegarde réussie quand seule une information de confort échoue', function () {
    $r = lancerSauvegarde(['du_echoue' => true]);

    expect($r['code'])->toBe(0)
        ->and($r['journal'])->toContain('Sauvegarde réussie')->not->toContain('ÉCHEC')
        ->and(glob($r['dossier'].'/sauvegardes/gamad_essai-*.dump'))->toHaveCount(1);
});

it('journalise un échec réel une seule fois et sort en erreur', function () {
    $r = lancerSauvegarde(['pg_dump_echoue' => true]);

    expect($r['code'])->toBe(1)
        ->and(substr_count($r['journal'], 'ÉCHEC de la sauvegarde'))->toBe(1)
        ->and($r['journal'])->not->toContain('Sauvegarde réussie');
});

it('ne conserve que les sauvegardes les plus récentes', function () {
    $r = lancerSauvegarde([
        'garder' => 2,
        'anciennes' => ['gamad_essai-2020-01-01_000000.dump', 'gamad_essai-2020-01-02_000000.dump', 'gamad_essai-2020-01-03_000000.dump'],
    ]);

    $restantes = array_map('basename', glob($r['dossier'].'/sauvegardes/gamad_essai-*.dump'));
    sort($restantes);

    expect($r['code'])->toBe(0)
        ->and($restantes)->toHaveCount(2)
        ->and($restantes)->not->toContain('gamad_essai-2020-01-01_000000.dump')
        ->and($restantes)->not->toContain('gamad_essai-2020-01-02_000000.dump')
        ->and($restantes)->toContain('gamad_essai-2020-01-03_000000.dump');
});
