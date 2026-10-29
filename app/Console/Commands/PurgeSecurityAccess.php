<?php

namespace App\Console\Commands;

use App\Core\Identity\SecurityRetention;
use Illuminate\Console\Command;

class PurgeSecurityAccess extends Command
{
    protected $signature = 'gamad:purger-securite {--batch=2000}';

    protected $description = 'Efface les traces de sécurité expirées, par lots bornés et reprenables';

    public function handle(SecurityRetention $retention): int
    {
        $result = $retention->purge((int) $this->option('batch'));
        $this->info($result['details'].' détail(s), '.$result['hours'].' heure(s) effacés. Relancer si le lot est plein.');

        return self::SUCCESS;
    }
}
