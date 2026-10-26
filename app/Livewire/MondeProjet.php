<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Enums\ProjectStage;
use App\Enums\ProjectState;
use App\Enums\ProjectStepState;
use App\Enums\PublicationType;
use App\Livewire\Concerns\GuardsZumraModule;
use App\Livewire\Concerns\PicksSkills;
use App\Models\Company;
use App\Models\Gesture;
use App\Models\ParticipationRequest;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectCompanyLink;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\ProjectStep;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Services\ParticipationRequests;
use App\Services\ProjectCompanyLinks;
use App\Services\ProjectHosting;
use App\Services\ProjectInvitations;
use App\Services\Projects;
use App\Services\ProjectShowcase;
use App\Services\ProjectSteps;
use App\Services\ProjectTeam;
use App\Services\Supports;
use App\Support\Experience;
use App\Support\Normalizer;
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
 * Dossier d'un projet (P1, D-206), privé : seuls son porteur et ses collaborateurs l'ouvrent, jamais par la seule appartenance à une
 * ZUMRA. Onglets : Avancement (étape, état, étapes), Accueil (la ZUMRA qui l'accueille), Équipe (collaborateurs avec leur accord,
 * demandes d'aide), Compétences recherchées, Présentation publique (la vitrine volontaire) et Entreprise ; Fil et Besoins quand les
 * publications collectives sont ouvertes. Le porteur seul modifie ; les collaborateurs lisent.
 */
#[Layout('components.layouts.app')]
class MondeProjet extends Component
{
    use GuardsZumraModule, PicksSkills;

    public const TABS = ['avancement', 'accueil', 'equipe', 'competences', 'vitrine', 'entreprise', 'fil', 'besoins'];

    public const PAGE_SIZE = 10;

    public const MAX_SIZE = 100;

    /** Personnes affichées dans l'équipe, et proposées à l'invitation. */
    public const PEOPLE_SHOWN = 100;

    #[Locked]
    public string $projectId;

    #[Url(as: 'onglet', except: 'avancement')]
    public string $tab = 'avancement';

    public int $limit = self::PAGE_SIZE;

    /** Le titre de la nouvelle étape en cours d'écriture. */
    public string $stepTitle = '';

    /** Le mot facultatif de la demande d'aide (D-128). */
    public string $participationWord = '';

    /** Compétences recherchées. @var array<int, string> */
    public array $needs = [];

    public string $needsInput = '';

    // Accueil par une ZUMRA.
    public string $hostZumraId = '';

    public string $hostMessage = '';

    // Invitation d'un collaborateur.
    public string $inviteQuery = '';

    public string $inviteWord = '';

    // Présentation publique.
    public string $showSummary = '';

    public string $showContribute = '';

    public bool $showOwner = false;

    public bool $showNeeds = false;

    // Lien avec une entreprise.
    public string $companyId = '';

    public string $companyKind = 'carried';

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->projectId = $project->id;
        $this->needs = $project->neededSkills()->pluck('name')->all();
        $this->hostZumraId = $project->zumra_id;
        $this->showSummary = (string) $project->showcase_summary;
        $this->showContribute = (string) $project->showcase_contribute;
        $this->showOwner = (bool) $project->showcase_show_owner;
        $this->showNeeds = (bool) $project->showcase_show_needs;
    }

    #[On('gesture-changed')]
    public function refresh(): void {}

    protected function skillLists(): array
    {
        return ['needs' => Project::MAX_SKILLS];
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'avancement';
        $this->limit = self::PAGE_SIZE;
        $this->resetErrorBag();
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

    public function setStage(string $stage, Projects $projects): void
    {
        $projects->setStage(Auth::user(), $this->project(), ProjectStage::tryFrom($stage) ?? abort(404));

        session()->flash('status', 'Étape du projet mise à jour.');
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

    public function saveNeeds(Projects $projects): void
    {
        $this->resetErrorBag();
        $projects->setNeeds(Auth::user(), $this->project(), $this->needs);

        session()->flash('status', 'Compétences recherchées enregistrées.');
    }

    public function requestHosting(ProjectHosting $hosting): void
    {
        $this->resetErrorBag();
        abort_unless(Str::isUuid($this->hostZumraId), 404);
        $zumra = Zumra::query()->find($this->hostZumraId) ?? abort(404);

        $hosting->request(Auth::user(), $this->project(), $zumra, $this->hostMessage);

        $this->reset('hostMessage');
        session()->flash('status', $this->project()->isHosted() ? 'Projet rattaché à votre ZUMRA.' : 'Demande d’accueil envoyée. Le projet reste privé en attendant la réponse.');
    }

    public function withdrawHosting(ProjectHosting $hosting): void
    {
        $hosting->withdraw(Auth::user(), $this->project());

        session()->flash('status', 'Demande d’accueil retirée.');
    }

    public function publishShowcase(ProjectShowcase $showcase): void
    {
        $this->resetErrorBag();
        $showcase->publish(Auth::user(), $this->project(), ['summary' => $this->showSummary, 'contribute' => $this->showContribute, 'show_owner' => $this->showOwner, 'show_needs' => $this->showNeeds]);

        session()->flash('status', 'Présentation publique publiée.');
    }

    public function unpublishShowcase(ProjectShowcase $showcase): void
    {
        $showcase->unpublish(Auth::user(), $this->project());

        session()->flash('status', 'Présentation publique retirée.');
    }

    public function invite(string $userId, ProjectInvitations $invitations): void
    {
        abort_unless(Str::isUuid($userId), 404);
        $this->resetErrorBag();

        $invitations->invite(Auth::user(), $this->project(), User::query()->findOrFail($userId), $this->inviteWord);

        $this->reset('inviteWord', 'inviteQuery');
        session()->flash('status', 'Invitation envoyée. La personne accepte ou refuse.');
    }

    public function cancelInvitation(string $id, ProjectInvitations $invitations): void
    {
        abort_unless(Str::isUuid($id), 404);

        $invitations->cancel(Auth::user(), ProjectInvitation::query()->where('project_id', $this->projectId)->findOrFail($id));
    }

    public function requestParticipation(ParticipationRequests $requests): void
    {
        $requests->propose(Auth::user(), $this->project(), $this->participationWord);

        $this->reset('participationWord');
        session()->flash('status', 'Demande envoyée. Le porteur du projet va la lire.');
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

        $this->redirect(route('espace.projets'), navigate: false);
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

    public function proposeCompany(ProjectCompanyLinks $links): void
    {
        $this->resetErrorBag();
        abort_unless(Str::isUuid($this->companyId), 404);

        $links->propose(Auth::user(), $this->project(), Company::query()->findOrFail($this->companyId), $this->companyKind);

        $this->reset('companyId');
        session()->flash('status', 'Demande envoyée à l’entreprise. Le lien n’existe qu’une fois confirmé.');
    }

    public function endCompanyLink(string $id, ProjectCompanyLinks $links): void
    {
        abort_unless(Str::isUuid($id), 404);

        $links->end(Auth::user(), ProjectCompanyLink::query()->where('project_id', $this->projectId)->findOrFail($id));
    }

    public function render()
    {
        $project = Project::query()->with(['zumra.domain', 'zumra.city', 'owner', 'domain', 'city'])->findOrFail($this->projectId);

        Gate::authorize('view', $project);

        $viewer = Auth::user();
        $initial = Experience::initial();
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'avancement';
        $tab = $initial && in_array($tab, ['fil', 'besoins'], true) ? 'avancement' : $tab;
        $this->tab = $tab;
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
            'initial' => $initial,
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
            'suggestions' => $tab === 'competences' ? $this->skillSuggestions('needs') : collect(),
            ...$this->team($project, $viewer, $manages, $tab),
            ...$this->hosting($project, $viewer, $manages, $tab),
            ...$this->company($project, $manages, $tab),
        ])->title($project->name);
    }

    /**
     * Ce que montre l'onglet Équipe : les collaborateurs, leurs invitations et, pour le porteur, les demandes d'aide à traiter et la
     * recherche de personnes à inviter.
     *
     * @return array<string, mixed>
     */
    private function team(Project $project, ?User $viewer, bool $manages, string $tab): array
    {
        $verified = fn (Builder $user) => $user->active();

        $count = ProjectMember::query()->where('project_id', $project->id)->whereHas('user', $verified)->count();

        if ($tab !== 'equipe') {
            return ['teamCount' => $count, 'teamMembers' => collect(), 'requests' => collect(), 'invitations' => collect(), 'candidates' => collect()];
        }

        $query = Normalizer::key(Str::limit($this->inviteQuery, 60, ''));

        return [
            'teamCount' => $count,
            'teamMembers' => ProjectMember::query()->where('project_id', $project->id)->whereHas('user', $verified)->with(['user.profile.city'])
                ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'project_members.user_id'))->orderBy('id')->limit(self::PEOPLE_SHOWN)->get(),
            'requests' => $manages
                ? ParticipationRequest::query()->where('project_id', $project->id)->where('state', JoinRequestState::Pending->value)
                    ->whereHas('user', $verified)->with(['user.profile.city', 'user.profile.offeredSkills'])->orderBy('created_at')->get()
                : collect(),
            'invitations' => $manages
                ? ProjectInvitation::query()->where('project_id', $project->id)->where('state', ProjectInvitation::PENDING)->with('user')->orderBy('created_at')->get()
                : collect(),
            // Des personnes que le porteur a le droit de voir, hors équipe et hors lui-même, trouvées par leur nom.
            'candidates' => $manages && mb_strlen($query) >= 2
                ? Profile::query()->visibleTo($viewer)->with(['user', 'city'])
                    ->whereHas('user', fn (Builder $user) => $user->where('name_search', 'like', Normalizer::likeContains($query)))
                    ->where('profiles.user_id', '<>', $viewer->getKey())
                    ->whereNotIn('profiles.user_id', ProjectMember::query()->where('project_id', $project->id)->select('user_id'))
                    ->limit(8)->get()
                : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function hosting(Project $project, ?User $viewer, bool $manages, string $tab): array
    {
        if ($tab !== 'accueil' || ! $manages) {
            return ['hostOptions' => collect()];
        }

        // Ses ZUMRA d'abord, puis les autres (qu'il faut convaincre d'accueillir le projet), par ordre alphabétique.
        $mine = $viewer->zumraMemberships()->pluck('zumra_id')->all();

        return ['hostOptions' => Zumra::query()->orderByRaw('case when id in ('.($mine === [] ? "'00000000-0000-0000-0000-000000000000'" : implode(',', array_map(fn ($id) => "'".$id."'", $mine))).') then 0 else 1 end')
            ->orderBy('name_key')->limit(200)->get(['id', 'name']), 'mine' => $mine];
    }

    /** @return array<string, mixed> */
    private function company(Project $project, bool $manages, string $tab): array
    {
        if ($tab !== 'entreprise') {
            return ['companyLinks' => collect(), 'companyOptions' => collect()];
        }

        return [
            'companyLinks' => $project->companyLinks()->with(['company', 'originZumra'])->orderBy('created_at')->get(),
            'companyOptions' => $manages ? Company::query()->publiclyVisible()->orderBy('name')->limit(200)->get(['id', 'name']) : collect(),
        ];
    }

    private function project(): Project
    {
        return Project::query()->with('zumra', 'owner')->findOrFail($this->projectId);
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
