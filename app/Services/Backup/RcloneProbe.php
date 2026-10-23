<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/** Essai réel avec rclone : un petit fichier est écrit, relu puis effacé à travers le chiffrement. */
class RcloneProbe implements StorageProbe
{
    public function verify(string $rcloneConfig): ?string
    {
        $dir = sys_get_temp_dir().'/gamad-sonde-'.Str::random(12);
        mkdir($dir, 0700);
        $conf = "$dir/rclone.conf";
        file_put_contents($conf, $rcloneConfig);
        chmod($conf, 0600);

        try {
            $options = ['rclone', '--config', $conf, '--retries', '1', '--low-level-retries', '2', '--contimeout', '10s', '--timeout', '30s'];
            $temoin = 'gamad-essai-'.Str::random(16);
            $cible = 'gamad-copie:essai/connexion.txt';

            $ecriture = Process::input($temoin)->timeout(90)->run([...$options, 'rcat', $cible]);
            if ($ecriture->failed()) {
                return $this->motif($ecriture->errorOutput(), $ecriture->exitCode());
            }

            $lecture = Process::timeout(60)->run([...$options, 'cat', $cible]);
            Process::timeout(60)->run([...$options, 'deletefile', $cible]);

            return $lecture->successful() && $lecture->output() === $temoin
                ? null
                : 'Le fichier d’essai a été écrit mais sa relecture a échoué.';
        } finally {
            @unlink($conf);
            @rmdir($dir);
        }
    }

    /** Dernière ligne utile de la réponse de rclone ; elle ne contient jamais les clés. */
    private function motif(string $erreur, ?int $code): string
    {
        $lignes = array_values(array_filter(array_map('trim', explode("\n", $erreur))));
        $derniere = $lignes === [] ? 'aucun détail' : Str::limit((string) end($lignes), 200);

        return $code === 127 || str_contains($erreur, 'not found')
            ? 'rclone n’est pas installé sur ce serveur.'
            : "Le stockage a refusé l’essai ($derniere).";
    }
}
