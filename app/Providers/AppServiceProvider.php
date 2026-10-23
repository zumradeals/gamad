<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        if (! $this->isBuildingConfigCache()) {
            $this->app->make(Settings::class)->applyToConfig();
        }

        Password::defaults(fn () => Password::min(8));

        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('moderate', fn (User $user) => $user->isModerator() || $user->isSuperAdmin());

        // Sanctionner une personne (D-151) : jamais soi-même, jamais le super administrateur ; un modérateur n'agit que sur
        // les personnes ordinaires, la modération d'un modérateur revient au super administrateur.
        Gate::define('sanction-person', fn (User $actor, User $person) => $actor->can('moderate')
            && $actor->isNot($person)
            && ! $person->isSuperAdmin()
            && (! $person->isModerator() || $actor->isSuperAdmin()));
    }

    /**
     * Les réglages de la base ne doivent jamais être figés dans le cache de configuration : ils seraient
     * périmés dès la prochaine modification dans la page « Paramètres ».
     */
    private function isBuildingConfigCache(): bool
    {
        return $this->app->runningInConsole()
            && array_intersect(['config:cache', 'optimize'], array_slice($_SERVER['argv'] ?? [], 1)) !== [];
    }
}
