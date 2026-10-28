<?php

// Frontières vérifiées par le code : le Core ne connaît pas les domaines du Social, le modèle d'identité ne pilote plus aucun domaine.

arch('le modèle User ne dépend d’aucun service métier ni du stockage de fichiers')
    ->expect('App\Models\User')
    ->not->toUse(['App\Services', 'Illuminate\Support\Facades\Storage']);

arch('le Core ne dépend ni des services, ni de l’interface, ni des modèles du Social (hors User)')
    ->expect('App\Core')
    ->not->toUse(['App\Services', 'App\Livewire', 'App\Http', 'App\Support', 'App\Policies', 'App\Models'])
    ->ignoring('App\Models\User');

arch('les participants à la fermeture implémentent l’interface du Core')
    ->expect('App\Services\AccountClosure')
    ->toImplement('App\Core\Identity\Closure\AccountClosureParticipant');
