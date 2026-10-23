<?php

namespace App\Livewire;

use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\Wallets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portefeuille ZAHAB (Lot 6, D-164) : le solde et les mouvements de la personne, ou de sa ZUMRA pour ses responsables. Rien d'autre
 * à faire ici tant que les recharges et les contributions ne sont pas construites.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Portefeuille')]
class PagePortefeuille extends Component
{
    use WithPagination;

    public ?Zumra $zumra = null;

    public function mount(Wallets $wallets, ?Zumra $zumra = null): void
    {
        $this->zumra = $zumra;
        Gate::authorize('view', $this->wallet($wallets));
    }

    public function render(Wallets $wallets)
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        return view('livewire.page-portefeuille', [
            'wallet' => $wallet,
            'balance' => $wallets->balance($wallet),
            'entries' => $wallet->entries()->paginate(20),
        ]);
    }

    /**
     * Le portefeuille existe dès qu'on l'ouvre : il se crée à la première visite d'un titulaire autorisé. Pour une ZUMRA, la
     * vérification passe avant la création, pour qu'une personne étrangère ne puisse pas en fabriquer.
     */
    private function wallet(Wallets $wallets): Wallet
    {
        if ($this->zumra) {
            abort_unless(in_array($this->zumra->getKey(), Auth::user()->managedZumraIds(), true), 404);

            return $wallets->forZumra($this->zumra);
        }

        return $wallets->forPerson(Auth::user());
    }
}
