<?php

namespace App\Core\Identity;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Trace technique d'un changement de sécurité de l'identité. En ajout seul : le modèle et la base refusent toute modification. */
class IdentityEvent extends Model
{
    use HasUuids;

    public const PASSWORD_CHANGED = 'password.changed';

    public const PASSWORD_RESET = 'password.reset';

    public const SESSION_REVOKED = 'session.revoked';

    public const OTHER_SESSIONS_REVOKED = 'sessions.others_revoked';

    public const ACCOUNT_CLOSED = 'account.closed';

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw ImmutableRecordException::forIdentityEvents());
        static::deleting(fn () => throw ImmutableRecordException::forIdentityEvents());
    }

    protected function casts(): array
    {
        return ['context' => 'array', 'occurred_at' => 'datetime'];
    }

    /** @param  array<string, scalar|null>  $context  Jamais d'adresse IP, de navigateur ni de secret. */
    public static function record(string $userId, string $type, array $context = []): self
    {
        return self::query()->create(['user_id' => $userId, 'type' => $type, 'context' => $context ?: null, 'occurred_at' => now()]);
    }
}
