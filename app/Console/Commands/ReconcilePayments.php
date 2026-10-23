<?php

namespace App\Console\Commands;

use App\Services\Payments;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'gamad:rapprocher-paiements';

    protected $description = 'Demande à GeniusPay l’état des recharges en attente et crédite celles qu’il confirme';

    public function handle(Payments $payments): int
    {
        $this->info($payments->reconcilePending().' paiement(s) mis à jour.');

        return self::SUCCESS;
    }
}
