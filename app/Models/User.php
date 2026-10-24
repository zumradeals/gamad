<?php

namespace App\Models;

use App\Enums\PlatformRole;
use App\Services\Memberships;
use App\Services\Wallets;
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

        // Chaque personne a un profil dès son inscription.
        static::created(fn (User $user) => $user->profile()->create());

        // Aucune photo ne survit au compte (données personnelles).
        static::deleting(function (User $user) {
            // Filet de sécurité : aucun compte ne disparaît avec des ZAHAB dedans (D-172). Le formulaire le vérifie avant, avec un message.
            app(Wallets::class)->assertAccountCanBeDeleted($user);

            $user->profile?->deletePhotoFile();
            Publication::query()->where('author_id', $user->id)->whereNotNull('media_path')->each(fn (Publication $post) => $post->deleteMediaFile());

            // Fondateur d'une ZUMRA : la fondation passe à un autre membre, ou la ZUMRA disparaît s'il n'y en a pas (D-115).
            app(Memberships::class)->passFoundationFrom($user);

            // La prochaine action privée ne survit pas au départ d'un de ses deux participants (D-184).
            Conversation::query()->where(fn ($q) => $q->where('owner_id', $user->getKey())->orWhere('helper_id', $user->getKey()))
                ->update(['next_action' => null, 'next_action_date' => null, 'next_action_by' => null, 'next_action_confirmed_at' => null]);

            // Ses messages sont effacés : le corps disparaît, la conversation affichera « Message supprimé » (D-097).
            Message::query()->where('sender_id', $user->getKey())->update(['body' => null]);
        });

        // Une conversation dont les deux personnes ont disparu n'intéresse plus personne.
        static::deleted(function (User $user) {
            Conversation::query()->whereNull('owner_id')->whereNull('helper_id')->delete();

            // Les portefeuilles du titulaire disparu sont fermés ; leur registre reste, en ajout seul.
            $wallets = app(Wallets::class);
            $wallets->closeFor(Wallet::PERSON, $user->getKey());

            // Une ZUMRA sans personne est supprimée avec son dernier compte.
            foreach (Zumra::query()->withoutMembers()->pluck('id') as $zumraId) {
                $wallets->closeFor(Wallet::ZUMRA, $zumraId);
            }
            Zumra::query()->withoutMembers()->delete();
        });
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
