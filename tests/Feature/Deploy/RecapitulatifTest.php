<?php

it('affiche le compte du Président une seule fois dans le récapitulatif avant confirmation', function () {
    $r = scenarioInstall("PRESIDENT_NOM='GAMAD'; PRESIDENT_EMAIL='admin@dgafrique.com'; PHP_V=8.4; GAMAD_OUI=1; recapitulatif");

    expect($r['code'])->toBe(0)
        ->and($r['sortie'])->toContain('Compte du Président : GAMAD <admin@dgafrique.com>')
        ->and(substr_count($r['sortie'], 'admin@dgafrique.com'))->toBe(1)
        ->and($r['sortie'])->not->toContain('admin@dgafrique.comadmin');
});

it('dit « déjà créé » sans adresse vide quand le compte du Président existe déjà', function () {
    $r = scenarioInstall("PRESIDENT_NOM=''; PRESIDENT_EMAIL=''; PHP_V=8.4; GAMAD_OUI=1; recapitulatif");

    expect($r['code'])->toBe(0)
        ->and($r['sortie'])->toContain('Compte du Président : déjà créé')
        ->and($r['sortie'])->not->toContain('<>');
});

it('liste ce qui sera créé et rappelle qu’aucun autre élément du serveur n’est modifié', function () {
    $r = scenarioInstall("PRESIDENT_NOM='Essai'; PRESIDENT_EMAIL='p@exemple.org'; PHP_V=8.4; GAMAD_OUI=1; recapitulatif");

    expect($r['sortie'])->toContain('gamad-reseau')
        ->toContain('gamad_reseau')
        ->toContain('AUCUN autre site');
});
