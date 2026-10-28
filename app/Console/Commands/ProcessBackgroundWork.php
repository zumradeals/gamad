<?php

namespace App\Console\Commands;

use App\Core\Execution\BackgroundRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gamad:traiter {--max=100 : Nombre maximal de tâches} {--secondes=50 : Durée maximale}')]
#[Description('Traite les tâches en attente du Core (mêmes limites et même file que le traitement après réponse)')]
class ProcessBackgroundWork extends Command
{
    public function handle(BackgroundRunner $runner): int
    {
        $done = $runner->run(max(1, (int) $this->option('max')), max(1, (int) $this->option('secondes')));
        $this->info("$done tâche(s) traitée(s).");

        return self::SUCCESS;
    }
}
