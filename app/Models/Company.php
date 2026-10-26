<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasUuids;

    public const VERIFIED = 'verified';

    public const MAX_SERVICES = 6;

    protected $guarded = ['id', 'owner_id', 'status', 'reviewer_id', 'verified_at', 'revision'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'revision' => 'integer', 'services' => 'array'];
    }

    protected static function booted(): void
    {
        // Le nom de l'entreprise fait partie du texte de recherche de ses opportunités.
        static::saved(function (Company $company) {
            if ($company->wasChanged(['name', 'sector'])) {
                $company->allOpportunities()->get()->each(fn (Opportunity $opportunity) => $opportunity->setRelation('company', $company)->save());
            }
        });
        static::deleting(function (Company $company) {
            foreach ($company->documents as $document) {
                Storage::disk('local')->delete($document->private_path);
            }
            Storage::disk('local')->delete(array_filter([$company->logo_path, $company->banner_path, ...collect($company->services ?? [])->pluck('image_path')->all()]));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documents()
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /** Opportunités ouvertes publiées par l'entreprise (module Opportunités) : une seule source pour sa page et le catalogue. */
    public function opportunities()
    {
        return $this->hasMany(Opportunity::class)->open();
    }

    /** Toutes les opportunités de l'entreprise, brouillons et clôturées comprises : réservé à sa gestion. */
    public function allOpportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function verified(): bool
    {
        return $this->status === self::VERIFIED && $this->verified_at !== null;
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('status', self::VERIFIED)->whereNotNull('verified_at');
    }
}
