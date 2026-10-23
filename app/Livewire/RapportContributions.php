<?php

namespace App\Livewire;

use App\Services\Contributions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Rapport public du fonds de contribution (Lot 6c, D-168) : ouvert à tous, sans aucun nom de personne ni montant individuel.
 * Il n'existe pas tant que les portefeuilles sont fermés.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Rapport du fonds de contribution')]
class RapportContributions extends Component
{
    public function render(Contributions $contributions)
    {
        return view('livewire.rapport-contributions', ['report' => $contributions->publicReport()]);
    }
}
