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

    // Photos de profil : dossier absent (défaut), vide, ou rempli.
    $photos = "$dossier/app/shared/storage/app/private/profile-photos";
    if (($options['photos_dossier_vide'] ?? false) || isset($options['photos'])) {
        mkdir($photos, 0777, true);
    }
    foreach ($options['photos'] ?? [] as $nom => $contenu) {
        file_put_contents("$photos/$nom", $contenu);
    }

    // Faux « tar » : il fait le vrai travail, puis sort avec le code demandé (1 = fichier modifié pendant la lecture).
    if (isset($options['tar_code'])) {
        $vraiTar = trim((string) shell_exec('command -v tar'));
        // Seule la création de l'archive (-czf) sort avec ce code ; la relecture de contrôle (-tzf) reste normale.
        $script('tar', "case \"\$1\" in\n-czf) $vraiTar \"\$@\"; exit ".(int) $options['tar_code'].";;\n*) exec $vraiTar \"\$@\";;\nesac");
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

it('archive les photos de profil dans une archive datée, lisible par l’administrateur seul', function () {
    $r = lancerSauvegarde(['photos' => ['a.jpg' => 'photo-a', 'b.jpg' => 'photo-b']]);

    $archives = glob($r['dossier'].'/sauvegardes/photos-*.tar.gz');

    expect($r['code'])->toBe(0)
        ->and($r['journal'])->toContain('Sauvegarde réussie')->toContain('Photos archivées : photos-')->not->toContain('ÉCHEC')
        ->and($archives)->toHaveCount(1)
        ->and(decoct(fileperms($archives[0]) & 0777))->toBe('600')
        ->and(glob($r['dossier'].'/sauvegardes/gamad_essai-*.dump'))->toHaveCount(1);

    $contenu = trim((string) shell_exec('tar -tzf '.escapeshellarg($archives[0])));
    expect(explode("\n", $contenu))->toContain('profile-photos/a.jpg')->toContain('profile-photos/b.jpg');

    // Et l'archive se restaure : on retrouve les octets d'origine.
    $cible = $r['dossier'].'/restauration';
    mkdir($cible);
    shell_exec('tar -xzf '.escapeshellarg($archives[0]).' -C '.escapeshellarg($cible));
    expect(file_get_contents("$cible/profile-photos/a.jpg"))->toBe('photo-a');
});

it('ne fait pas échouer la sauvegarde quand le dossier des photos est absent', function () {
    $r = lancerSauvegarde();

    expect($r['code'])->toBe(0)
        ->and($r['journal'])->toContain('Sauvegarde réussie')->toContain('Aucune photo à archiver.')->not->toContain('ÉCHEC')
        ->and(glob($r['dossier'].'/sauvegardes/photos-*'))->toBe([])
        ->and(glob($r['dossier'].'/sauvegardes/gamad_essai-*.dump'))->toHaveCount(1);
});

it('ne fait pas échouer la sauvegarde quand le dossier des photos est vide, ou ne contient que des dossiers vides', function () {
    $vide = lancerSauvegarde(['photos_dossier_vide' => true]);
    expect($vide['code'])->toBe(0)->and($vide['journal'])->toContain('Aucune photo à archiver.')->and(glob($vide['dossier'].'/sauvegardes/photos-*'))->toBe([]);

    $imbrique = lancerSauvegarde(['photos_dossier_vide' => true]);
    mkdir($imbrique['dossier'].'/app/shared/storage/app/private/profile-photos/sous-dossier', 0777, true);
    $deuxieme = Process::env(['GAMAD_CONF' => $imbrique['dossier'].'/deploy.conf', 'PATH' => $imbrique['dossier'].'/faux:'.getenv('PATH')])->run(['bash', base_path('deploy/sauvegarde.sh')]);

    expect($deuxieme->exitCode())->toBe(0)->and(glob($imbrique['dossier'].'/sauvegardes/photos-*'))->toBe([]);
});

it('applique aux archives de photos la même rotation que pour la base', function () {
    $r = lancerSauvegarde([
        'garder' => 2,
        'photos' => ['a.jpg' => 'x'],
        'anciennes' => [
            'photos-2020-01-01_000000.tar.gz', 'photos-2020-01-02_000000.tar.gz', 'photos-2020-01-03_000000.tar.gz',
            'gamad_essai-2020-01-01_000000.dump', 'gamad_essai-2020-01-02_000000.dump', 'gamad_essai-2020-01-03_000000.dump',
        ],
    ]);

    $photos = array_map('basename', glob($r['dossier'].'/sauvegardes/photos-*.tar.gz'));
    sort($photos);

    expect($r['code'])->toBe(0)
        ->and($photos)->toHaveCount(2)
        ->and($photos)->not->toContain('photos-2020-01-01_000000.tar.gz')->not->toContain('photos-2020-01-02_000000.tar.gz')
        ->and($photos)->toContain('photos-2020-01-03_000000.tar.gz')
        ->and(glob($r['dossier'].'/sauvegardes/gamad_essai-*.dump'))->toHaveCount(2);
});

it('tolère une photo remplacée pendant la lecture (code 1 de tar), mais pas une vraie erreur', function () {
    $tolere = lancerSauvegarde(['photos' => ['a.jpg' => 'x'], 'tar_code' => 1]);
    expect($tolere['code'])->toBe(0)->and($tolere['journal'])->toContain('Photos archivées')->not->toContain('ÉCHEC')
        ->and(glob($tolere['dossier'].'/sauvegardes/photos-*.tar.gz'))->toHaveCount(1);

    $vraie = lancerSauvegarde(['photos' => ['a.jpg' => 'x'], 'tar_code' => 2]);
    expect($vraie['code'])->toBe(1)
        ->and(substr_count($vraie['journal'], 'ÉCHEC de la sauvegarde'))->toBe(1)
        ->and($vraie['journal'])->not->toContain('Sauvegarde réussie')
        ->and(glob($vraie['dossier'].'/sauvegardes/photos-*.tar.gz'))->toBe([]);
});

it('ne touche ni au dossier des photos de l’application ni à rien d’autre que ses propres sauvegardes', function () {
    $r = lancerSauvegarde(['photos' => ['a.jpg' => 'x']]);

    expect(file_get_contents($r['dossier'].'/app/shared/storage/app/private/profile-photos/a.jpg'))->toBe('x')
        ->and(file_get_contents($r['dossier'].'/app/shared/.env'))->toContain('cle-de-test');
});
