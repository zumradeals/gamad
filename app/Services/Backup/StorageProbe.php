<?php

namespace App\Services\Backup;

/** Essai d'écriture et de relecture sur le stockage de la copie hors serveur, à travers le chiffrement. */
interface StorageProbe
{
    /**
     * @param  string  $rcloneConfig  Contenu d'un fichier de configuration rclone (remotes « gamad-s3 » et « gamad-copie »).
     * @return string|null Null si l'essai a réussi, sinon le motif du refus (sans aucune clé).
     */
    public function verify(string $rcloneConfig): ?string;
}
