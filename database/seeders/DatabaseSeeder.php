<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Aucune donnée n'est créée en production. Les données d'exemple vivent dans DevelopmentSeeder,
     * appelé uniquement en environnement local.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
