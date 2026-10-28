<?php

namespace App\Console\Commands;

use App\Core\Files\FilePurger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gamad:purger-fichiers-en-attente')]
#[Description('Efface les fichiers devenus inutiles après la validation d’une transaction (reprise des effacements interrompus)')]
class DrainFilePurges extends Command
{
    public function handle(FilePurger $purger): int
    {
        $done = $purger->drain();
        $left = $purger->pending();

        $this->info("$done fichier(s) effacé(s). En attente : {$left['retryable']}, abandonnés après échecs répétés : {$left['failed']}.");

        return $left['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
