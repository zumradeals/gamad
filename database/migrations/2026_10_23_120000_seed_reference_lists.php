<?php

use Database\Seeders\DomainSeeder;
use Database\Seeders\TerritorySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Remplit une seule fois les listes de référence réelles (17 domaines, Côte d'Ivoire et ses villes). Le déploiement
 * automatique ne lance que les migrations : c'est ce qui les met en production, sans intervention. Les seeders ne
 * font rien si les listes ne sont pas vides ; aucune donnée d'exemple n'est créée ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new DomainSeeder)->run();
        (new TerritorySeeder)->run();
    }

    public function down(): void
    {
        // Rien à défaire : la suppression des tables est faite par les migrations qui les ont créées.
    }
};
