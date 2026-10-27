<?php

namespace App\Livewire;

use App\Enums\ZumraFormat;
use App\Enums\ZumraJoinMode;
use App\Enums\ZumraSphere;
use App\Livewire\Concerns\GuardsZumraModule;
use App\Models\City;
use App\Models\Domain;
use App\Models\Zumra;
use App\Services\Zumras;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Créer une ZUMRA, ou modifier sa mission, sa ville et son format (D-114 : le nom et le domaine ne changent pas). Écran sobre,
 * sans maquette, construit avec les composants existants.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
class FormulaireZumra extends Component
{
    use GuardsZumraModule;

    #[Locked]
    public ?string $zumraId = null;

    public string $name = '';

    public string $domain_id = '';

    public string $city_id = '';

    public string $format = '';

    public string $mission = '';

    public string $sphere = '';

    public string $join_mode = 'open';

    public function mount(?Zumra $zumra = null): void
    {
        if ($zumra?->exists) {
            Gate::authorize('update', $zumra);

            $this->zumraId = $zumra->id;
            $this->name = $zumra->name;
            $this->domain_id = $zumra->domain_id;
            $this->city_id = (string) $zumra->city_id;
            $this->format = $zumra->format->value;
            $this->mission = $zumra->mission;
            $this->join_mode = $zumra->join_mode->value;

            return;
        }

        Gate::authorize('create', Zumra::class);
    }

    public function save(Zumras $zumras)
    {
        $this->resetErrorBag();

        if ($this->zumraId) {
            $zumra = $this->zumra();
            $zumras->update(Auth::user(), $zumra, ['city_id' => $this->city_id, 'format' => $this->format, 'mission' => $this->mission, 'join_mode' => $this->join_mode]);
            session()->flash('status', 'ZUMRA mise à jour.');

            return $this->redirect(route('zumras.show', $zumra), navigate: false);
        }

        $zumra = $zumras->create(Auth::user(), ['name' => $this->name, 'domain_id' => $this->domain_id, 'city_id' => $this->city_id, 'format' => $this->format, 'mission' => $this->mission, 'sphere' => $this->sphere, 'join_mode' => $this->join_mode]);
        session()->flash('status', 'ZUMRA créée. Vous en êtes le fondateur.');

        return $this->redirect(route('zumras.show', $zumra), navigate: false);
    }

    public function render()
    {
        $editing = $this->zumraId !== null;
        $zumra = $editing ? $this->zumra() : null;

        return view('livewire.formulaire-zumra', [
            'editing' => $editing,
            'zumra' => $zumra,
            'domains' => Domain::query()->active()->ordered()->get(),
            'cities' => City::query()
                ->where(fn (Builder $query) => $query->where('active', true)->when($zumra?->city_id, fn (Builder $q, $id) => $q->orWhere('id', $id)))
                ->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values(),
            'formats' => ZumraFormat::cases(),
            'spheres' => ZumraSphere::cases(),
            'joinModes' => ZumraJoinMode::cases(),
            'nameMax' => Zumra::NAME_MAX,
            'missionMax' => Zumra::MISSION_MAX,
        ])->title($editing ? 'Modifier la ZUMRA' : 'Créer une ZUMRA');
    }

    private function zumra(): Zumra
    {
        $zumra = Zumra::query()->with('domain')->findOrFail($this->zumraId);

        Gate::authorize('update', $zumra);

        return $zumra;
    }
}
