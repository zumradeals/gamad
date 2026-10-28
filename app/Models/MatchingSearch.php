<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Une recherche de correspondances : son état réel (en attente, en cours, terminée, échouée, annulée), visible de la personne concernée. */
class MatchingSearch extends Model
{
    use HasUuids;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cursor' => 'array', 'rerun' => 'boolean', 'requested_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'heartbeat_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return in_array($this->state, [self::QUEUED, self::RUNNING], true);
    }

    /**
     * L'état à afficher, fidèle à la réalité : « en cours » seulement si la tranche a donné signe de vie récemment ; une recherche
     * qui a échoué puis attend sa reprise est « en reprise » ; un échec définitif est « indisponible », jamais « aucune piste ».
     */
    public function status(): string
    {
        return match (true) {
            $this->state === self::DONE => 'done',
            $this->state === self::FAILED => 'unavailable',
            $this->state === self::CANCELLED => 'cancelled',
            $this->state === self::RUNNING && $this->heartbeat_at?->gt(now()->subMinutes(3)) => 'running',
            $this->attempts > 0 => 'retrying',
            default => 'queued',
        };
    }
}
