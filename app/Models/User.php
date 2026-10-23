<?php

namespace App\Models;

use App\Enums\PlatformRole;
use App\Services\Memberships;
use App\Support\Normalizer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
            $user->profile?->deletePhotoFile();

            // Fondateur d'une ZUMRA : la fondation passe à un autre membre, ou la ZUMRA disparaît s'il n'y en a pas (D-115).
            app(Memberships::class)->passFoundationFrom($user);

            // Ses messages sont effacés : le corps disparaît, la conversation affichera « Message supprimé » (D-097).
            Message::query()->where('sender_id', $user->getKey())->update(['body' => null]);
        });

        // Une conversation dont les deux personnes ont disparu n'intéresse plus personne.
        static::deleted(function () {
            Conversation::query()->whereNull('owner_id')->whereNull('helper_id')->delete();

            // Une ZUMRA sans personne est supprimée avec son dernier compte.
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
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
        ];
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
