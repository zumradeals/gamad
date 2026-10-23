<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Country;
use App\Services\ReferenceAdmin;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Paramètres › Territoires (D-068) : pays et villes. Ajouter, renommer, désactiver ; jamais supprimer. Chaque acte est journalisé. */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Territoires')]
class ParametresTerritoires extends Component
{
    public string $newCountry = '';

    public string $cityCountryId = '';

    public string $newCity = '';

    public string $newCityArea = '';

    /** « country » ou « city », avec l'identifiant en cours de modification. */
    public ?string $editingType = null;

    public ?string $editingId = null;

    public string $editName = '';

    public ?string $status = null;

    public function mount(): void
    {
        Gate::authorize('super-admin');

        $this->cityCountryId = (string) Country::query()->orderBy('sort_order')->orderBy('name')->value('id');
    }

    public function addCountry(ReferenceAdmin $admin): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');
        $this->resetErrorBag();

        $this->resolve(fn () => $admin->create(Auth::user(), 'country', ['name' => $this->newCountry]), 'newCountry');

        $this->newCountry = '';
        $this->status = 'Pays ajouté.';
    }

    public function addCity(ReferenceAdmin $admin): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');
        $this->resetErrorBag();

        $this->resolve(fn () => $admin->create(Auth::user(), 'city', [
            'name' => $this->newCity,
            'country_id' => $this->cityCountryId,
            'area' => $this->newCityArea,
        ]), 'newCity', ['country_id' => 'cityCountryId', 'area' => 'newCityArea']);

        $this->reset('newCity', 'newCityArea');
        $this->status = 'Ville ajoutée.';
    }

    public function startEdit(string $type, string $id): void
    {
        Gate::authorize('super-admin');
        abort_unless(in_array($type, ['country', 'city'], true), 404);

        $this->editingType = $type;
        $this->editingId = $id;
        $this->editName = $this->find($type, $id)->name;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingType', 'editingId', 'editName');
        $this->resetErrorBag();
    }

    public function rename(ReferenceAdmin $admin): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');
        $this->resetErrorBag();

        $item = $this->find((string) $this->editingType, (string) $this->editingId);
        $changed = $this->resolve(fn () => $admin->rename(Auth::user(), $item, $this->editName), 'editName');

        $this->reset('editingType', 'editingId', 'editName');
        $this->status = $changed ? 'Nom modifié.' : 'Aucune modification.';
    }

    public function toggle(ReferenceAdmin $admin, string $type, string $id): void
    {
        Gate::authorize('super-admin');
        $this->reset('status');
        $this->resetErrorBag();

        $item = $this->find($type, $id);
        $admin->setActive(Auth::user(), $item, ! $item->active);

        $this->status = $item->fresh()->active ? 'Réactivé.' : 'Désactivé.';
    }

    public function render()
    {
        return view('livewire.parametres-territoires', [
            'countries' => Country::query()->orderBy('sort_order')->orderBy('name')->get(),
            'cities' => ! Str::isUuid($this->cityCountryId) ? collect() : City::query()->where('country_id', $this->cityCountryId)->get()
                ->sortBy(fn (City $city) => Normalizer::key($city->label()))->values(),
            'cityUsage' => DB::table('profiles')->selectRaw('city_id, count(*) as total')->whereNotNull('city_id')->groupBy('city_id')->pluck('total', 'city_id'),
        ]);
    }

    private function find(string $type, string $id): Country|City
    {
        // Un identifiant renvoyé par le navigateur n'est jamais cru : qui n'est pas un identifiant donne 404.
        abort_unless(Str::isUuid($id), 404);

        return match ($type) {
            'country' => Country::query()->findOrFail($id),
            'city' => City::query()->findOrFail($id),
            default => abort(404),
        };
    }

    /**
     * Les erreurs de validation du service sont rattachées aux champs de la page.
     *
     * @param  array<string, string>  $map  champ du service => champ de la page
     */
    private function resolve(callable $action, string $default, array $map = []): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            $field = array_key_first($e->errors());

            throw ValidationException::withMessages([($map[$field] ?? $default) => $e->validator->errors()->first()]);
        }
    }
}
