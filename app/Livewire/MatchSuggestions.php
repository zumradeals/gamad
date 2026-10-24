<?php

namespace App\Livewire;

use App\Models\MatchSuggestion;
use App\Services\Matching;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class MatchSuggestions extends Component
{
    public int $limit = 10;

    public ?string $status = null;

    public function more(): void
    {
        $this->limit = min(100, $this->limit + 10);
    }

    public function ignore(string $id, Matching $matching): void
    {
        abort_unless(Str::isUuid($id), 404);
        $matching->ignore(Auth::user(), MatchSuggestion::findOrFail($id));
        $this->status = 'Cette piste a été retirée de vos suggestions.';
    }

    public function render(Matching $matching)
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);
        $this->limit = max(10, min(100, $this->limit));
        $preferences = $matching->preferences($user->ensureProfile());
        $items = $preferences['enabled'] ? $matching->suggestions($user, $this->limit) : collect();

        return view('livewire.match-suggestions', ['items' => $items->take($this->limit), 'preferences' => $preferences, 'hasMore' => $items->count() > $this->limit && $this->limit < 100]);
    }
}
