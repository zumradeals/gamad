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

arch('l’exécution du Core ne dépend ni du Social ni de ses événements ni de ses tâches')
    ->expect('App\Core\Execution')
    ->not->toUse(['App\Services', 'App\Livewire', 'App\Models', 'App\Jobs', 'App\Events', 'App\Listeners']);

arch('les tâches du Matching passent par l’exécution du Core et par les services du Social, jamais par les modèles de la file')
    ->expect('App\\Jobs\\Matching')
    ->toImplement('Illuminate\\Contracts\\Queue\\ShouldQueue');
