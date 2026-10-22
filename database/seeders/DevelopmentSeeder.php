<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Données d'exemple pour le développement local uniquement. Ne jamais appeler en production.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeeder ne doit jamais être exécuté en production.');
        }

        User::factory()->create([
            'name' => 'Personne de développement',
            'email' => 'dev@gamad.test',
            'password' => 'motdepasse-dev',
        ]);
    }
}
