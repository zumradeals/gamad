<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Models\Publication;
use App\Services\MatchingConditions;
use App\Services\Publications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

class FilComposer extends Component
{
    use WithFileUploads;

    public string $body = '';
    public string $kind = 'share';
    public string $visibility = Publication::PUBLIC;
    public $media = null;
    public bool $expanded = false;
    public ?string $status = null;

    public function mount(): void
    {
        $this->visibility = Auth::user()->ensureProfile()->visibility === Publication::MEMBERS
            ? Publication::MEMBERS : Publication::PUBLIC;
    }

    public function publish(Publications $publications): void
    {
        Gate::authorize('create', Publication::class);
        $this->validate([
            'kind' => ['required', 'in:share,personal_work'],
            'body' => ['required', 'string', 'max:'.Publication::BODY_MAX],
            'visibility' => ['required', 'in:public,members'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp4,webm', 'max:10240'],
        ]);
        $type = PublicationType::from($this->kind);
        $profile = Auth::user()->ensureProfile();
        $publications->publish(Auth::user(), $type, [
            'body' => $this->body,
            'domain_id' => '',
            'city_id' => (string) ($profile->city?->active ? $profile->city_id : ''),
            'visibility' => $this->visibility,
            'skills' => [],
            'media' => $this->media,
            'matching' => MatchingConditions::publicationDefaults($type),
        ]);
        $this->reset('body', 'media', 'expanded');
        $this->status = 'Votre publication est en ligne.';
        $this->dispatch('fil-publication-created');
    }

    public function render()
    {
        return view('livewire.fil-composer');
    }
}
