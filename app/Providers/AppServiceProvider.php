<?php

namespace App\Providers;

use App\Core\Files\FilePurger;
use App\Core\Identity\AccountCloser;
use App\Core\Identity\Sessions;
use App\Models\OpportunityResponse;
use App\Models\User;
use App\Policies\OpportunityPolicy;
use App\Services\AccountClosure\ConversationsClosure;
use App\Services\AccountClosure\FilesClosure;
use App\Services\AccountClosure\ProfileClosure;
use App\Services\AccountClosure\WalletsClosure;
use App\Services\AccountClosure\ZumrasClosure;
use App\Services\Backup\RcloneProbe;
use App\Services\Backup\StorageProbe;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->bind(StorageProbe::class, RcloneProbe::class);
        $this->app->singleton(FilePurger::class);
        $this->app->singleton(Sessions::class);

        // Fermeture d'un compte : l'ordre est explicite et fait partie du comportement. Le contrôle du portefeuille passe d'abord ;
        // la fondation des ZUMRA est transmise avant la suppression ; les conversations sont nettoyées en dernier.
        $this->app->singleton(AccountCloser::class, fn ($app) => new AccountCloser([
            $app->make(WalletsClosure::class),
            $app->make(ProfileClosure::class),
            $app->make(FilesClosure::class),
            $app->make(ZumrasClosure::class),
            $app->make(ConversationsClosure::class),
        ], $app->make(FilePurger::class)));
    }

    public function boot(): void
    {
        if (! $this->isBuildingConfigCache()) {
            $this->app->make(Settings::class)->applyToConfig();
        }

        Password::defaults(fn () => Password::min(8));

        // Les réponses à une opportunité suivent la politique de l'opportunité (auteur de la réponse et entreprise émettrice).
        Gate::policy(OpportunityResponse::class, OpportunityPolicy::class);

        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('moderate', fn (User $user) => $user->isModerator() || $user->isSuperAdmin());

        // Sanctionner une personne (D-152) : jamais soi-même, jamais le super administrateur ; un modérateur n'agit que sur
        // les personnes ordinaires, la modération d'un modérateur revient au super administrateur.
        Gate::define('sanction-person', fn (User $actor, User $person) => $actor->can('moderate')
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
