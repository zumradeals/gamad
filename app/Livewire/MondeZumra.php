<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Enums\ProjectHostingState;
use App\Enums\PublicationType;
use App\Enums\ZumraDuty;
use App\Livewire\Concerns\GuardsZumraModule;
use App\Models\Gesture;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Models\ZumraPost;
use App\Services\Contributions;
use App\Services\Invitations;
use App\Services\JoinRequests;
use App\Services\Memberships;
use App\Services\ProjectHosting;
use App\Services\Supports;
use App\Services\Wallets;
use App\Services\ZumraModeration;
use App\Services\ZumraPosts;
use App\Services\Zumras;
use App\Support\Normalizer;
use App\Support\ProjectsModule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monde d'une ZUMRA : l'en-tête, la Formation et le Fil sont publics (selon le niveau de chaque publication) ; la liste des
 * membres est réservée aux personnes connectées et vérifiées, avec la règle de visibilité de chaque profil ; la Discussion est
 * réservée aux membres (404 pour les autres, D-112). L'onglet Projets liste les projets de la ZUMRA (Lot 4).
 */
#[Layout('components.layouts.app')]
class MondeZumra extends Component
{
    use GuardsZumraModule;

    public const MEMBERS_SHOWN = 100;

    public const PAGE_SIZE = 10;

    public const MAX_SIZE = 100;

    public const POSTS_SHOWN = 100;

    public const SKILLS_SHOWN = 12;

    public const TABS = ['formation', 'fil', 'projets', 'membres', 'discussion'];

    #[Locked]
    public string $zumraId;

    #[Url(as: 'onglet', except: 'formation')]
    public string $tab = 'formation';

    public int $limit = self::PAGE_SIZE;

    /** Le mot facultatif de « Je veux rejoindre » (D-110). */
    public string $joinWord = '';

    /** Le message de la Discussion en cours d'écriture. */
    public string $postBody = '';

    /** Motif de la suspension ou du rétablissement d'une ZUMRA (modération, D-215). */
    public string $moderationReason = '';

    /** Motif du retrait de la reconnaissance (super administrateur). */
    public string $recognitionReason = '';

    public function mount(Zumra $zumra): void
    {
        Gate::authorize('view', $zumra);

        $this->zumraId = $zumra->id;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'formation';
        $this->limit = self::PAGE_SIZE;
        $this->resetErrorBag();
    }

    public function more(): void
    {
        $this->limit = min($this->limit + self::PAGE_SIZE, self::MAX_SIZE);
    }

    /** Un geste envoyé, retiré, accepté ou décliné sur une publication de la ZUMRA : la page se redessine. */
    #[On('gesture-changed')]
    public function refresh(): void {}

    public function sendPost(ZumraPosts $posts): void
    {
        $posts->post(Auth::user(), $this->zumra(), $this->postBody);

        $this->reset('postBody');
        $this->resetErrorBag();
    }

    public function deletePost(string $id, ZumraPosts $posts): void
    {
        abort_unless(Str::isUuid($id), 404);

        $posts->delete(Auth::user(), ZumraPost::query()->where('zumra_id', $this->zumraId)->find($id) ?? abort(404));
    }

    public function suspend(ZumraModeration $moderation): void
    {
        $moderation->suspend(Auth::user(), $this->zumra(), $this->moderationReason);

        $this->reset('moderationReason');
        session()->flash('status', 'ZUMRA suspendue : elle disparaît du Carrefour et des fils. Décision inscrite au journal.');
    }

    public function reinstate(ZumraModeration $moderation): void
    {
        $moderation->reinstate(Auth::user(), $this->zumra(), $this->moderationReason);

        $this->reset('moderationReason');
        session()->flash('status', 'ZUMRA rétablie. Décision inscrite au journal.');
    }

    public function recognize(Zumras $zumras): void
    {
        $zumras->recognize(Auth::user(), $this->zumra());
        session()->flash('status', 'ZUMRA reconnue : elle porte désormais le badge « Reconnue par GAMAD ».');
    }

    public function withdrawRecognition(Zumras $zumras): void
    {
        $zumras->withdrawRecognition(Auth::user(), $this->zumra(), $this->recognitionReason);

        $this->reset('recognitionReason');
        session()->flash('status', 'Reconnaissance retirée.');
    }

    public function requestJoin(JoinRequests $requests): void
    {
        $requests->propose(Auth::user(), $this->zumra(), $this->joinWord);

        $this->reset('joinWord');
        session()->flash('status', 'Votre demande est envoyée. Le fondateur ou un responsable y répondra.');
    }

    public function withdrawRequest(JoinRequests $requests): void
    {
        $request = JoinRequest::query()->where('zumra_id', $this->zumraId)->where('user_id', Auth::id())->firstOrFail();

        $requests->withdraw(Auth::user(), $request);
        session()->flash('status', 'Votre demande est retirée.');
    }

    public function acceptInvitation(string $id, Invitations $invitations): void
    {
        $invitations->accept(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Bienvenue dans la ZUMRA.');
    }

    public function refuseInvitation(string $id, Invitations $invitations): void
    {
        $invitations->refuse(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Invitation refusée.');
    }

    public function leave(Memberships $memberships): void
    {
        $memberships->leave(Auth::user(), $this->zumra());

        session()->flash('status', 'Vous avez quitté la ZUMRA.');
    }

    public function acceptRequest(string $id, JoinRequests $requests): void
    {
        $requests->accept(Auth::user(), $this->joinRequest($id));
    }

    public function declineRequest(string $id, JoinRequests $requests): void
    {
        $requests->decline(Auth::user(), $this->joinRequest($id));
    }

    public function cancelInvitation(string $id, Invitations $invitations): void
    {
        abort_unless(Str::isUuid($id), 404);

        $invitations->cancel(Auth::user(), Invitation::query()->where('zumra_id', $this->zumraId)->findOrFail($id));
    }

    public function removeMember(string $id, Memberships $memberships): void
    {
        $memberships->remove(Auth::user(), $this->zumra(), $this->membership($id));
    }

    public function promote(string $id, Memberships $memberships): void
    {
        $memberships->promote(Auth::user(), $this->zumra(), $this->membership($id));
    }

    public function demote(string $id, Memberships $memberships): void
    {
        $memberships->demote(Auth::user(), $this->zumra(), $this->membership($id));
    }

    /** Le fondateur propose un poste à un responsable (il reste « en attente » jusqu'à son acceptation), ou retire la proposition. */
    public function assignDuty(string $id, string $duty, Memberships $memberships): void
    {
        $duty = ZumraDuty::tryFrom($duty);

        $duty === null
            ? $memberships->clearDuty(Auth::user(), $this->zumra(), $this->membership($id))
            : $memberships->proposeDuty(Auth::user(), $this->zumra(), $this->membership($id), $duty);
    }

    public function acceptHosting(string $id, ProjectHosting $hosting): void
    {
        $hosting->accept(Auth::user(), $this->hostedProject($id));
        session()->flash('status', 'Projet accueilli. Il reste à son porteur.');
    }

    public function declineHosting(string $id, ProjectHosting $hosting): void
    {
        $hosting->decline(Auth::user(), $this->hostedProject($id));
        session()->flash('status', 'Demande d’accueil refusée.');
    }

    public function render()
    {
        $viewer = Auth::user();
        $zumra = Zumra::query()->with(['domain', 'city'])
            ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->active())])
            ->findOrFail($this->zumraId);

        Gate::authorize('view', $zumra);

        $canSeeMembers = Gate::forUser($viewer)->allows('viewMembers', $zumra);
        $canDiscuss = Gate::forUser($viewer)->allows('discuss', $zumra);

        // La Discussion n'existe pas pour qui n'est pas membre : « page introuvable » (D-112).
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'formation';
        // Projets fermés (D-213) : l'onglet n'existe pas, même appelé directement.
        if ($tab === 'projets' && ! ProjectsModule::accessibleTo(Auth::user())) {
            $tab = 'formation';
        }
        $this->tab = $tab;
        abort_if($tab === 'discussion' && ! $canDiscuss, 404);

        // Les personnes dont l'adresse n'est pas vérifiée n'existent pas pour les autres : elles ne figurent ni dans la liste ni dans le compte.
        $members = $canSeeMembers
            ? ZumraMembership::query()->where('zumra_id', $zumra->id)
                ->whereHas('user', fn (Builder $user) => $user->active())
                ->with(['user.profile.city'])
                ->orderByRaw("case role when 'founder' then 0 when 'manager' then 1 else 2 end")
                ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'zumra_memberships.user_id'))
                ->orderBy('id')
                ->limit(self::MEMBERS_SHOWN)->get()
            : collect();

        $role = $zumra->roleOf($viewer);
        $manages = (bool) $role?->manages();
        $managed = $viewer ? $viewer->managedZumraIds() : [];
        $walletsEnabled = $manages && app(Wallets::class)->enabled();

        // Les publications de la ZUMRA que la personne a le droit de voir, de la plus récente à la plus ancienne.
        $publications = collect();
        $hasMore = false;

        if (in_array($tab, ['formation', 'fil'], true)) {
            $found = Publication::query()->visibleTo($viewer)->where('zumra_id', $zumra->id)
                ->when($tab === 'formation', fn (Builder $query) => $query->where('type', PublicationType::Knowledge->value))
                ->with(['author.profile', 'domain', 'city', 'skills', 'zumra', 'project'])
                ->withCount(['supports as supports_count'])
                ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', ['sent', 'accepted'])])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit($this->limit + 1)->get();

            $hasMore = $found->count() > $this->limit && $this->limit < self::MAX_SIZE;
            $publications = $found->take($this->limit);
        }

        // Les projets de la ZUMRA que la personne a le droit de voir : les présentations publiées, et ceux qu'elle porte ou auxquels elle collabore.
        // Être membre de la ZUMRA n'ouvre aucun dossier (P1, D-206).
        $visibleProjects = fn () => Project::query()->where('zumra_id', $zumra->id)->where('hosting_state', ProjectHostingState::Accepted->value)
            ->where(fn (Builder $q) => $q->whereNotNull('showcase_published_at')->orWhereIn('projects.id', Project::query()->openableBy($viewer)->select('projects.id')));
        $projects = $tab === 'projets' ? $visibleProjects()->with('owner')->orderBy('name_key')->orderBy('id')->get() : collect();

        // Pour le fondateur et les responsables : les demandes d'accueil à examiner. Seulement ce qui sert à décider, jamais le dossier.
        $hostingRequests = $manages && $tab === 'projets'
            ? Project::query()->where('zumra_id', $zumra->id)->where('hosting_state', ProjectHostingState::Pending->value)->with(['owner.profile', 'domain'])->orderBy('hosting_requested_at')->get()
            : collect();

        $gestures = $viewer && $publications->isNotEmpty()
            ? Gesture::query()->with('conversation')->where('user_id', $viewer->getKey())->whereIn('publication_id', $publications->pluck('id'))->get()->keyBy('publication_id')
            : collect();

        return view('livewire.monde-zumra', [
            'zumra' => $zumra,
            'governance' => $zumra->governance(),
            'viewer' => $viewer,
            'canSeeMembers' => $canSeeMembers,
            'members' => $members,
            'role' => $role,
            'manages' => $manages,
            'tab' => $tab,
            'canDiscuss' => $canDiscuss,
            'publications' => $publications,
            'hasMore' => $hasMore,
            'gestures' => $gestures,
            'projects' => $projects,
            'projectsCount' => $visibleProjects()->count(),
            'hostingRequests' => $hostingRequests,
            'supported' => app(Supports::class)->supportedBy($viewer, $publications->pluck('id')),
            'managed' => $managed,
            'walletsEnabled' => $walletsEnabled,
            'engaged' => app(Contributions::class)->isEngaged($zumra),
            'memberOf' => $viewer ? $viewer->zumraMemberships()->pluck('zumra_id')->all() : [],
            'skills' => $tab === 'formation' ? $this->skills($zumra, $viewer) : ['offered' => collect(), 'wanted' => collect()],
            'discussion' => $tab === 'discussion'
                ? ZumraPost::query()->where('zumra_id', $zumra->id)->with('author')->orderByDesc('created_at')->orderByDesc('id')->limit(self::POSTS_SHOWN)->get()->reverse()->values()
                : collect(),
            // Pour une personne qui n'est pas dans la ZUMRA : sa demande, et l'invitation ouverte qu'elle a reçue.
            'myRequest' => $viewer && ! $role ? JoinRequest::query()->where('zumra_id', $zumra->id)->where('user_id', $viewer->getKey())->first() : null,
            'myInvitation' => $viewer && ! $role ? Invitation::query()->open()->where('zumra_id', $zumra->id)->where('user_id', $viewer->getKey())->with('inviter')->first() : null,
            // Pour le fondateur et les responsables : les demandes à traiter et les invitations en attente.
            'requests' => $manages
                ? JoinRequest::query()->where('zumra_id', $zumra->id)->where('state', JoinRequestState::Pending->value)
                    ->whereHas('user', fn (Builder $user) => $user->active())
                    ->with(['user.profile.city', 'user.profile.offeredSkills'])->orderBy('created_at')->get()
                : collect(),
            'invitations' => $manages
                ? Invitation::query()->open()->where('zumra_id', $zumra->id)->with('user')->orderBy('created_at')->get()
                : collect(),
        ])->title($zumra->name);
    }

    /**
     * Ce que les membres savent faire, et ce que des personnes veulent apprendre ici : les capacités des profils que la personne qui
     * regarde a le droit de voir, sans doublon, par ordre alphabétique (aucun classement, aucun compte).
     *
     * @return array{offered: Collection, wanted: Collection}
     */
    private function skills(Zumra $zumra, ?User $viewer): array
    {
        $profiles = Profile::query()->visibleTo($viewer)
            ->whereIn('user_id', ZumraMembership::query()->where('zumra_id', $zumra->id)->select('user_id'))
            ->with(['offeredSkills', 'wantedSkills'])->get();

        $names = fn (string $relation) => $profiles->flatMap(fn (Profile $profile) => $profile->{$relation})
            ->unique('id')->sortBy(fn ($skill) => Normalizer::key($skill->name))->take(self::SKILLS_SHOWN)->values();

        return ['offered' => $names('offeredSkills'), 'wanted' => $names('wantedSkills')];
    }

    private function zumra(): Zumra
    {
        $zumra = Zumra::query()->findOrFail($this->zumraId);

        Gate::authorize('view', $zumra);

        return $zumra;
    }

    private function hostedProject(string $id): Project
    {
        abort_unless(Str::isUuid($id), 404);

        return Project::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }

    private function joinRequest(string $id): JoinRequest
    {
        abort_unless(Str::isUuid($id), 404);

        return JoinRequest::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }

    private function invitation(string $id): Invitation
    {
        abort_unless(Str::isUuid($id), 404);

        return Invitation::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }

    private function membership(string $id): ZumraMembership
    {
        abort_unless(Str::isUuid($id), 404);

        return ZumraMembership::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }
}
