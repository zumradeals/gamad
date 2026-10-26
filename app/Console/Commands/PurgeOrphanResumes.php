<?php

namespace App\Console\Commands;

use App\Models\OpportunityResponse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Repère les PDF de candidature que plus aucune réponse ne référence (comptes ou entreprises supprimés avant la correction C2).
 * Par défaut, simple liste : rien n'est effacé sans --purger. Un fichier encore référencé n'est jamais touché, et un fichier
 * trop récent est ignoré (un envoi en cours écrit le fichier avant la ligne).
 */
#[Signature('gamad:purger-cv-orphelins {--purger : Efface réellement les fichiers orphelins (sinon, simple liste)} {--age=60 : Âge minimal en minutes}')]
#[Description('Liste (ou efface avec --purger) les PDF de candidature sans réponse associée')]
class PurgeOrphanResumes extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $limit = now()->subMinutes(max(0, (int) $this->option('age')))->getTimestamp();
        $referenced = OpportunityResponse::query()->whereNotNull('attachment_path')->pluck('attachment_path')->flip();

        $orphans = collect($disk->files('opportunity-responses'))
            ->reject(fn (string $path) => $referenced->has($path) || $disk->lastModified($path) > $limit)
            ->values();

        $purge = (bool) $this->option('purger');
        foreach ($orphans as $path) {
            $this->line(($purge ? 'Effacé : ' : 'Orphelin : ').$path);
        }
        if ($purge) {
            $disk->delete($orphans->all());
        }

        $this->info($orphans->count().($purge ? ' fichier(s) effacé(s).' : ' fichier(s) orphelin(s). Relancez avec --purger pour les effacer.'));

        return self::SUCCESS;
    }
}
