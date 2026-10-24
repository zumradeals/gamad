<?php

namespace App\Livewire;

use App\Models\IdentityVerification;
use App\Models\TrustReport;
use App\Models\WorkAttestation;
use App\Services\PersonalTrust;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Mes attestations')]
class MesAttestations extends Component
{
    public bool $consent = false;

    public array $roles = [];

    public array $consents = [];

    public int $limit = 20;

    public function requestIdentity(PersonalTrust $trust): void
    {
        $trust->requestIdentity(Auth::user(), $this->consent);
        $this->consent = false;
        session()->flash('status', 'Demande enregistrée. Suivez ici les modalités du contrôle humain.');
    }

    public function withdrawIdentity(int $revision, PersonalTrust $trust): void
    {
        $trust->withdrawIdentity(Auth::user(), $revision);
        session()->flash('status', 'Votre consentement a été retiré.');
    }

    public function decide(string $id, int $revision, string $decision, PersonalTrust $trust): void
    {
        $trust->decideWork(Auth::user(), $id, $revision, $decision, $this->roles[$id] ?? '', ($this->consents[$id] ?? false) === true);
        unset($this->roles[$id],$this->consents[$id]);
        session()->flash('status', 'Référence mise à jour.');
    }

    public function more(): void
    {
        $this->limit = min(200, max(20, $this->limit) + 20);
    }

    public function render(PersonalTrust $trust)
    {
        abort_unless(Auth::user()->isActive(), 403);
        $limit = min(200, max(20, $this->limit));
        $user = Auth::user();

        return view('livewire.mes-attestations', ['user' => $user, 'identity' => IdentityVerification::where('user_id', $user->id)->first(), 'currentIdentity' => $trust->identity($user), 'works' => WorkAttestation::where(fn ($q) => $q->where('author_id', $user->id)->orWhere('attester_id', $user->id))->with(['publication', 'author.profile', 'attester.profile'])->latest('updated_at')->limit($limit)->get(), 'reports' => TrustReport::where('reporter_id', $user->id)->latest()->limit($limit)->get()]);
    }
}
