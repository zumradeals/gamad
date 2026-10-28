<?php

namespace App\Core\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Le mot de passe : changement par la personne connectée, et suites d'une réinitialisation par e-mail. Dans les deux cas, les
 * sessions ouvertes ailleurs sont révoquées, le jeton « se souvenir de moi » est renouvelé et le changement est tracé.
 */
class Credentials
{
    public function __construct(private readonly Sessions $sessions) {}

    /** Le mot de passe actuel est-il le bon ? (Sans effet de bord ; le limiteur de tentatives est du ressort de l'appelant.) */
    public function verify(User $user, string $password): bool
    {
        return Hash::check($password, $user->getAuthPassword());
    }

    /** @return int Nombre de sessions révoquées. */
    public function change(User $user, string $newPassword, ?string $currentSessionId): int
    {
        return DB::transaction(function () use ($user, $newPassword, $currentSessionId) {
            $this->store($user, $newPassword);
            $revoked = $this->sessions->revokeOthers($user, $currentSessionId);
            IdentityEvent::record($user->getKey(), IdentityEvent::PASSWORD_CHANGED, ['sessions_revoked' => $revoked]);

            return $revoked;
        });
    }

    /** Réinitialisation par e-mail : toutes les sessions tombent, y compris celle d'une personne déjà connectée. */
    public function reset(User $user, string $newPassword): int
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $this->store($user, $newPassword);
            $revoked = $this->sessions->revokeAll($user);
            IdentityEvent::record($user->getKey(), IdentityEvent::PASSWORD_RESET, ['sessions_revoked' => $revoked]);

            return $revoked;
        });
    }

    private function store(User $user, string $newPassword): void
    {
        // Le modèle hache le mot de passe (cast « hashed »). Le jeton « se souvenir de moi » change : un cookie volé ne rouvre rien.
        $user->forceFill(['password' => $newPassword, 'remember_token' => Str::random(60)])->save();
    }
}
