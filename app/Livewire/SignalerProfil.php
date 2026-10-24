<?php

namespace App\Livewire;

use App\Models\Profile;
use App\Models\User;
use App\Models\WorkAttestation;
use App\Services\PersonalTrust;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Signalement ou recours')]
class SignalerProfil extends Component
{
    #[Locked]
    public string $subjectId;

    #[Locked]
    public ?string $referenceId = null;

    public string $category = '';

    public string $details = '';

    public bool $sent = false;

    public function mount(Profile $profile): void
    {
        $this->subjectId = $profile->user_id;
        $ref = request()->query('reference');
        if ($ref !== null) {
            abort_unless(is_string($ref) && Str::isUuid($ref), 404);
            $this->referenceId = $ref;
        }
        $this->check();
        $this->category = request()->query('categorie') === 'appeal' ? 'appeal' : ($ref ? 'reference' : 'identity');
    }

    private function check(): array
    {
        abort_unless(Auth::user()?->isActive(), 403);
        $subject = User::findOrFail($this->subjectId);
        $work = $this->referenceId ? WorkAttestation::where('author_id', $subject->id)->findOrFail($this->referenceId) : null;
        if ($work && in_array(Auth::id(), [$work->author_id, $work->attester_id], true)) {
            Gate::authorize('view', $work);
        } else {
            Gate::authorize('view', $subject->ensureProfile());
            if ($work) {
                abort_unless(WorkAttestation::visibleTo(Auth::user())->whereKey($work->id)->exists(), 404);
            }
        }

        return [$subject, $work];
    }

    public function submit(PersonalTrust $trust): void
    {
        [$subject,$work] = $this->check();
        $trust->report(Auth::user(), $subject, $work, $this->category, $this->details);
        $this->sent = true;
        $this->details = '';
    }

    public function render()
    {
        [$subject,$work] = $this->check();

        return view('livewire.signaler-profil', ['subject' => $subject, 'work' => $work]);
    }
}
