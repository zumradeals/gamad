<?php

namespace App\Console\Commands;

use App\Services\BackupStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Appelée chaque nuit par deploy/copie-hors-serveur.sh (en tant que compte de l'application) : écrit sur la sortie standard la
 * configuration rclone construite depuis Espace → Paramètres → Copie hors serveur. La sortie contient des clés : le script la range
 * dans un fichier temporaire à droits restreints, supprimé à la fin, et elle n'est jamais journalisée.
 * Code de sortie 2 : la copie n'est pas configurée (ce n'est pas une panne).
 */
#[Signature('gamad:copie-configuration')]
#[Description('Écrit la configuration rclone de la copie hors serveur (contient des clés : à ne pas journaliser)')]
class BackupConfiguration extends Command
{
    public const NOT_CONFIGURED = 2;

    public function handle(BackupStorage $storage): int
    {
        if (! $storage->configured()) {
            // Sur la sortie d'erreur : la sortie standard est réservée à la configuration.
            $this->output->getErrorStyle()->writeln('La copie hors serveur n’est pas configurée (Espace → Paramètres → Copie hors serveur).');

            return self::NOT_CONFIGURED;
        }

        $this->output->write($storage->rcloneConfig());

        return self::SUCCESS;
    }
}
