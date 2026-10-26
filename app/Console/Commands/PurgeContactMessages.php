<?php

namespace App\Console\Commands;

use App\Services\ContactMessages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Efface les messages de contact plus anciens que la durée de conservation (D-208). */
#[Signature('gamad:purger-contacts')]
#[Description('Efface les messages de contact plus anciens que la durée de conservation')]
class PurgeContactMessages extends Command
{
    public function handle(ContactMessages $messages): int
    {
        $this->info($messages->purgeExpired().' message(s) de contact effacé(s).');

        return self::SUCCESS;
    }
}
