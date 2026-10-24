<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Models\Publication;
use App\Models\WorkAttestation;
use App\Services\PersonalTrust;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AttesterRealisation extends Component
{
    #[Locked]
    public string $publicationId;

    public string $attesterId = '';

    public string $claim = '';

    public string $performedOn = '';

    public function mount(Publication $publication): void
    {
        $this->publicationId = $publication->id;
        Gate::authorize('view', $publication);
    }

    private function publication(): Publication
    {
        $p = Publication::findOrFail($this->publicationId);
        Gate::authorize('view', $p);
        abort_unless($p->type === PublicationType::PersonalWork && ! $p->zumra_id, 404);

        return $p;
    }

    public function request(PersonalTrust $trust): void
    {
        abort_unless(Auth::user()?->isActive(), 403);
        $trust->requestWork(Auth::user(), $this->publication(), $this->attesterId, $this->claim, $this->performedOn);
        $this->reset('attesterId', 'claim', 'performedOn');
        session()->flash('trust-status', 'La personne sollicitée pourra accepter ou refuser dans ses attestations.');
    }

    public function render(PersonalTrust $trust)
    {
        $post = $this->publication();
        $owner = Auth::id() === $post->author_id;

        return view('livewire.attester-realisation', ['post' => $post, 'isOwner' => $owner, 'candidates' => $owner && ! $post->isHidden() ? $trust->candidates(Auth::user()) : collect(), 'references' => WorkAttestation::visibleTo(Auth::user())->where('publication_id', $post->id)->with(['attester.profile', 'author.profile'])->latest('confirmed_at')->limit(3)->get()]);
    }
}
