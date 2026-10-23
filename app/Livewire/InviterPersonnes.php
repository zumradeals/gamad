<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zumra;
use App\Services\Invitations;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inviter des personnes dans une ZUMRA selon leurs capacités (D-110) : on cherche ce qu'elles peuvent apporter (même règle que le
 * Carrefour Personnes : profils visibles pour soi, ordre alphabétique, aucun classement) et on invite avec un mot facultatif.
 * Réservé au fondateur et aux responsables, revérifié à chaque affichage et à chaque invitation.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Inviter des personnes')]
class InviterPersonnes extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    #[Locked]
    public string $zumraId;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** Adresse d'un profil précis (bouton « Inviter dans ma ZUMRA » du Monde d'une personne). */
    #[Url(as: 'personne', except: '')]
    public string $person = '';

    /** Le profil en cours d'invitation (le mot facultatif s'écrit sous sa carte). */
    public string $inviting = '';

    public string $inviteWord = '';

    public function mount(Zumra $zumra): void
    {
        Gate::authorize('invite', $zumra);

        $this->zumraId = $zumra->id;
    }

    public function updatingQuery(): void
    {
        $this->resetPage();
    }

    public function startInvite(string $profileId): void
    {
        $this->inviting = Str::isUuid($profileId) ? $profileId : '';
        $this->reset('inviteWord');
        $this->resetErrorBag();
    }

    public function stopInvite(): void
    {
        $this->reset('inviting', 'inviteWord');
        $this->resetErrorBag();
    }

    public function send(Invitations $invitations): void
    {
        abort_unless(Str::isUuid($this->inviting), 404);

        $zumra = $this->zumra();
        $profile = Profile::query()->visibleTo(Auth::user())->with('user')->findOrFail($this->inviting);

        $invitations->invite(Auth::user(), $zumra, $profile->user, $this->inviteWord);

        $this->reset('inviting', 'inviteWord');
        session()->flash('status', 'Invitation envoyée à '.$profile->user->name.'.');
    }

    public function render()
    {
        $zumra = $this->zumra();
        $viewer = Auth::user();
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $memberIds = $zumra->memberships()->pluck('user_id');

        $chosen = $this->person !== ''
            ? Profile::query()->visibleTo($viewer)->where('public_id', $this->person)->whereNotIn('user_id', $memberIds)->with(['user', 'city', 'offeredSkills'])->first()
            : null;

        $people = Profile::query()
            ->visibleTo($viewer)
            ->whereNotIn('user_id', $memberIds)
            ->whereHas('offeredSkills')
            ->when($key !== '', function (Builder $query) use ($key) {
                $like = Normalizer::likeContains($key);

                $query->where(function (Builder $query) use ($like) {
                    $query->whereHas('offeredSkills', fn (Builder $skills) => $skills->where('skills.normalized', 'like', $like))
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name_search', 'like', $like));
                });
            })
            ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'profiles.user_id'))
            ->orderBy('profiles.id')
            ->with(['user', 'city', 'offeredSkills'])
            ->paginate(self::PER_PAGE);

        $shown = collect([$chosen])->filter()->merge($people->getCollection())->unique('id');
        $userIds = $shown->pluck('user_id');

        return view('livewire.inviter-personnes', [
            'zumra' => $zumra,
            'chosen' => $chosen,
            'people' => $people,
            'shown' => $shown,
            'invitedIds' => Invitation::query()->open()->where('zumra_id', $zumra->id)->whereIn('user_id', $userIds)->pluck('user_id')->all(),
            'requestedIds' => JoinRequest::query()->where('zumra_id', $zumra->id)->where('state', JoinRequestState::Pending->value)->whereIn('user_id', $userIds)->pluck('user_id')->all(),
            'key' => $key,
        ]);
    }

    private function zumra(): Zumra
    {
        $zumra = Zumra::query()->findOrFail($this->zumraId);

        Gate::authorize('invite', $zumra);

        return $zumra;
    }
}
