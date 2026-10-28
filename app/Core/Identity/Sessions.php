<?php

namespace App\Core\Identity;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Les sessions ouvertes d'une personne, lues et révoquées dans la table des sessions de Laravel (pilote « database », celui de la
 * production). Une personne ne voit et ne révoque que les siennes ; elle ne peut pas révoquer la session qu'elle utilise (la
 * déconnexion existe pour cela). Aucun identifiant de session ne sort de ce service : l'interface manipule un identifiant opaque.
 */
class Sessions
{
    /** Sans le pilote « database », rien n'est listable ni révocable : l'écran le dit au lieu de faire semblant. */
    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return Collection<int, SessionInfo> Les plus récentes d'abord ; seulement les sessions encore valides. */
    public function for(User $user, ?string $currentSessionId): Collection
    {
        if (! $this->supported()) {
            return collect();
        }

        return $this->alive($user)->orderByDesc('last_activity')->get()
            ->map(fn ($row) => new SessionInfo(
                handle: $this->handle($row->id),
                browser: $this->browser((string) $row->user_agent),
                platform: $this->platform((string) $row->user_agent),
                ip: $row->ip_address,
                lastActivity: CarbonImmutable::createFromTimestamp($row->last_activity),
                current: $row->id === $currentSessionId,
            ))->values();
    }

    /** Révoque une session de la personne. Faux si elle n'existe pas, n'est pas à elle ou est la session courante. */
    public function revoke(User $user, string $handle, ?string $currentSessionId): bool
    {
        if (! $this->supported()) {
            return false;
        }

        $id = $this->alive($user)->pluck('id')->first(fn (string $id) => hash_equals($this->handle($id), $handle));

        if ($id === null || $id === $currentSessionId) {
            return false;
        }

        $deleted = $this->table()->where('user_id', $user->getKey())->where('id', $id)->delete() > 0;
        if ($deleted) {
            IdentityEvent::record($user->getKey(), IdentityEvent::SESSION_REVOKED);
        }

        return $deleted;
    }

    /** Révoque toutes les sessions de la personne sauf la courante. Retourne leur nombre. */
    public function revokeOthers(User $user, ?string $currentSessionId): int
    {
        if (! $this->supported()) {
            return 0;
        }

        $query = $this->table()->where('user_id', $user->getKey());
        if ($currentSessionId !== null) {
            $query->where('id', '!=', $currentSessionId);
        }
        $count = $query->delete();

        if ($count > 0) {
            IdentityEvent::record($user->getKey(), IdentityEvent::OTHER_SESSIONS_REVOKED, ['sessions' => $count]);
        }

        return $count;
    }

    /** Révoque toutes les sessions, y compris la courante (réinitialisation du mot de passe). Sans trace propre : l'appelant l'inscrit. */
    public function revokeAll(User $user): int
    {
        return $this->supported() ? $this->table()->where('user_id', $user->getKey())->delete() : 0;
    }

    private function table()
    {
        return DB::table(config('session.table', 'sessions'));
    }

    private function alive(User $user)
    {
        return $this->table()->where('user_id', $user->getKey())->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp());
    }

    private function handle(string $sessionId): string
    {
        return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 24);
    }

    private function browser(string $agent): string
    {
        return match (true) {
            $agent === '' => 'Navigateur inconnu',
            Str::contains($agent, ['Edg/', 'EdgA/']) => 'Edge',
            Str::contains($agent, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($agent, ['SamsungBrowser']) => 'Samsung Internet',
            Str::contains($agent, ['Firefox/', 'FxiOS']) => 'Firefox',
            Str::contains($agent, ['Chrome/', 'CriOS']) => 'Chrome',
            Str::contains($agent, 'Safari/') => 'Safari',
            default => 'Navigateur inconnu',
        };
    }

    private function platform(string $agent): string
    {
        return match (true) {
            Str::contains($agent, 'Android') => 'Android',
            Str::contains($agent, ['iPhone', 'iPad', 'iPod']) => 'iOS',
            Str::contains($agent, 'Windows') => 'Windows',
            Str::contains($agent, ['Macintosh', 'Mac OS']) => 'macOS',
            Str::contains($agent, 'Linux') => 'Linux',
            default => 'Appareil inconnu',
        };
    }
}
