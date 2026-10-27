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
use App\Services\ComplementaryPeople;
use App\Services\DisplayName;
use App\Services\PersonalTrust;
use App\Services\Todo;
use App\Services\Wallets;
use App\Support\ContributionsModule;
use App\Support\Experience;
use App\Support\ProfileCompleteness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Mon espace')]
class Espace extends Component
{
    use WithPagination;

    /** Rubriques de la navigation de gauche, dans l'ordre : clé => droit requis (null = tout le monde). */
    public const SECTIONS = [
        'vue-ensemble' => null, 'capacites' => null, 'realisations' => null, 'publications' => null, 'relations' => null, 'compte' => null,
        'echanges' => null, 'enregistrements' => null, 'attestations' => null, 'entreprise' => null, 'pistes' => null, 'activites' => null,
        'moderation' => 'moderate', 'administration' => 'super-admin',
    ];

    public string $password = '';

    public string $name = '';

    /** La rubrique affichée au centre ; reprise dans l'adresse (?rubrique=…) pour le retour arrière et les liens directs. */
    #[Url(as: 'rubrique', history: true)]
    public string $section = 'vue-ensemble';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->section = $this->allowed($this->section);
    }

    /** Affiche une rubrique au centre, sans recharger la page. Une rubrique interdite ramène à la vue d'ensemble. */
    public function show(string $section): void
    {
        $this->section = $this->allowed($section);
        $this->resetPage('echanges');
    }

    /** Côté serveur : une rubrique inconnue ou réservée à d'autres droits n'est jamais affichée. */
    private function allowed(string $section): string
    {
        if (! array_key_exists($section, self::SECTIONS)) {
            return 'vue-ensemble';
        }

        $right = self::SECTIONS[$section];

        return $right === null || Auth::user()?->can($right) ? $section : 'vue-ensemble';
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
            $this->redirect(route('espace', ['rubrique' => 'compte']), navigate: false);

            return;
        }

        session()->flash('status', 'Nom affiché enregistré.');
        $this->redirect(route('espace', ['rubrique' => 'compte']), navigate: false);
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

    /**
     * Accepter ou non de recevoir des propositions d'aide spontanées sur son profil (C1, D-207). Désactivé par défaut : seule la personne
     * le décide, et seulement pour elle-même.
     */
    public function setAcceptsContributions(bool $accepts): void
    {
        abort_unless(ContributionsModule::accessibleTo(Auth::user()), 404);

        Auth::user()->ensureProfile()->forceFill(['accepts_contributions' => $accepts])->save();
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
        $this->section = $this->allowed($this->section);
        $section = $this->section;

        $initial = Experience::initial();
        $legacyZumras = $initial ? Zumra::query()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->getKey()))->orderBy('name_key')->get() : collect();
        $legacyWallets = $initial && app(Wallets::class)->legacyEnabled()
            ? Wallet::query()->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('owner_type', Wallet::PERSON)->where('owner_id', $user->getKey()))
                ->orWhere(fn ($q) => $q->where('owner_type', Wallet::ZUMRA)->whereIn('owner_id', $user->managedZumraIds())))->get()
            : collect();

        $profile = $user->ensureProfile()->load(['offeredSkills', 'wantedSkills', 'city.country', 'country']);
        $own = fn () => $user->publications()->whereNull('zumra_id')->whereNull('company_id');
        $achievements = $own()->where('type', PublicationType::PersonalWork)->with('skills');
        $posts = $user->publications()->where('type', '!=', PublicationType::PersonalWork)->with('skills');

        return view('livewire.espace', [
            'conversations' => $section === 'echanges'
                ? Conversation::query()
                    ->where(fn ($q) => $q->where('owner_id', $user->getKey())->orWhere('helper_id', $user->getKey()))
                    ->with(['owner', 'helper'])->orderByDesc('updated_at')->orderByDesc('id')->paginate(10, ['*'], 'echanges')
                : null,
            'section' => $section,
            'hasLegacy' => $legacyZumras->isNotEmpty() || $legacyWallets->isNotEmpty(),
            'legacyZumras' => $legacyZumras,
            'legacyWallets' => $legacyWallets,
            'user' => $user,
            'profile' => $profile,
            'completeness' => ProfileCompleteness::for($user, $profile),
            'identityVerified' => app(PersonalTrust::class)->identity($user) !== null,
            'available' => $this->isAvailable($profile),
            'achievements' => (clone $achievements)->orderByDesc('created_at')->orderByDesc('id')->limit($section === 'realisations' ? 30 : 3)->get(),
            'counts' => [
                'skills' => $profile->offeredSkills->count(),
                'achievements' => (clone $achievements)->count(),
                'posts' => (clone $posts)->count(),
                'relations' => PersonConnection::query()->forPerson($user)->where('state', 'accepted')->count(),
            ],
            'suggestedPeople' => $this->suggestedPeople($user, $profile),
            'todo' => app(Todo::class)->for($user),
            'walletsEnabled' => ! $initial && app(Wallets::class)->legacyEnabled(),
            // Mes publications : le chemin vers « Mon besoin » et les réponses reçues.
            'publications' => (clone $posts)
                ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', ['sent', 'accepted'])])
                ->orderByDesc('created_at')->orderByDesc('id')->limit($section === 'publications' ? 30 : 5)->get(),
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
        $people = app(ComplementaryPeople::class);

        return $people->suggestions($user, $profile, $people->relatedUserIds($user), 3, ['user', 'city', 'offeredSkills']);
    }
}
