<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        // La confirmation de mot de passe n'a pas d'écran au lot 0 : la route interne de Fortify répond 404.
        Fortify::confirmPasswordView(fn () => abort(404));

        // Inscriptions par heure et par adresse IP (20 par défaut : beaucoup d'utilisateurs mobiles partagent une même
        // adresse IP, D-045). La valeur se règle dans la page « Paramètres ».
        RateLimiter::for('register', fn (Request $request) => Limit::perHour((int) config('gamad.limits.register_per_hour'))->by($request->ip()));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute((int) config('gamad.limits.login_per_minute'))->by($throttleKey)->response(function (Request $request, array $headers) {
                // The named Laravel throttle skips Fortify's internal limiter: emit its real Lockout event here.
                event(new Lockout($request));

                return response('Trop de tentatives de connexion.', 429, $headers);
            });
        });
    }
}
