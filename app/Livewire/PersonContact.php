<?php

namespace App\Livewire;

use App\Models\Profile;
use App\Services\People;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PersonContact extends Component
{
    #[Locked]
    public string $profileId;

    public string $note = '';

    public bool $composing = false;

    public function mount(Profile $profile): void
    {
        $this->profileId = $profile->id;
    }

    private function profile(): Profile
    {
        $profile = Profile::findOrFail($this->profileId);
        Gate::authorize('view', $profile);

        return $profile;
    }

    public function follow(bool $follow, People $people): void
    {
        $people->follow(Auth::user(), $this->profile()->user, $follow);
    }

    public function request(People $people): void
    {
        $people->request(Auth::user(), $this->profile()->user, $this->note);
        $this->reset('composing', 'note');
    }

    public function decide(string $decision, int $revision, People $people): void
    {
        $people->decide(Auth::user(), $this->profile()->user, $decision, $revision);
    }

    public function block(People $people): void
    {
        $people->block(Auth::user(), $this->profile()->user, true);
        $this->redirect(route('espace'), navigate: false);
    }

    public function render(People $people)
    {
        $profile = $this->profile();
        $viewer = Auth::user();

        return view('livewire.person-contact', [
            'viewer' => $viewer, 'profile' => $profile,
            'pair' => $viewer ? $people->pair($viewer, $profile->user)->first() : null,
            'following' => $viewer && DB::table('person_follows')->where('user_id', $viewer->id)->where('followed_id', $profile->user_id)->exists(),
        ]);
    }
}
