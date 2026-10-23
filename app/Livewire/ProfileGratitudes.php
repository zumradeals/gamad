<?php

namespace App\Livewire;

use App\Models\Gratitude;
use App\Models\Profile;
use App\Services\Gratitudes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * « Remerciements reçus » sur le Monde d'une personne (écran 5, D-084). Visible pour qui peut voir le profil (ProfilePolicy,
 * revérifiée à chaque affichage). La personne remerciée peut masquer un remerciement de son profil, et le rétablir.
 * Aucun compteur, aucune note.
 */
class ProfileGratitudes extends Component
{
    public const SHOWN = 20;

    #[Locked]
    public string $profileId;

    public function mount(Profile $profile): void
    {
        $this->profileId = $profile->id;
    }

    public function hide(string $id, Gratitudes $gratitudes): void
    {
        $gratitudes->setHidden(Auth::user(), $this->gratitude($id), true);
    }

    public function show(string $id, Gratitudes $gratitudes): void
    {
        $gratitudes->setHidden(Auth::user(), $this->gratitude($id), false);
    }

    public function render()
    {
        $profile = Profile::query()->with('user')->findOrFail($this->profileId);

        Gate::authorize('view', $profile);

        $viewer = Auth::user();
        $isOwner = $viewer && $viewer->getKey() === $profile->user_id;

        $gratitudes = Gratitude::query()
            ->where('helper_id', $profile->user_id)
            // La personne qui remercie existe pour les autres seulement si son adresse e-mail est vérifiée (comme ses publications).
            ->whereHas('author', fn ($author) => $author->whereNotNull('email_verified_at'))
            ->when(! $isOwner, fn ($query) => $query->whereNull('hidden_at'))
            ->with(['author.profile', 'publication'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::SHOWN)->get();

        return view('livewire.profile-gratitudes', ['profile' => $profile, 'gratitudes' => $gratitudes, 'isOwner' => $isOwner, 'viewer' => $viewer]);
    }

    private function gratitude(string $id): Gratitude
    {
        abort_unless(Str::isUuid($id), 404);

        return Gratitude::query()->where('helper_id', Profile::query()->findOrFail($this->profileId)->user_id)->findOrFail($id);
    }
}
