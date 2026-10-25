<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Exceptions\WalletException;
use App\Models\Conversation;
use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\AdminJournal;
use App\Services\DisplayName;
use App\Services\PersonalTrust;
use App\Services\Todo;
use App\Services\Wallets;
use App\Support\Experience;
use App\Support\ProfileCompleteness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Mon espace')]
class Espace extends Component
{
    use WithPagination;

    public string $password = '';

    public string $name = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    /**
     * Chaque personne modifie son propre nom affiché, et lui seul. Les entrées passées du journal d'administration
     * gardent l'ancien nom (actor_label est une copie faite au moment de l'acte).
     */
    public function updateName(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:255']], [], ['name' => 'nom affiché']);

        if (! DisplayName::change($user, $this->name)) {
            session()->flash('status', 'Aucune modification.');
            $this->redirect(route('espace'), navigate: false);

            return;
        }

        session()->flash('status', 'Nom affiché enregistré.');
        $this->redirect(route('espace'), navigate: false);
    }

    /**
     * Visibilité du profil : seulement les deux règles réelles de GAMAD (D-067) : « public » ou « inscrits seulement ». Chacun ne
     * change que la sienne.
     */
    public function setVisibility(string $visibility): void
    {
        abort_unless(in_array($visibility, [Profile::PUBLIC, Profile::MEMBERS], true), 422);

        Auth::user()->ensureProfile()->forceFill(['visibility' => $visibility])->save();
    }

    public function deleteAccount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! Auth::guard('web')->validate(['email' => $user->email, 'password' => $this->password])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        try {
            app(Wallets::class)->assertAccountCanBeDeleted($user);
        } catch (WalletException $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        DB::transaction(function () use ($user) {
            // Les actes d'un modérateur ou du super administrateur sont toujours tracés (CLAUDE.md, règle 8).
            if ($user->can('moderate')) {
                AdminJournal::record($user, 'account.deleted_by_owner', $user, payload: ['role' => $user->platform_role->value]);
            }

            // Aucun jeton de réinitialisation ne survit au compte : il est rattaché à l'adresse e-mail, non à l'identifiant.
            DB::table('password_reset_tokens')->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->delete();

            // Ni adresse IP ni navigateur ne survivent au compte : toutes ses sessions sont effacées (D-048).
            DB::table('sessions')->where('user_id', $user->id)->delete();

            Auth::guard('web')->logout();
            $user->delete();
        });

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        $user = Auth::user();

        $initial = Experience::initial();
        $legacyZumras = $initial ? Zumra::query()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->getKey()))->orderBy('name_key')->get() : collect();
        $legacyWallets = $initial && app(Wallets::class)->enabled()
            ? Wallet::query()->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('owner_type', Wallet::PERSON)->where('owner_id', $user->getKey()))
                ->orWhere(fn ($q) => $q->where('owner_type', Wallet::ZUMRA)->whereIn('owner_id', $user->managedZumraIds())))->get()
            : collect();

        $profile = $user->ensureProfile()->load(['offeredSkills', 'wantedSkills', 'city.country', 'country']);
        $own = fn () => $user->publications()->whereNull('zumra_id')->whereNull('company_id');
        $achievements = $own()->where('type', PublicationType::PersonalWork)->with('skills');
        $posts = $user->publications()->where('type', '!=', PublicationType::PersonalWork)->with('skills');

        return view('livewire.espace', [
            'conversations' => Conversation::query()
                ->where(fn ($q) => $q->where('owner_id', $user->getKey())->orWhere('helper_id', $user->getKey()))
                ->with(['owner', 'helper'])->orderByDesc('updated_at')->orderByDesc('id')->paginate(10, ['*'], 'echanges'),
            'legacyZumras' => $legacyZumras,
            'legacyWallets' => $legacyWallets,
            'user' => $user,
            'profile' => $profile,
            'completeness' => ProfileCompleteness::for($user, $profile),
            'identityVerified' => app(PersonalTrust::class)->identity($user) !== null,
            'available' => $this->isAvailable($profile),
            'achievements' => (clone $achievements)->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get(),
            'counts' => [
                'skills' => $profile->offeredSkills->count(),
                'achievements' => (clone $achievements)->count(),
                'posts' => (clone $posts)->count(),
                'relations' => PersonConnection::query()->forPerson($user)->where('state', 'accepted')->count(),
            ],
            'suggestedPeople' => $this->suggestedPeople($user, $profile),
            'todo' => app(Todo::class)->for($user),
            'walletsEnabled' => ! $initial && app(Wallets::class)->enabled(),
            // Mes publications : le chemin vers « Mon besoin » et les réponses reçues.
            'publications' => (clone $posts)
                ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', ['sent', 'accepted'])])
                ->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /** « Disponible » : seulement si la personne l'a choisi dans ses préférences de mise en relation, et que la période n'est pas passée. */
    private function isAvailable(Profile $profile): bool
    {
        $prefs = $profile->matching_preferences ?? [];

        return ($prefs['enabled'] ?? false) && ($prefs['available'] ?? false) && (empty($prefs['until']) || $prefs['until'] >= today('Africa/Abidjan')->toDateString());
    }

    /**
     * Personnes dont les capacités complètent les vôtres : elles apportent ce que vous voulez apprendre, ou veulent apprendre ce que
     * vous apportez. Seuls des profils réels que vous avez le droit de voir, hors vous-même et vos relations ; vide s'il n'y en a pas.
     *
     * @return Collection<int, Profile>
     */
    private function suggestedPeople(User $user, Profile $profile)
    {
        $wanted = $profile->wantedSkills->pluck('id');
        $offered = $profile->offeredSkills->pluck('id');

        if ($wanted->isEmpty() && $offered->isEmpty()) {
            return collect();
        }

        $related = PersonConnection::query()->forPerson($user)->whereIn('state', ['accepted', 'pending'])->get()->map(fn (PersonConnection $c) => $c->otherId($user));

        return Profile::query()->visibleTo($user)
            ->where('user_id', '!=', $user->getKey())->whereNotIn('user_id', $related)
            ->where(fn ($q) => $q
                ->whereHas('offeredSkills', fn ($s) => $s->whereIn('skills.id', $wanted))
                ->orWhereHas('wantedSkills', fn ($s) => $s->whereIn('skills.id', $offered)))
            ->with(['user', 'city', 'offeredSkills'])->orderByDesc('updated_at')->limit(3)->get();
    }
}
