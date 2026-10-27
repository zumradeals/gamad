<?php

namespace App\Livewire;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\GeniusPay;
use App\Services\Payouts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reversements du Zahab Wallet (D-218), réservés au super administrateur : il voit la somme, l'opérateur, le titulaire indiqué et le numéro
 * masqué (aucun compte n'est nommé, D-170). Il lit le numéro entier le temps de verser la somme, chaque lecture étant au journal, puis marque
 * le reversement comme versé, avec sa référence, ou le refuse avec un motif : la somme revient alors au portefeuille.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Reversements')]
class ReversementsAdministration extends Component
{
    public string $tab = 'a-verser';

    /** Numéros lus pendant cette session de page : identifiant du reversement => numéro. */
    public array $revealed = [];

    public array $references = [];

    public array $reasons = [];

    public ?string $status = null;

    public function mount(): void
    {
        Gate::authorize('super-admin');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'traites' ? 'traites' : 'a-verser';
    }

    public function reveal(string $id, Payouts $payouts): void
    {
        $payout = $this->payout($id);
        $this->revealed[$id] = $payouts->revealPhone(Auth::user(), $payout);
    }

    public function markPaid(string $id, Payouts $payouts): void
    {
        $payouts->markPaid(Auth::user(), $this->payout($id), $this->references[$id] ?? null);
        unset($this->revealed[$id], $this->references[$id]);
        $this->status = 'Reversement marqué comme versé. La personne en est prévenue.';
    }

    public function decline(string $id, Payouts $payouts): void
    {
        $payouts->decline(Auth::user(), $this->payout($id), $this->reasons[$id] ?? null);
        unset($this->revealed[$id], $this->reasons[$id]);
        $this->status = 'Reversement refusé : la somme est revenue dans le portefeuille.';
    }

    public function render(Payouts $payouts, GeniusPay $provider)
    {
        Gate::authorize('super-admin');

        $open = $this->tab === 'a-verser';
        $mode = $provider->mode();

        return view('livewire.reversements-administration', [
            'payouts' => Payout::query()->with('wallet:id,owner_type')->where('status', $open ? '=' : '<>', PayoutStatus::Requested->value)
                ->orderBy('created_at', $open ? 'asc' : 'desc')->limit(50)->get(),
            'totals' => $payouts->totals($mode),
            'mode' => $mode,
            'operators' => Payout::OPERATORS,
            'openCount' => Payout::query()->where('status', PayoutStatus::Requested->value)->count(),
        ]);
    }

    private function payout(string $id): Payout
    {
        Gate::authorize('super-admin');
        abort_unless(Str::isUuid($id), 404);

        return Payout::query()->findOrFail($id);
    }
}
