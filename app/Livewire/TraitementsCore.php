<?php

namespace App\Livewire;

use App\Services\CoreConsoleActions;
use App\Services\CoreConsoleObservations;
use App\Services\SecurityObservations;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('GAMAD Console')]
class TraitementsCore extends Component
{
    public int $hours = 24;

    public string $reason = '';

    public string $feedback = '';

    #[Locked]
    public ?string $investigationId = null;

    #[Locked]
    public string $dimension = 'target';

    public function boot(): void
    {
        abort_unless(auth()->user()?->can('super-admin'), 404);
    }

    public function setHours(int $hours): void
    {
        $this->hours = in_array($hours, [1, 24, 168], true) ? $hours : 24;
    }

    public function investigate(string $id, string $dimension = 'target'): void
    {
        abort_unless(Str::isUuid($id) && in_array($dimension, ['source', 'target'], true), 422);
        $this->investigationId = $id;
        $this->dimension = $dimension;
        $this->dispatch('console-investigation-opened');
    }

    public function closeInvestigation(): void
    {
        $this->investigationId = null;
    }

    public function retryMatching(string $id, CoreConsoleActions $actions): void
    {
        abort_unless(Str::isUuid($id), 422);
        $this->feedback = $actions->retry(auth()->user(), $id, $this->reason)['message'];
        $this->reason = '';
    }

    public function requestRunner(CoreConsoleActions $actions): void
    {
        $actions->requestRunner(auth()->user(), $this->reason);
        $this->feedback = 'Passage borné demandé après réponse. Aucune réussite de tâche n’est encore confirmée. Actualisez pour voir le passage réel.';
        $this->reason = '';
    }

    public function render(CoreConsoleObservations $observations, SecurityObservations $security)
    {
        $hours = in_array($this->hours, [1, 24, 168], true) ? $this->hours : 24;
        $data = $observations->snapshot($hours);
        $data['investigation'] = $this->investigationId === null ? null : $security->investigate(auth()->user(), $this->investigationId, $this->dimension);

        return view('livewire.traitements-core', $data);
    }
}
