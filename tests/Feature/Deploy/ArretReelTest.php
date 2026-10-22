<?php

/*
 * Règle des scripts de deploy/ : un message d'arrêt ne s'affiche JAMAIS sans arrêt réel du script, y compris quand
 * l'erreur survient dans une substitution de commande $(...) ou un sous-shell (incident de l'installation réelle du
 * 22 octobre 2026 : « L'installation s'est arrêtée » s'était affiché, puis l'installation avait continué).
 */

const MESSAGE_ARRET = 'L’installation s’est arrêtée pendant l’étape';
const MESSAGE_ARRET_ASCII = "L'installation s'est arrêtée pendant l'étape";

it('s’arrête vraiment quand une affectation par substitution échoue', function () {
    $r = scenarioInstall('x="$(false)"; echo APRES');

    expect($r['code'])->not->toBe(0)
        ->and($r['sortie'])->toContain(MESSAGE_ARRET_ASCII)
        ->and($r['sortie'])->toContain('Étape d essai')
        ->and($r['sortie'])->not->toContain('APRES');
});

it('n’affiche aucun message d’arrêt quand l’échec d’une substitution est sans conséquence, et continue vraiment', function () {
    $r = scenarioInstall('ok "valeur : $(false)"; echo APRES');

    expect($r['code'])->toBe(0)
        ->and($r['sortie'])->not->toContain(MESSAGE_ARRET_ASCII)
        ->and($r['sortie'])->toContain('APRES');
});

it('arrête le script principal quand arreter() est appelée depuis une substitution de commande', function () {
    $r = scenarioInstall('x="$(arreter "Panne volontaire")"; echo APRES');

    expect($r['code'])->not->toBe(0)
        ->and($r['sortie'])->toContain('Panne volontaire')
        ->and($r['sortie'])->not->toContain('APRES');
});

it('arrête le script principal même quand arreter() est appelée dans un argument, dont le code de sortie est ignoré', function () {
    $r = scenarioInstall('echo "texte $(arreter "Panne dans un argument")" >/dev/null; echo APRES');

    expect($r['code'])->not->toBe(0)
        ->and($r['sortie'])->toContain('Panne dans un argument')
        ->and($r['sortie'])->not->toContain('APRES');
});

it('arrête le script principal quand arreter() est appelée depuis un sous-shell', function () {
    $r = scenarioInstall('( arreter "Panne dans un sous-shell" ); echo APRES');

    expect($r['code'])->not->toBe(0)
        ->and($r['sortie'])->toContain('Panne dans un sous-shell')
        ->and($r['sortie'])->not->toContain('APRES');
});

it('s’arrête vraiment quand un sous-shell ou un maillon de tube échoue', function (string $scenario) {
    $r = scenarioInstall($scenario.'; echo APRES');

    expect($r['code'])->not->toBe(0)
        ->and($r['sortie'])->toContain(MESSAGE_ARRET_ASCII)
        ->and($r['sortie'])->not->toContain('APRES');
})->with([
    'sous-shell' => '( false )',
    'tube' => 'false | cat',
    'commande simple' => 'false',
]);

it('ne dit « arrêtée » que si le script s’est réellement arrêté', function (string $scenario) {
    $r = scenarioInstall($scenario);
    // « ✘ » ouvre tout message d'arrêt (celui du piège d'erreur comme celui de arreter()).
    $messageAffiche = str_contains($r['sortie'], '✘');
    $suiteExecutee = str_contains($r['sortie'], 'APRES');

    // Invariant : message affiché  =>  code de sortie non nul ET suite non exécutée.
    if ($messageAffiche) {
        expect($r['code'])->not->toBe(0)->and($suiteExecutee)->toBeFalse();
    } else {
        expect($r['code'])->toBe(0)->and($suiteExecutee)->toBeTrue();
    }
})->with([
    'affectation qui échoue' => 'x="$(false)"; echo APRES',
    'message avec substitution qui échoue' => 'ok "$(false)"; echo APRES',
    'argument de commande avec substitution qui échoue' => 'echo "$(false)" >/dev/null; echo APRES',
    'arreter dans un argument' => 'echo "$(arreter "Panne")" >/dev/null; echo APRES',
    'arreter dans une affectation' => 'x="$(arreter "Panne")"; echo APRES',
    'sous-shell qui échoue' => '( false ); echo APRES',
    'sous-shell qui réussit' => '( true ); echo APRES',
    'tube qui échoue' => 'false | cat; echo APRES',
    'condition qui échoue' => 'if false; then :; fi; echo APRES',
    'ou-logique' => 'false || true; echo APRES',
    'substitution tolérée explicitement' => 'x="$(false || true)"; echo APRES',
]);

it('lit la version du code sous le compte dédié, jamais en administrateur', function () {
    $install = file_get_contents(base_path('deploy/install.sh'));

    preg_match_all('/^.*git -C "\$APP_DIR\/repo".*$/m', $install, $lignes);

    expect($lignes[0])->not->toBeEmpty();
    foreach ($lignes[0] as $ligne) {
        expect($ligne)->toContain('runuser -u "$APP_USER" --');
    }
});
