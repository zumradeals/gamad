<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un message envoyé par le formulaire de contact public (D-208). Lu par le seul super administrateur, jamais exposé aux autres. */
class ContactMessage extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const SUBJECTS = [
        'general' => 'Question générale',
        'account' => 'Problème de compte',
        'problem' => 'Signaler un problème',
        'partnership' => 'Partenariat',
        'other' => 'Autre',
    ];

    public const NEW = 'new';

    public const READ = 'read';

    public const HANDLED = 'handled';

    public const STATES = [self::NEW => 'Nouveau', self::READ => 'Lu', self::HANDLED => 'Traité'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'handled_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectLabel(): string
    {
        return self::SUBJECTS[$this->subject] ?? self::SUBJECTS['other'];
    }
}
