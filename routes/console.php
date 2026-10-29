<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reprise des effacements de fichiers interrompus (voir App\Core\Files\FilePurger). Suppose que le planificateur Laravel tourne.
Schedule::command('gamad:purger-fichiers-en-attente')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('gamad:purger-securite')->everyFiveMinutes()->withoutOverlapping();
