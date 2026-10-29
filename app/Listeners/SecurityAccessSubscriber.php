<?php

namespace App\Listeners;

use App\Core\Identity\SecurityAccessEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Throwable;

/** Only authentication events, never web navigation or request/form dumps. */
class SecurityAccessSubscriber
{
    public function login(Login $event): void
    {
        if ($event->user instanceof User && $event->guard === 'web') {
            $this->record(request(), $event->user->isSuspended() ? 'refused' : 'success', $event->user, $event->user);
        }
    }

    public function failed(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }
        // Never inspect or serialize credentials: the event may carry a password.
        $this->record(request(), 'failure', $event->user instanceof User ? $event->user : null);
    }

    public function lockout(Lockout $event): void
    {
        $this->record($event->request, 'limited');
    }

    private function record(Request $request, string $outcome, ?User $target = null, ?User $authenticated = null): void
    {
        if ($request->attributes->get('gamad.security.recorded')) {
            return;
        }
        $request->attributes->set('gamad.security.recorded', true);

        try {
            $login = $request->input(Fortify::username());
            $login = is_string($login) ? mb_strtolower(mb_substr($login, 0, 320)) : '';
            // Same canonical lowercase lookup as Fortify. Unknown login addresses are never persisted.
            $target ??= $login === '' ? null : User::query()->where('email', $login)->first();
            $login = $login === '' && $target !== null ? mb_strtolower($target->email) : $login;
            $ip = $request->ip();
            $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : null;
            $key = (string) config('app.key');
            if ($key === '') {
                throw new \RuntimeException('Security encryption key unavailable');
            }
            DB::transaction(function () use ($request, $outcome, $target, $authenticated, $login, $ip, $key) {
                $occurredAt = now();
                SecurityAccessEvent::query()->create([
                    'occurred_at' => $occurredAt, 'outcome' => $outcome,
                    'source_key' => $ip === null ? null : hash_hmac('sha256', 'security-source:'.$ip, $key),
                    'target_key' => $login === '' ? null : hash_hmac('sha256', 'security-target:'.$login, $key),
                    'authenticated_id' => $authenticated?->getKey(), 'target_id' => $target?->getKey(),
                    'sensitive_target' => $target !== null && ($target->isSuperAdmin() || $target->isModerator()),
                    'ip' => $ip, 'user_agent' => $request->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 512),
                ]);
                DB::statement("insert into security_access_hours (hour, {$outcome}) values (?, 1) on conflict (hour) do update set {$outcome} = security_access_hours.{$outcome} + 1", [$occurredAt->copy()->startOfHour()]);
            });
            cache()->forget('security:recording-error');
        } catch (Throwable) {
            // Do not report the exception (SQL bindings could expose identifying data). Do not break authentication.
            cache()->put('security:recording-error', now()->toIso8601String(), now()->addDay());
        }
    }
}
