<?php

namespace App\Core\Identity;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Current aggregate state of Identity. No account, session payload or event context leaves this observation. */
class IdentityObservations
{
    public function snapshot(int $hours): array
    {
        $hours = in_array($hours, [1, 24, 168], true) ? $hours : 24;
        $observed = now()->toIso8601String();
        $accounts = DB::table('users')->selectRaw('count(*) as total,
            count(*) filter (where email_verified_at is not null and suspended_at is null) as active,
            count(*) filter (where email_verified_at is null and suspended_at is null) as pending,
            count(*) filter (where suspended_at is not null) as suspended,
            count(*) filter (where email_verified_at is not null) as verified,
            count(*) filter (where email_verified_at is null) as unverified,
            max(updated_at) as latest_change')->first();
        $types = [
            IdentityEvent::PASSWORD_CHANGED => 'Mots de passe modifiés',
            IdentityEvent::PASSWORD_RESET => 'Mots de passe réinitialisés',
            IdentityEvent::SESSION_REVOKED => 'Sessions révoquées individuellement',
            IdentityEvent::OTHER_SESSIONS_REVOKED => 'Révocations des autres sessions',
            IdentityEvent::ACCOUNT_CLOSED => 'Comptes fermés',
        ];
        $rows = DB::table('identity_events')->where('occurred_at', '>=', now()->subHours($hours))
            ->groupBy('type')->selectRaw('type, count(*) as total, max(occurred_at) as latest')->get()->keyBy('type');
        $events = [];
        foreach (array_unique(array_merge(array_keys($types), $rows->keys()->all())) as $type) {
            $row = $rows->get($type);
            $events[] = ['type' => $type, 'label' => $types[$type] ?? $type, 'total' => (int) ($row?->total ?? 0), 'latest' => $row?->latest];
        }
        try {
            $count = app(Sessions::class)->authenticatedCount();
            $sessions = ['count' => $count, 'state' => $count === null ? 'non disponible' : 'mesuré',
                'reason' => $count === null ? 'Le pilote de sessions n’est pas database ; aucune quantité ne peut être déduite.' : 'Sessions non expirées rattachées à un compte non suspendu ; une adresse e-mail peut rester non vérifiée. Ce nombre ne prouve pas une présence en ligne.'];
        } catch (Throwable) {
            $sessions = ['count' => null, 'state' => 'inconnu', 'reason' => 'La source des sessions n’a pas pu être lue.'];
        }

        return [
            'accounts' => ['total' => (int) $accounts->total, 'active' => (int) $accounts->active, 'pending' => (int) $accounts->pending,
                'suspended' => (int) $accounts->suspended, 'verified' => (int) $accounts->verified, 'unverified' => (int) $accounts->unverified,
                'latest_change' => $accounts->latest_change, 'source' => 'users · email_verified_at, suspended_at et updated_at', 'observed_at' => $observed],
            'events' => $events, 'events_latest' => DB::table('identity_events')->max('occurred_at'), 'events_source' => 'identity_events · type et occurred_at ; nombre d’événements, pas nombre de sessions dans une révocation groupée',
            'sessions' => $sessions + ['source' => 'Sessions · pilote '.config('session.driver').' · table '.config('session.table', 'sessions').' · expiration '.config('session.lifetime').' min', 'observed_at' => $observed],
            'observed_at' => $observed,
        ];
    }
}
