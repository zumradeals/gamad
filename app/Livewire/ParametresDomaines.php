<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Services\ReferenceAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Paramètres › Domaines (D-069) : ajouter, renommer, désactiver. Réservé au super administrateur ; chaque acte est journalisé. */
#[Layout('components.layouts.app', ['narrow' => true])]
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
        $this->resetErrorBag();

        $this->resolve(fn () => $admin->create(Auth::user(), 'domain', ['name' => $this->newName]), 'newName');

        $this->newName = '';
        $this->status = 'Domaine ajouté.';
    }

    public function startEdit(string $id): void
    {
        Gate::authorize('super-admin');

        $this->editingId = $id;
        $this->editName = $this->domain($id)->name;
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
        $this->resetErrorBag();

        $domain = $this->domain((string) $this->editingId);
        $changed = $this->resolve(fn () => $admin->rename(Auth::user(), $domain, $this->editName), 'editName');

        $this->reset('editingId', 'editName');
        $this->status = $changed ? 'Domaine renommé.' : 'Aucune modification.';
    }

    public function toggle(ReferenceAdmin $admin, string $id): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');
        $this->resetErrorBag();

        $domain = $this->domain($id);
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

    /** Un identifiant renvoyé par le navigateur n'est jamais cru : qui n'est pas un identifiant donne 404. */
    private function domain(string $id): Domain
    {
        abort_unless(Str::isUuid($id), 404);

        return Domain::query()->findOrFail($id);
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
