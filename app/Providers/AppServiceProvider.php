<?php

namespace App\Providers;

use App\Core\Execution\BackgroundRunner;
use App\Core\Files\FilePurger;
use App\Core\Identity\AccountCloser;
use App\Core\Identity\Sessions;
use App\Events\Matching\PersonBlockChanged;
use App\Events\Matching\PersonSuspensionChanged;
use App\Events\Matching\ProfileMatchingChanged;
use App\Events\Matching\ProfileSkillsChanged;
use App\Events\Matching\PublicationMatchingChanged;
use App\Listeners\MatchingSubscriber;
use App\Listeners\SecurityAccessSubscriber;
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
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(BackgroundRunner::class);

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

        // Échec définitif d'une tâche pendant un passage du lanceur du Core : consigné dans `failed_jobs`, comme le fait `queue:work`.
        Event::listen(JobFailed::class, function (JobFailed $event) {
            if (BackgroundRunner::working()) {
                app('queue.failer')->log($event->connectionName, $event->job->getQueue(), $event->job->getRawBody(), $event->exception);
            }
        });

        // Matching : protections immédiates et recherches confiées à l'exécution (voir MatchingSubscriber).
        Event::listen(PublicationMatchingChanged::class, [MatchingSubscriber::class, 'publication']);
        Event::listen(ProfileMatchingChanged::class, [MatchingSubscriber::class, 'profile']);
        Event::listen(ProfileSkillsChanged::class, [MatchingSubscriber::class, 'skills']);
        Event::listen(PersonBlockChanged::class, [MatchingSubscriber::class, 'block']);
        Event::listen(PersonSuspensionChanged::class, [MatchingSubscriber::class, 'suspension']);

        Event::listen(Login::class, [SecurityAccessSubscriber::class, 'login']);
        Event::listen(Failed::class, [SecurityAccessSubscriber::class, 'failed']);
        Event::listen(Lockout::class, [SecurityAccessSubscriber::class, 'lockout']);

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
