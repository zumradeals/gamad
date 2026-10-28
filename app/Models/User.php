<?php

namespace App\Models;

use App\Core\Identity\AccountCloser;
use App\Enums\PlatformRole;
use App\Support\Normalizer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected static function booted(): void
    {
        // Clé de recherche par nom, sans casse ni accents (Carrefour Personnes).
        static::saving(fn (User $user) => $user->name_search = Normalizer::key($user->name));

        // Le nom contrôlé ne doit jamais redevenir vérifié après un changement puis un retour à l’ancien nom.
        static::updating(function (User $user) {
            if ($user->isDirty('name')) {
                IdentityVerification::where('user_id', $user->id)->whereIn('state', ['pending', 'verified'])
                    ->update(['state' => 'revoked', 'revision' => DB::raw('revision + 1'), 'reason' => 'Nom affiché modifié : un nouveau contrôle est nécessaire.', 'updated_at' => now()]);
            }
        });

        // Chaque personne a un profil dès son inscription.
        static::created(fn (User $user) => $user->profile()->create());

        // Un compte ne se supprime que par AccountCloser::close() : contrôles préalables, transfert de fondation, fichiers, journal.
        // Une suppression directe contournerait ces garanties (D-172 : aucun compte ne disparaît avec des ZAHAB).
        static::deleting(function (User $user) {
            if (! app(AccountCloser::class)->isClosing($user)) {
                throw new LogicException('Un compte se ferme par AccountCloser::close(), jamais par delete().');
            }
        });
    }

    public function identityVerification(): HasOne
    {
        return $this->hasOne(IdentityVerification::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'author_id');
    }

    public function gestures(): HasMany
    {
        return $this->hasMany(Gesture::class);
    }

    /**
     * Identifiants des ZUMRA que cette personne gère (fondatrice ou responsable). Toujours lu en base : aucune valeur gardée en
     * mémoire, pour qu'un changement de rôle soit pris en compte tout de suite.
     *
     * @return array<int, string>
     */
    public function managedZumraIds(): array
    {
        return $this->zumraMemberships()->whereIn('role', ['founder', 'manager'])->pluck('zumra_id')->all();
    }

    public function zumraMemberships(): HasMany
    {
        return $this->hasMany(ZumraMembership::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** Profil de la personne, créé s'il manquait (comptes antérieurs au Lot 1). */
    public function ensureProfile(): Profile
    {
        return $this->profile ?? $this->profile()->create();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'charter_accepted_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
        ];
    }

    public function suspender(): BelongsTo
    {
        return $this->belongsTo(self::class, 'suspended_by');
    }

    public function moderationNotices(): HasMany
    {
        return $this->hasMany(ModerationNotice::class);
    }

    /** L'avis de la modération en cours pour cette personne, s'il y en a un. */
    public function openNotice(): ?ModerationNotice
    {
        return $this->moderationNotices()->open()->first();
    }

    /** Personne suspendue (D-145) : elle ne peut plus rien faire et n'est plus visible des autres. */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Personne dont l'adresse e-mail est vérifiée et qui n'est pas suspendue : seule celle-là existe pour les autres. */
    public function isActive(): bool
    {
        return $this->hasVerifiedEmail() && ! $this->isSuspended();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at')->whereNull('suspended_at');
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === PlatformRole::SuperAdmin;
    }

    public function isModerator(): bool
    {
        return $this->platform_role === PlatformRole::Moderator;
    }

    /** Une ou deux lettres pour l'avatar : « Awa Traoré » donne « AT ». */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters);
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1));
    }
}
