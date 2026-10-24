<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyExperience extends Model
{
    use HasUuids;

    public const TYPES = [
        'employee' => 'Salarié',
        'former_employee' => 'Ancien salarié',
        'contractor' => 'Prestataire',
        'intern' => 'Stagiaire',
        'partner' => 'Partenaire',
    ];

    protected $guarded = ['id', 'user_id', 'company_id', 'status', 'revision', 'decided_by_id', 'decided_at'];

    protected function casts(): array
    {
        return [
            'show_on_company_page' => 'boolean',
            'decided_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function confirmed(): bool
    {
        return $this->status === 'confirmed' && $this->decided_at !== null && $this->company?->verified();
    }

    public function relationshipLabel(): string
    {
        return self::TYPES[$this->relationship] ?? 'Collaboration';
    }
}
