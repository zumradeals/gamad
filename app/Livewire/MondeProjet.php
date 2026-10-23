<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Enums\ProjectState;
use App\Enums\ProjectStepState;
use App\Enums\PublicationType;
use App\Models\Gesture;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStep;
use App\Models\Publication;
use App\Models\User;
use App\Models\ZumraMembership;
use App\Services\ParticipationRequests;
use App\Services\Projects;
use App\Services\ProjectSteps;
use App\Services\ProjectTeam;
use App\Services\Supports;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monde d'un projet : la page publique d'un projet, lisible sans compte (D-005). Onglet Avancement : son état, ses étapes et
 * l'histoire de ses changements d'état ; Fil : les avancées et réalisations publiées en son nom ; Équipe : les membres de la ZUMRA
 * qui y participent ; Besoins : ses besoins (Lots 4a à 4c). Le fondateur et les responsables de la ZUMRA le modifient, gèrent ses
 * étapes, son équipe et publient en son nom (D-127).
 */
#[Layout('components.layouts.app')]
class MondeProjet extends Component
{
    public const TABS = ['avancement', 'fil', 'equipe', 'besoins'];

    public const PAGE_SIZE = 10;

    public const MAX_SIZE = 100;

    /** Personnes affichées dans l'équipe, et proposées à l'ajout. */
    public const PEOPLE_SHOWN = 100;

    #[Locked]
    public string $projectId;

    #[Url(as: 'onglet', except: 'avancement')]
    public string $tab = 'avancement';

    public int $limit = self::PAGE_SIZE;

    /** Le titre de la nouvelle étape en cours d'écriture. */
    public string $stepTitle = '';

    /** Le mot facultatif de « Je veux participer » (D-128). */
    public string $participationWord = '';

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    #[On('gesture-changed')]
    public function refresh(): void {}

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'avancement';
        $this->limit = self::PAGE_SIZE;
    }

    public function more(): void
    {
        $this->limit = min($this->limit + self::PAGE_SIZE, self::MAX_SIZE);
    }

    public function changeState(string $state, Projects $projects): void
    {
        $projects->changeState(Auth::user(), $this->project(), ProjectState::tryFrom($state) ?? abort(404));

        session()->flash('status', 'État du projet mis à jour.');
    }

    public function addStep(ProjectSteps $steps): void
    {
        $steps->add(Auth::user(), $this->project(), $this->stepTitle);

        $this->reset('stepTitle');
    }

    public function setStepState(string $id, string $state, ProjectSteps $steps): void
    {
        $steps->setState(Auth::user(), $this->step($id), ProjectStepState::tryFrom($state) ?? abort(404));
    }

    public function moveStep(string $id, int $direction, ProjectSteps $steps): void
    {
        $steps->move(Auth::user(), $this->step($id), $direction);
    }

    public function removeStep(string $id, ProjectSteps $steps): void
    {
        $steps->remove(Auth::user(), $this->step($id));
    }

    public function requestParticipation(ParticipationRequests $requests): void
    {
        $requests->propose(Auth::user(), $this->project(), $this->participationWord);

        $this->reset('participationWord');
        session()->flash('status', 'Demande envoyée. Les responsables de la ZUMRA vont la lire.');
    }

    public function withdrawParticipation(ParticipationRequests $requests): void
    {
        $requests->withdraw(Auth::user(), ParticipationRequest::query()->where('project_id', $this->projectId)->where('user_id', Auth::id())->firstOrFail());

        session()->flash('status', 'Demande retirée.');
    }

    public function leaveTeam(ProjectTeam $team): void
    {
        $team->leave(Auth::user(), $this->project());

        session()->flash('status', 'Vous avez quitté l’équipe.');
    }

    public function addMember(string $userId, ProjectTeam $team): void
    {
        abort_unless(Str::isUuid($userId), 404);

        $team->add(Auth::user(), $this->project(), User::query()->findOrFail($userId));
    }

    public function removeMember(string $id, ProjectTeam $team): void
    {
        abort_unless(Str::isUuid($id), 404);

        $team->remove(Auth::user(), ProjectMember::query()->where('project_id', $this->projectId)->find($id) ?? abort(404));
    }

    public function acceptRequest(string $id, ParticipationRequests $requests): void
    {
        $requests->accept(Auth::user(), $this->participationRequest($id));
    }

    public function declineRequest(string $id, ParticipationRequests $requests): void
    {
        $requests->decline(Auth::user(), $this->participationRequest($id));
    }

    public function render()
    {
        $project = Project::query()->with(['zumra.domain', 'zumra.city'])->findOrFail($this->projectId);

        Gate::authorize('view', $project);

        $viewer = Auth::user();
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'avancement';
        $manages = $viewer && Gate::forUser($viewer)->allows('update', $project);

        $steps = $project->steps()->get();

        // Les publications du projet que la personne a le droit de voir, de la plus récente à la plus ancienne.
        $publications = collect();
        $hasMore = false;

        if (in_array($tab, ['fil', 'besoins'], true)) {
            $found = Publication::query()->visibleTo($viewer)->where('project_id', $project->id)
                ->when($tab === 'besoins', fn (Builder $query) => $query->where('type', PublicationType::Need->value),
                    fn (Builder $query) => $query->where('type', '!=', PublicationType::Need->value))
                ->with(['author.profile', 'domain', 'city', 'skills', 'zumra', 'project'])
                ->withCount(['supports as supports_count'])
                ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', ['sent', 'accepted'])])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit($this->limit + 1)->get();

            $hasMore = $found->count() > $this->limit && $this->limit < self::MAX_SIZE;
            $publications = $found->take($this->limit);
        }

        $gestures = $viewer && $tab === 'besoins' && $publications->isNotEmpty()
            ? Gesture::query()->with('conversation')->where('user_id', $viewer->getKey())->whereIn('publication_id', $publications->pluck('id'))->get()->keyBy('publication_id')
            : collect();

        return view('livewire.monde-projet', [
            'project' => $project,
            'viewer' => $viewer,
            'tab' => $tab,
            'manages' => $manages,
            'steps' => $steps,
            'stepsDone' => $steps->where('state', ProjectStepState::Done)->count(),
            'changes' => $tab === 'avancement' ? $project->stateChanges()->with('user')->orderBy('created_at')->orderBy('id')->get() : collect(),
            'publications' => $publications,
            'hasMore' => $hasMore,
            'stepMax' => ProjectStep::MAX_PER_PROJECT,
            'gestures' => $gestures,
            'supported' => app(Supports::class)->supportedBy($viewer, $publications->pluck('id')),
            'managed' => $viewer ? $viewer->managedZumraIds() : [],
            'memberOf' => $viewer ? $viewer->zumraMemberships()->pluck('zumra_id')->all() : [],
            ...$this->team($project, $viewer, $manages, $tab),
        ])->title($project->name);
    }

    /**
     * Ce que montre l'onglet Équipe : le nombre réel de personnes ; pour une personne connectée et vérifiée, la liste ; pour la
     * personne concernée, sa demande ; pour les gestionnaires, les demandes à traiter et les membres de la ZUMRA à ajouter.
     *
     * @return array<string, mixed>
     */
    private function team(Project $project, ?User $viewer, bool $manages, string $tab): array
    {
        $verified = fn (Builder $user) => $user->active();

        $count = ProjectMember::query()->where('project_id', $project->id)->whereHas('user', $verified)->count();

        if ($tab !== 'equipe') {
            return ['teamCount' => $count, 'teamMembers' => collect(), 'canSeeTeam' => false, 'myRequest' => null, 'isMember' => false,
                'participation' => null, 'requests' => collect(), 'candidates' => collect()];
        }

        $canSeeTeam = Gate::forUser($viewer)->allows('viewTeam', $project);
        $isMember = $viewer && ProjectMember::query()->where('project_id', $project->id)->where('user_id', $viewer->getKey())->exists();

        return [
            'teamCount' => $count,
            'canSeeTeam' => $canSeeTeam,
            'teamMembers' => $canSeeTeam
                ? ProjectMember::query()->where('project_id', $project->id)->whereHas('user', $verified)->with(['user.profile.city'])
                    ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'project_members.user_id'))->orderBy('id')->limit(self::PEOPLE_SHOWN)->get()
                : collect(),
            'isMember' => $isMember,
            'myRequest' => $viewer ? ParticipationRequest::query()->where('project_id', $project->id)->where('user_id', $viewer->getKey())->first() : null,
            // Le droit de demander, et la raison quand il est refusé (affichée telle quelle).
            'participation' => $viewer ? Gate::forUser($viewer)->inspect('participate', $project) : null,
            'requests' => $manages
                ? ParticipationRequest::query()->where('project_id', $project->id)->where('state', JoinRequestState::Pending->value)
                    ->whereHas('user', $verified)->with(['user.profile.city', 'user.profile.offeredSkills'])->orderBy('created_at')->get()
                : collect(),
            // Les membres vérifiés de la ZUMRA qui ne sont pas encore dans l'équipe.
            'candidates' => $manages
                ? ZumraMembership::query()->where('zumra_id', $project->zumra_id)->whereHas('user', $verified)
                    ->whereNotIn('user_id', ProjectMember::query()->where('project_id', $project->id)->select('user_id'))
                    ->with('user')->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'zumra_memberships.user_id'))->orderBy('id')->limit(self::PEOPLE_SHOWN)->get()
                : collect(),
        ];
    }

    private function project(): Project
    {
        return Project::query()->with('zumra')->findOrFail($this->projectId);
    }

    /** Une étape de ce projet : un identifiant qui n'en est pas une, ou d'un autre projet, n'existe pas. */
    private function step(string $id): ProjectStep
    {
        abort_unless(Str::isUuid($id), 404);

        return ProjectStep::query()->where('project_id', $this->projectId)->with('project.zumra')->find($id) ?? abort(404);
    }

    /** Une demande de participation à ce projet. */
    private function participationRequest(string $id): ParticipationRequest
    {
        abort_unless(Str::isUuid($id), 404);

        return ParticipationRequest::query()->where('project_id', $this->projectId)->find($id) ?? abort(404);
    }
}
