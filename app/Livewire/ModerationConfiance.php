<?php

namespace App\Livewire;

use App\Models\IdentityVerification;
use App\Models\TrustReport;
use App\Models\WorkAttestation;
use App\Services\PersonalTrust;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Confiance et attestations')]
class ModerationConfiance extends Component
{
    public array $reasons = [];

    public array $methods = [];

    public array $namesChecked = [];

    public array $personsChecked = [];

    public string $search = '';

    public int $limit = 20;

    private function authorizeReview(): void
    {
        abort_unless(Auth::user()->isActive(), 403);
        Gate::authorize('super-admin');
    }

    public function mount(): void
    {
        $this->authorizeReview();
    }

    public function identity(string $id, int $revision, string $decision, PersonalTrust $trust): void
    {
        $this->authorizeReview();
        $trust->reviewIdentity(Auth::user(), $id, $revision, $decision, $this->reasons[$id] ?? '', $this->methods[$id] ?? '', ($this->namesChecked[$id] ?? false) === true, ($this->personsChecked[$id] ?? false) === true);
        $this->done($id);
    }

    public function revoke(string $id, int $revision, PersonalTrust $trust): void
    {
        $this->authorizeReview();
        $trust->revokeWork(Auth::user(), $id, $revision, $this->reasons[$id] ?? '');
        $this->done($id);
    }

    public function reopen(string $id, int $revision, PersonalTrust $trust): void
    {
        $this->authorizeReview();
        $trust->reopenWork(Auth::user(), $id, $revision, $this->reasons[$id] ?? '');
        $this->done($id);
    }

    public function closeReport(string $id, int $revision, PersonalTrust $trust): void
    {
        $this->authorizeReview();
        $trust->closeReport(Auth::user(), $id, $revision, $this->reasons[$id] ?? '');
        $this->done($id);
    }

    public function more(): void
    {
        $this->limit = min(200, max(20, $this->limit) + 20);
    }

    private function done(string $id): void
    {
        unset($this->reasons[$id],$this->methods[$id],$this->namesChecked[$id],$this->personsChecked[$id]);
        session()->flash('status', 'Décision enregistrée et journalisée.');
    }

    public function render()
    {
        $this->authorizeReview();
        $key = Normalizer::key(mb_substr($this->search, 0, 100));
        $limit = min(200, max(20, $this->limit));

        return view('livewire.moderation-confiance', ['identities' => IdentityVerification::with('user')->when($key !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name_search', 'like', Normalizer::likeContains($key))))->orderByRaw("case when state = 'pending' then 0 else 1 end")->latest('updated_at')->limit($limit)->get(), 'reports' => TrustReport::where('state', 'open')->with(['reporter', 'subject', 'workAttestation'])->oldest()->limit($limit)->get(), 'works' => WorkAttestation::whereIn('state', ['confirmed', 'pending'])->with(['author', 'attester', 'publication'])->when($key !== '', fn ($q) => $q->whereHas('author', fn ($u) => $u->where('name_search', 'like', Normalizer::likeContains($key))))->latest()->limit($limit)->get()]);
    }
}
