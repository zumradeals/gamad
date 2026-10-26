<?php

namespace App\Models;

use App\Enums\AdCta;
use App\Enums\AdObjective;
use App\Enums\AdState;
use App\Support\PubliciteModule;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une campagne publicitaire et son annonce (D-209 à D-212). Le budget est en FCFA et prévisionnel : aucun paiement n'existe. L'état
 * enregistré est draft, review, refused, ready, paused ou done ; l'état affiché (displayState) y ajoute « Active », calculé.
 */
class AdCampaign extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'user_id', 'state'];

    protected function casts(): array
    {
        return [
            'remote' => 'boolean', 'budget_fcfa' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date',
            'submitted_at' => 'datetime', 'moderated_at' => 'datetime', 'stopped_at' => 'datetime',
            'objective' => AdObjective::class, 'cta' => AdCta::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'ad_campaign_skill')->orderBy('skills.name');
    }

    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'ad_campaign_domain')->orderBy('domains.sort_order')->orderBy('domains.name');
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'ad_campaign_city');
    }

    public function moderations(): HasMany
    {
        return $this->hasMany(AdModeration::class)->latest('created_at');
    }

    public function isPersonalized(): bool
    {
        return $this->audience_mode === 'personalized';
    }

    public function advertiserName(): string
    {
        return $this->company?->name ?? $this->owner?->name ?? 'Annonceur';
    }

    /** L'état affiché : « Active » si la campagne est prête, dans ses dates, et que la diffusion payante est ouverte ; « Terminée » après sa date de fin. */
    public function displayState(): AdState
    {
        $state = AdState::from($this->state);
        $today = now('Africa/Abidjan')->startOfDay();

        if (in_array($state, [AdState::Ready, AdState::Paused], true) && $this->ends_on !== null && $this->ends_on->lt($today)) {
            return AdState::Done;
        }

        if ($state === AdState::Ready && PubliciteModule::diffusionOpen() && $this->starts_on !== null && $this->ends_on !== null
            && $this->starts_on->lte($today) && $this->ends_on->gte($today)) {
            return AdState::Active;
        }

        return $state;
    }

    public function durationDays(): ?int
    {
        return $this->starts_on && $this->ends_on ? (int) $this->starts_on->diffInDays($this->ends_on) + 1 : null;
    }
}
