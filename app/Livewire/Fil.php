<?php

namespace App\Livewire;

use App\Enums\GestureState;
use App\Enums\PublicationType;
use App\Models\Gesture;
use App\Models\Publication;
use App\Services\Supports;
use App\Support\Experience;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
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
 * aucun algorithme de recommandation (D-087). Filtres : Tout, Besoins, Savoirs, Apprendre, Aides (D-098).
 */
#[Layout('components.layouts.app')]
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
        'apprendre' => PublicationType::LearningWish,
        'aides' => PublicationType::Offer,
    ];

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
        return $filter === 'suivis' || array_key_exists($filter, self::FILTERS) || (! Experience::initial() && in_array($filter, [self::ZUMRA_FILTER, self::PROJECT_FILTER], true));
    }

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
                    ->orWhereHas('author', fn (Builder $author) => $author->where('name_search', 'like', Normalizer::likeContains($key)));
            }))
            ->when(array_key_exists($filter, self::FILTERS), fn ($query) => $query->where('type', self::FILTERS[$filter]->value))
            ->when($filter === 'suivis', fn ($query) => $query->where('visibility', Publication::PUBLIC)->whereNull('zumra_id')->whereIn('author_id', DB::table('person_follows')->where('user_id', $viewer->id)->select('followed_id')))
            ->when($filter === self::ZUMRA_FILTER, fn ($query) => $query->whereNotNull('zumra_id'))
            ->when($filter === self::PROJECT_FILTER, fn ($query) => $query->whereNotNull('project_id'))
            ->with(['author.profile', 'domain', 'city', 'skills', 'zumra', 'project'])
            ->withCount(['supports as supports_count'])
            ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', [GestureState::Sent->value, GestureState::Accepted->value])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();

        $hasMore = $posts->count() > $this->limit;
        $posts = $posts->take($this->limit);

        return view('livewire.fil', [
            'posts' => $posts,
            'searchText' => $text,
            'hasMore' => $hasMore && $this->limit < self::MAX_SIZE,
            'gestures' => Gesture::query()->with('conversation')->where('user_id', $viewer->getKey())->whereIn('publication_id', $posts->pluck('id'))->get()->keyBy('publication_id'),
            'viewer' => $viewer,
            'profile' => $viewer->ensureProfile()->load(['city', 'offeredSkills', 'wantedSkills']),
            'filter' => $filter,
            'supported' => app(Supports::class)->supportedBy($viewer, $posts->pluck('id')),
            'managedZumraIds' => $viewer->managedZumraIds(),
            'memberOf' => $viewer->zumraMemberships()->pluck('zumra_id')->all(),
        ]);
    }
}
