<?php

namespace App\Livewire;

use App\Enums\GestureState;
use App\Models\Gesture;
use App\Models\MatchSuggestion;
use App\Models\Publication;
use App\Services\Gestures;
use App\Services\Gratitudes;
use App\Services\MatchingSearches;
use App\Services\Moderation;
use App\Services\PublicationBookmarks;
use App\Services\Publications;
use App\Support\ContributionsModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Page d'une publication. Pour son auteur, c'est l'écran « Mon besoin » (écran 11) : les réponses reçues, avec Accepter et
 * Décliner, puis « Marquer comme résolu » et « Fermer sans suite ». Pour les autres, la publication seule et le bouton de réponse.
 * Une publication qu'on n'a pas le droit de voir répond 404 (D-085).
 */
#[Layout('components.layouts.app', ['narrow' => true])]
class PagePublication extends Component
{
    /**
     * Verrouillé : le navigateur ne peut pas changer de publication en modifiant cette valeur dans la requête. Et, parce que le
     * verrou ne protège pas contre tout, le droit de voir est revérifié à chaque affichage et à chaque action.
     */
    #[Locked]
    public string $publicationId;

    /** Écran 13 : la personne remerciée (identifiant de compte, parmi celles dont le geste est accepté) et le texte du remerciement. */
    public string $outcome = '';

    public string $helperId = '';

    public string $gratitudeText = '';

    /** L'explication de l'auteur d'une publication masquée (D-089). */
    public string $explanation = '';

    public function mount(Publication $publication): void
    {
        Gate::authorize('view', $publication);

        $this->publicationId = $publication->id;
        $this->outcome = (string) $publication->outcome;
    }

    #[On('gesture-changed')]
    public function refresh(): void {}

    public function accept(string $gestureId, Gestures $gestures): void
    {
        $gestures->accept(Auth::user(), $this->gesture($gestureId));
    }

    public function decline(string $gestureId, Gestures $gestures): void
    {
        $gestures->decline(Auth::user(), $this->gesture($gestureId));
    }

    public function saveOutcome(Publications $publications): void
    {
        $publications->saveOutcome(Auth::user(), $this->publication(), $this->outcome);
        session()->flash('status', 'Bilan enregistré.');
    }

    public function resolve(Publications $publications): void
    {
        $publications->resolve(Auth::user(), $this->publication());
        session()->flash('status', 'Besoin marqué comme résolu.');
    }

    /** « Marquer résolu et remercier » : ensemble, ou pas du tout. */
    public function resolveAndThank(Gratitudes $gratitudes): void
    {
        $gratitudes->resolveAndThank(Auth::user(), $this->publication(), $this->helperId, $this->gratitudeText);
        $this->reset('helperId', 'gratitudeText');
        session()->flash('status', 'Besoin marqué comme résolu. Votre remerciement est publié.');
    }

    /** Remercier après coup, sur un besoin déjà résolu. */
    public function thank(Gratitudes $gratitudes): void
    {
        $gratitudes->thank(Auth::user(), $this->publication(), $this->helperId, $this->gratitudeText);
        $this->reset('helperId', 'gratitudeText');
        session()->flash('status', 'Votre remerciement est publié.');
    }

    public function explain(Moderation $moderation): void
    {
        $moderation->explain(Auth::user(), $this->publication(), $this->explanation);

        session()->flash('status', 'Votre explication est envoyée à la modération.');
    }

    public function closeMatching(): void
    {
        DB::transaction(function () {
            $post = Publication::whereKey($this->publicationId)->lockForUpdate()->firstOrFail();
            Gate::authorize('manage', $post);
            abort_unless(! $post->zumra_id && ($post->matching['enabled'] ?? false), 403);
            $post->update(['matching' => array_replace($post->matching, ['open' => false])]);
        });
        session()->flash('status', 'Cette publication ne produit plus de suggestions.');
    }

    public function close(Publications $publications): void
    {
        $publications->close(Auth::user(), $this->publication());
        session()->flash('status', 'Besoin fermé sans suite.');
    }

    public function render()
    {
        $viewer = Auth::user();
        $publication = $this->publication()->load(['author.profile', 'company', 'domain', 'city', 'skills', 'zumra', 'project']);

        if ($this->explanation === '' && $publication->explained_at) {
            $this->explanation = (string) $publication->author_explanation;
        }
        $publication->live_gestures_count = $publication->liveGesturesCount();
        $publication->supports_count = $publication->supports()->count();
        $type = $publication->type;
        // « isAuthor » : la personne qui gère la publication, son auteur ou, pour une ZUMRA, son fondateur et ses responsables (D-116).
        $isAuthor = $publication->isManagedBy($viewer);

        $received = $isAuthor
            ? $publication->gestures()
                ->where('state', '!=', GestureState::Withdrawn->value)
                ->with(['author.profile.city', 'author.profile.offeredSkills', 'conversation'])
                ->orderByRaw("case state when 'sent' then 0 when 'accepted' then 1 else 2 end")
                ->orderBy('created_at')
                ->get()
            : collect();

        // Contributions (C1) : un besoin d'entraide reçoit des propositions ; l'opportunité professionnelle d'une entreprise a son propre parcours.
        $c1 = null;

        if ($publication->isNeed() && ($publication->help_kind || ! $publication->company_id) && ContributionsModule::accessibleTo($viewer)) {
            $c1 = [
                'canContribute' => Gate::forUser($viewer)->allows('contribute', $publication),
                'reason' => Gate::forUser($viewer)->inspect('contribute', $publication)->message(),
                'proposals' => $isAuthor
                    ? $publication->proposals()->with(['author.profile'])->whereNotIn('state', ['withdrawn'])->orderByRaw("case state when 'proposed' then 0 when 'declared' then 1 when 'accepted' then 2 else 3 end")->orderByDesc('created_at')->get()
                    : collect(),
                'mine' => $viewer && ! $isAuthor ? $publication->proposals()->where('user_id', $viewer->getKey())->latest()->first() : null,
                'count' => $publication->proposals()->whereIn('state', ['proposed', 'accepted', 'declared', 'realized'])->count(),
            ];
        }

        // L'état réel de la recherche de correspondances, pour l'auteur d'une publication ouverte au matching.
        $matchSearch = $isAuthor && ! $publication->zumra_id && ($publication->matching['enabled'] ?? false) && ! $publication->isHidden()
            ? app(MatchingSearches::class)->latestForPublication($publication->id) : null;
        $matchCount = $matchSearch ? MatchSuggestion::query()->where('user_id', $viewer->getKey())->where('publication_id', $publication->id)->whereNull('ignored_at')->whereNull('withdrawn_at')->count() : 0;

        return view('livewire.page-publication', [
            'matchSearch' => $matchSearch,
            'matchCount' => $matchCount,
            'c1' => $c1,
            'accepted' => $received->where('state', GestureState::Accepted)->values(),
            'gratitude' => $isAuthor ? $publication->gratitude()->with('helper.profile')->first() : null,
            'publication' => $publication,
            'type' => $type,
            'viewer' => $viewer,
            'isAuthor' => $isAuthor,
            'gesture' => $viewer && ! $isAuthor ? Gesture::query()->with('conversation')->where('publication_id', $publication->id)->where('user_id', $viewer->getKey())->first() : null,
            // Les gestes reçus : ceux en attente d'abord, puis acceptés, puis déclinés ; les gestes retirés disparaissent.
            'received' => $received,
            'saved' => $viewer ? in_array($publication->id, app(PublicationBookmarks::class)->savedIds($viewer, [$publication->id]), true) : false,
        ])->title($isAuthor ? $publication->ownTitle() : $publication->displayLabel());
    }

    /** La publication, après avoir revérifié le droit de la voir (404 sinon, D-085). */
    private function publication(): Publication
    {
        $publication = Publication::query()->findOrFail($this->publicationId);

        Gate::authorize('view', $publication);

        return $publication;
    }

    private function gesture(string $id): Gesture
    {
        abort_unless(Str::isUuid($id), 404);

        $this->publication();

        return Gesture::query()->where('publication_id', $this->publicationId)->findOrFail($id);
    }
}
