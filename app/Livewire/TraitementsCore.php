<?php

namespace App\Livewire;

use App\Core\Execution\Stats;
use App\Services\MatchingStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Première vue d'observation du Core : ce que l'exécution a réellement traité (file, reprises, échecs, durées) et ce que le Matching
 * a produit ou retiré. Réservée au super administrateur ; des comptes et des durées, jamais de contenu ni de personne.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Traitements du Core')]
class TraitementsCore extends Component
{
    public int $hours = 24;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('super-admin'), 404);
    }

    public function setHours(int $hours): void
    {
        $this->hours = in_array($hours, [1, 24, 168], true) ? $hours : 24;
    }

    public function render(Stats $stats, MatchingStats $matching)
    {
        abort_unless(auth()->user()?->can('super-admin'), 404);

        return view('livewire.traitements-core', [
            'queue' => $stats->queue(), 'failed' => $stats->failed(), 'runs' => $stats->runs($this->hours),
            'searches' => $matching->searches($this->hours), 'suggestions' => $matching->suggestions(),
        ]);
    }
}
