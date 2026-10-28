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
            // Les CV reçus par ses opportunités : les lignes partent par cascade, sans événement du modèle.
            OpportunityResponse::deleteAttachmentsOf(OpportunityResponse::query()->whereIn('opportunity_id', $company->allOpportunities()->select('id')));
            foreach ($company->documents as $document) {
                Storage::disk('local')->delete($document->private_path);
            }
            Storage::disk('local')->delete(array_filter([$company->logo_path, $company->banner_path, ...collect($company->services ?? [])->pluck('image_path')->all()]));
        });
    }

    /**
     * Tous les fichiers du disque local qui n'existent que pour cette entreprise : pièces justificatives, logo, bannière, images de
     * services et CV reçus par ses opportunités. Sert à la fermeture d'un compte propriétaire (les lignes partent par cascade SQL,
     * sans événement du modèle).
     *
     * @return list<string>
     */
    public function storedFilePaths(): array
    {
        return array_values(array_filter([
            ...$this->documents()->pluck('private_path')->all(),
            $this->logo_path,
            $this->banner_path,
            ...collect($this->services ?? [])->pluck('image_path')->all(),
            ...OpportunityResponse::query()->whereIn('opportunity_id', $this->allOpportunities()->select('id'))->whereNotNull('attachment_path')->pluck('attachment_path')->all(),
        ]));
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
