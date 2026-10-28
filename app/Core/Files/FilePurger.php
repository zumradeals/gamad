<?php

namespace App\Core\Files;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Effacement de fichiers coordonné avec la base : `enqueue()` s'appelle DANS la transaction qui supprime les lignes, `drain()` APRÈS
 * sa validation. Une transaction annulée n'efface donc aucun fichier, et un arrêt entre la validation et l'effacement laisse une ligne
 * `file_purges` que la commande `gamad:purger-fichiers-en-attente` (ou la fermeture de compte suivante) reprend. Idempotent.
 */
class FilePurger
{
    /** Au-delà, la ligne reste visible (échec durable à examiner) mais n'est plus retentée automatiquement. */
    public const MAX_ATTEMPTS = 10;

    /**
     * @param  iterable<array{0: string, 1: string}>  $files  Couples [disque, chemin].
     * @return list<string> Identifiants des lignes concernées.
     */
    public function enqueue(iterable $files): array
    {
        $ids = [];

        foreach ($files as [$disk, $path]) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            $this->assertSafe($path);

            // Un même fichier demandé deux fois ne produit qu'une ligne.
            $row = FilePurge::query()->firstOrCreate(['disk' => $disk, 'path' => $path]);
            $ids[] = $row->getKey();
        }

        return array_values(array_unique($ids));
    }

    /**
     * Efface les fichiers demandés (ou, sans argument, ceux en attente). Un échec est noté et retenté plus tard, jamais bloquant.
     *
     * @param  list<string>|null  $ids
     * @return int Nombre de fichiers effacés.
     */
    public function drain(?array $ids = null, int $limit = 200): int
    {
        $query = FilePurge::query()->where('attempts', '<', self::MAX_ATTEMPTS)->orderBy('created_at')->limit($limit);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        $done = 0;
        foreach ($query->get() as $purge) {
            try {
                // Flysystem ne considère pas l'absence du fichier comme une erreur : un fichier déjà parti est un succès.
                if (! Storage::disk($purge->disk)->delete($purge->path)) {
                    throw new \RuntimeException('Le disque a refusé l’effacement.');
                }
                $purge->delete();
                $done++;
            } catch (Throwable $error) {
                report($error);
                $purge->forceFill(['attempts' => $purge->attempts + 1, 'last_error' => mb_substr($error->getMessage(), 0, 500), 'last_attempt_at' => now()])->save();
            }
        }

        return $done;
    }

    /** Fichiers en attente : (à reprendre, abandonnés après trop d'échecs). Source des mesures de la future Console. */
    public function pending(): array
    {
        return [
            'retryable' => FilePurge::query()->where('attempts', '<', self::MAX_ATTEMPTS)->count(),
            'failed' => FilePurge::query()->where('attempts', '>=', self::MAX_ATTEMPTS)->count(),
        ];
    }

    private function assertSafe(string $path): void
    {
        if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Chemin de fichier refusé.');
        }
    }
}
