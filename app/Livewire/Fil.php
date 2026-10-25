<?php

namespace App\Livewire;

use App\Enums\GestureState;
use App\Enums\PublicationType;
use App\Models\Conversation;
use App\Models\Gesture;
use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\Publication;
use App\Models\User;
use App\Services\People;
use App\Services\PersonalTrust;
use App\Services\PublicationBookmarks;
use App\Services\Supports;
use App\Support\Experience;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fil global (écrans 1 et 4) : les publications visibles pour celui qui regarde, de la plus récente à la plus ancienne, sans
 * aucun algorithme de recommandation (D-087). Onglets : Tout, Savoirs, Réalisations, Besoins, Personnes, Entreprises ; autres vues (suivis, découvrir, partages, apprendre, aides) dans le sélecteur.
 */
#[Layout('components.layouts.app', ['extraWide' => true])]
#[Title('Accueil')]
class Fil extends Component
{
    public const PAGE_SIZE = 10;

    public const MAX_SIZE = 200;

    #[Url(as: 'filtre', except: 'tout')]
    public string $filter = 'tout';

    public int $limit = self::PAGE_SIZE;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    public function updatingQuery(): void
    {
        $this->limit = self::PAGE_SIZE;
    }

    public function clearSearch(): void
    {
        $this->reset('query');
        $this->limit = self::PAGE_SIZE;
        $this->dispatch('feed-search-cleared');
    }

    public function findPeople(): void
    {
        $this->redirect(route('personnes.index', ['q' => Normalizer::display(Str::limit($this->query, 100, ''))]), navigate: false);
    }

    /** Filtres du Fil (D-098) : clé d'adresse => type publié ; « tout » n'en filtre aucun. */
    public const FILTERS = [
        'besoins' => PublicationType::Need,
        'savoirs' => PublicationType::Knowledge,
        'realisations' => PublicationType::PersonalWork,
        'apprendre' => PublicationType::LearningWish,
        'aides' => PublicationType::Offer,
    ];

    /** Filtres sociaux D-193, sans classement ni donnée fictive. */
    public const DISCOVER_FILTER = 'decouvrir';

    public const SHARES_FILTER = 'partages';

    /** Filtre « ZUMRA » (D-098, 3c) : les publications faites au nom d'une ZUMRA, tous types confondus. */
    public const ZUMRA_FILTER = 'zumra';

    /** Filtre « Projets » (D-098, 4b) : les avancées et réalisations publiées au nom d'un projet. */
    public const PROJECT_FILTER = 'projets';

    public function setFilter(string $filter): void
    {
        $this->filter = $this->knows($filter) ? $filter : 'tout';
        $this->limit = self::PAGE_SIZE;
    }

    public function more(): void
    {
        $this->limit = min($this->limit + self::PAGE_SIZE, self::MAX_SIZE);
    }

    /** Un geste envoyé, retiré, accepté ou décliné : la page se redessine. */
    private function knows(string $filter): bool
    {
        return in_array($filter, ['suivis', 'entreprises', 'personnes', self::DISCOVER_FILTER, self::SHARES_FILTER], true) || array_key_exists($filter, self::FILTERS) || (! Experience::initial() && in_array($filter, [self::ZUMRA_FILTER, self::PROJECT_FILTER], true));
    }

    /**
     * Suivi social volontaire depuis une publication personnelle visible.
     * People::follow applique les droits du profil et les blocages, sans créer de relation privée.
     */
    public function followAuthor(string $publicationId, People $people): void
    {
        abort_unless(Str::isUuid($publicationId), 404);
        $viewer = Auth::user();
        abort_unless($viewer?->isActive(), 403);

        $post = Publication::query()->visibleTo($viewer)->whereNull('hidden_at')
            ->whereNull('zumra_id')->whereNull('project_id')->whereNull('company_id')
            ->with('author.profile')->findOrFail($publicationId);

        $people->follow($viewer, $post->author, true);
        $this->dispatch('feed-follow-changed');
    }

    /** Suivre une personne suggérée : mêmes droits et blocages que « Suivre » depuis une publication (People::follow). */
    public function followSuggestion(string $profileId, People $people): void
    {
        abort_unless(Str::isUuid($profileId), 404);
        $viewer = Auth::user();
        abort_unless($viewer?->isActive(), 403);

        $profile = Profile::query()->visibleTo($viewer)->with('user')->findOrFail($profileId);
        $isFollowed = DB::table('person_follows')->where('user_id', $viewer->id)->where('followed_id', $profile->user_id)->exists();
        $people->follow($viewer, $profile->user, ! $isFollowed);
    }

    #[On('fil-publication-created')]
    public function publicationCreated(): void {}

    #[On('gesture-changed')]
    public function refresh(): void {}

    public function render()
    {
        $viewer = Auth::user();
        $filter = $this->knows($this->filter) ? $this->filter : 'tout';

        $text = trim(Str::limit($this->query, 100, ''));
        $key = Normalizer::key($text);

        $posts = Publication::query()
            ->visibleTo($viewer)
            // Une publication masquée n'apparaît jamais dans le Fil, même à son auteur ni à la modération (D-089).
            ->whereNull('hidden_at')
            ->when($key !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($text, $key) {
                $query->where('body', 'ilike', Normalizer::likeContains($text))
                    ->orWhereHas('skills', fn (Builder $skills) => $skills->where('skills.normalized', 'like', Normalizer::likeContains($key)))
                    ->orWhereHas('author', fn (Builder $author) => $author->where('name_search', 'like', Normalizer::likeContains($key)))
                    ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'ilike', Normalizer::likeContains($text)));
            }))
            ->when(array_key_exists($filter, self::FILTERS), fn ($query) => $query->where('type', self::FILTERS[$filter]->value))
            ->when($filter === 'suivis', fn ($query) => $query->whereNull('zumra_id')->whereNull('project_id')
                ->where(fn ($followed) => $followed
                    ->where(fn ($people) => $people->whereNull('company_id')
                        ->whereIn('author_id', DB::table('person_follows')->where('user_id', $viewer->id)->select('followed_id')))
                    ->orWhere(fn ($companies) => $companies->whereNotNull('company_id')
                        ->whereIn('company_id', DB::table('company_follows')->where('user_id', $viewer->id)->select('company_id')))))
            ->when($filter === self::DISCOVER_FILTER, fn ($query) => $query->whereNull('zumra_id')->whereNull('project_id')
                ->where('visibility', Publication::PUBLIC)
                ->where(fn ($discover) => $discover
                    ->where(fn ($people) => $people->whereNull('company_id')->where('author_id', '!=', $viewer->id)
                        ->whereNotIn('author_id', DB::table('person_follows')->where('user_id', $viewer->id)->select('followed_id')))
                    ->orWhere(fn ($companies) => $companies->whereNotNull('company_id')
                        ->whereNotIn('company_id', DB::table('company_follows')->where('user_id', $viewer->id)->select('company_id')))))
            ->when($filter === 'entreprises', fn ($query) => $query->whereNotNull('company_id'))
            // « Personnes » : publications faites en leur nom propre, ni au nom d'une entreprise, ni d'une ZUMRA, ni d'un projet.
            ->when($filter === 'personnes', fn ($query) => $query->whereNull('company_id')->whereNull('zumra_id')->whereNull('project_id'))
            ->when($filter === self::SHARES_FILTER, fn ($query) => $query->whereNull('zumra_id')->whereNull('project_id')
                ->whereIn('type', [PublicationType::Share->value, PublicationType::PersonalWork->value]))
            ->when($filter === self::ZUMRA_FILTER, fn ($query) => $query->whereNotNull('zumra_id'))
            ->when($filter === self::PROJECT_FILTER, fn ($query) => $query->whereNotNull('project_id'))
            ->with(['author.profile', 'company', 'domain', 'city', 'skills', 'zumra', 'project'])
            ->withCount(['supports as supports_count'])
            ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', [GestureState::Sent->value, GestureState::Accepted->value])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();

        $hasMore = $posts->count() > $this->limit;
        $posts = $posts->take($this->limit);
        $followedAuthorIds = DB::table('person_follows')->where('user_id', $viewer->id)
            ->whereIn('followed_id', $posts->pluck('author_id'))->pluck('followed_id')->all();

        return view('livewire.fil', [
            'posts' => $posts,
            'searchText' => $text,
            'hasMore' => $hasMore && $this->limit < self::MAX_SIZE,
            'gestures' => Gesture::query()->with('conversation')->where('user_id', $viewer->getKey())->whereIn('publication_id', $posts->pluck('id'))->get()->keyBy('publication_id'),
            'viewer' => $viewer,
            'profile' => $viewer->ensureProfile()->load(['city', 'offeredSkills', 'wantedSkills']),
            'filter' => $filter,
            'supported' => app(Supports::class)->supportedBy($viewer, $posts->pluck('id')),
            'savedIds' => app(PublicationBookmarks::class)->savedIds($viewer, $posts->pluck('id')),
            'followedAuthorIds' => $followedAuthorIds,
            'managedZumraIds' => $viewer->managedZumraIds(),
            'memberOf' => $viewer->zumraMemberships()->pluck('zumra_id')->all(),
            'activity' => $this->activity($viewer),
            'identityVerified' => app(PersonalTrust::class)->identity($viewer) !== null,
            'suggestions' => $this->suggestions($viewer, $profile = $viewer->ensureProfile()),
            'suggestionFollowedIds' => DB::table('person_follows')->where('user_id', $viewer->id)->pluck('followed_id')->all(),
        ]);
    }

    /** Compteurs réels du bloc « Mon activité » : seulement ce que la personne a déjà fait. */
    private function activity(User $viewer): array
    {
        return [
            'Publications' => $viewer->publications()->whereNull('zumra_id')->whereNull('company_id')->whereNull('hidden_at')->count(),
            'Enregistrements' => DB::table('publication_bookmarks')->where('user_id', $viewer->id)->count(),
            'Échanges' => Conversation::query()->where(fn ($q) => $q->where('owner_id', $viewer->id)->orWhere('helper_id', $viewer->id))->count(),
        ];
    }

    /**
     * Quelques personnes dont les capacités complètent les vôtres (elles apportent ce que vous voulez apprendre, ou veulent apprendre
     * ce que vous apportez), puis, à défaut, des personnes de la même localité déclarée. Profils visibles seulement, hors vous et vos
     * relations ; ordre alphabétique, jamais un classement.
     */
    private function suggestions(User $viewer, Profile $profile): Collection
    {
        $profile->loadMissing(['offeredSkills', 'wantedSkills']);
        $wanted = $profile->wantedSkills->pluck('id');
        $offered = $profile->offeredSkills->pluck('id');
        $related = PersonConnection::query()->forPerson($viewer)->whereIn('state', ['accepted', 'pending'])->get()->map(fn (PersonConnection $c) => $c->otherId($viewer));
        $followed = DB::table('person_follows')->where('user_id', $viewer->id)->pluck('followed_id');
        $base = fn () => Profile::query()->visibleTo($viewer)->where('profiles.user_id', '!=', $viewer->id)
            ->whereNotIn('profiles.user_id', array_merge($related->all(), $followed->all()) ?: [Str::uuid()->toString()])
            ->whereHas('user', fn (Builder $user) => $user->active())
            ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'profiles.user_id'))
            ->with(['user', 'city', 'offeredSkills', 'wantedSkills']);

        $list = collect();
        if ($wanted->isNotEmpty() || $offered->isNotEmpty()) {
            $list = $base()->where(fn (Builder $q) => $q
                ->whereHas('offeredSkills', fn (Builder $s) => $s->whereIn('skills.id', $wanted))
                ->orWhereHas('wantedSkills', fn (Builder $s) => $s->whereIn('skills.id', $offered)))
                ->limit(4)->get()->map(function (Profile $person) use ($wanted, $offered) {
                    $brings = $person->offeredSkills->whereIn('id', $wanted->all())->pluck('name')->first();
                    $learns = $person->wantedSkills->whereIn('id', $offered->all())->pluck('name')->first();
                    $person->setAttribute('reason', $brings ? 'Peut vous apporter : '.$brings : 'Veut apprendre : '.$learns);

                    return $person;
                });
        }

        if ($list->count() < 4 && $profile->city_id) {
            $near = $base()->where('profiles.city_id', $profile->city_id)->whereNotIn('profiles.id', $list->pluck('id')->all() ?: [Str::uuid()->toString()])
                ->limit(4 - $list->count())->get()->map(function (Profile $person) {
                    $person->setAttribute('reason', 'Même localité déclarée');

                    return $person;
                });
            $list = $list->concat($near);
        }

        return $list;
    }
}
