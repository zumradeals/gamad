<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('moderate', fn (User $user) => $user->isModerator() || $user->isSuperAdmin());
    }
}
