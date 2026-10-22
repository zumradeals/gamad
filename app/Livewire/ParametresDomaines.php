<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Services\ReferenceAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Paramètres › Domaines (D-069) : ajouter, renommer, désactiver. Réservé au super administrateur ; chaque acte est journalisé. */
#[Layout('components.layouts.app')]
#[Title('Domaines')]
class ParametresDomaines extends Component
{
    public string $newName = '';

    public ?string $editingId = null;

    public string $editName = '';

    public ?string $status = null;

    public function mount(): void
    {
        Gate::authorize('super-admin');
    }

    public function add(ReferenceAdmin $admin): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');

        $this->resolve(fn () => $admin->create(Auth::user(), 'domain', ['name' => $this->newName]), 'newName');

        $this->newName = '';
        $this->status = 'Domaine ajouté.';
    }

    public function startEdit(string $id): void
    {
        Gate::authorize('super-admin');

        $this->editingId = $id;
        $this->editName = Domain::query()->findOrFail($id)->name;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editName');
        $this->resetErrorBag();
    }

    public function rename(ReferenceAdmin $admin): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');

        $domain = Domain::query()->findOrFail($this->editingId);
        $changed = $this->resolve(fn () => $admin->rename(Auth::user(), $domain, $this->editName), 'editName');

        $this->reset('editingId', 'editName');
        $this->status = $changed ? 'Domaine renommé.' : 'Aucune modification.';
    }

    public function toggle(ReferenceAdmin $admin, string $id): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');

        $domain = Domain::query()->findOrFail($id);
        $admin->setActive(Auth::user(), $domain, ! $domain->active);

        $this->status = $domain->fresh()->active ? 'Domaine réactivé.' : 'Domaine désactivé.';
    }

    public function render()
    {
        return view('livewire.parametres-domaines', [
            'domains' => Domain::query()->ordered()->get(),
            'usage' => DB::table('domain_profile')->selectRaw('domain_id, count(*) as total')->groupBy('domain_id')->pluck('total', 'domain_id'),
        ]);
    }

    /** Les erreurs de validation du service sont rattachées au champ de la page. */
    private function resolve(callable $action, string $field): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([$field => $e->validator->errors()->first()]);
        }
    }
}
