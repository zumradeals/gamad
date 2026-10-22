<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Territoires</h1>
        <p class="text-muted">Les pays et les villes que chaque personne peut choisir. On ajoute, on renomme, on désactive : on ne supprime jamais. Chaque acte est inscrit au journal d’administration.</p>
    </div>

    @if ($status)
        <x-status>{{ $status }}</x-status>
    @endif

    <section aria-labelledby="pays" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="pays" class="text-[18px] font-extrabold">Pays</h2>

        <ul class="flex flex-col divide-y divide-line-soft">
            @foreach ($countries as $country)
                <li wire:key="pays-{{ $country->id }}" class="flex flex-col gap-2 py-3">
                    @if ($editingType === 'country' && $editingId === $country->id)
                        <form wire:submit="rename" class="flex flex-col gap-2">
                            <x-wire-field name="editName" label="Nouveau nom" autocomplete="off" maxlength="100" />
                            <div class="flex gap-2">
                                <x-button class="flex-1">Enregistrer</x-button>
                                <x-button type="button" variant="secondary" wire:click="cancelEdit" class="flex-1">Annuler</x-button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span @class(['text-[15px] font-bold', 'text-muted line-through' => ! $country->active])>{{ $country->name }}</span>
                            @unless ($country->active)
                                <span class="text-[12px] font-bold text-muted">désactivé</span>
                            @endunless
                        </div>
                        <div class="flex gap-2">
                            <x-button type="button" variant="secondary" wire:click="startEdit('country', '{{ $country->id }}')" class="flex-1 !text-[14px]">Renommer</x-button>
                            <x-button type="button" variant="secondary" wire:click="toggle('country', '{{ $country->id }}')" class="flex-1 !text-[14px]">{{ $country->active ? 'Désactiver' : 'Réactiver' }}</x-button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <form wire:submit="addCountry" class="flex flex-col gap-3 border-t border-line-soft pt-3">
            <x-wire-field name="newCountry" label="Ajouter un pays" autocomplete="off" maxlength="100" />
            <x-button wire:loading.attr="disabled" wire:target="addCountry">Ajouter le pays</x-button>
        </form>
    </section>

    <section aria-labelledby="villes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="villes" class="text-[18px] font-extrabold">Villes</h2>

        <div class="flex flex-col gap-1.5">
            <label for="cityCountryId" class="text-[13px] font-semibold">Pays</label>
            <select id="cityCountryId" wire:model.live="cityCountryId" class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3 text-[15px]">
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </select>
            @error('cityCountryId')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-[13px] font-semibold text-muted">{{ $cities->count() }} {{ $cities->count() > 1 ? 'villes' : 'ville' }}</p>

        <ul class="flex flex-col divide-y divide-line-soft">
            @foreach ($cities as $city)
                <li wire:key="ville-{{ $city->id }}" class="flex flex-col gap-2 py-3">
                    @if ($editingType === 'city' && $editingId === $city->id)
                        <form wire:submit="rename" class="flex flex-col gap-2">
                            <x-wire-field name="editName" label="Nouveau nom" autocomplete="off" maxlength="100"
                                          :hint="$city->area ? 'Cette ville est affichée « '.$city->area.', … ».' : null" />
                            <div class="flex gap-2">
                                <x-button class="flex-1">Enregistrer</x-button>
                                <x-button type="button" variant="secondary" wire:click="cancelEdit" class="flex-1">Annuler</x-button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span @class(['text-[15px] font-bold', 'text-muted line-through' => ! $city->active])>{{ $city->label() }}</span>
                            @unless ($city->active)
                                <span class="text-[12px] font-bold text-muted">désactivée</span>
                            @endunless
                            <span class="text-[12px] text-muted">{{ $cityUsage[$city->id] ?? 0 }} {{ ($cityUsage[$city->id] ?? 0) > 1 ? 'personnes' : 'personne' }}</span>
                        </div>
                        <div class="flex gap-2">
                            <x-button type="button" variant="secondary" wire:click="startEdit('city', '{{ $city->id }}')" class="flex-1 !text-[14px]">Renommer</x-button>
                            <x-button type="button" variant="secondary" wire:click="toggle('city', '{{ $city->id }}')" class="flex-1 !text-[14px]">{{ $city->active ? 'Désactiver' : 'Réactiver' }}</x-button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <form wire:submit="addCity" class="flex flex-col gap-3 border-t border-line-soft pt-3">
            <h3 class="text-[15px] font-extrabold">Ajouter une ville à ce pays</h3>
            <x-wire-field name="newCity" label="Nom de la ville" autocomplete="off" maxlength="100" />
            <x-wire-field name="newCityArea" label="Rattachée à (facultatif)" autocomplete="off" maxlength="100"
                          hint="Pour une commune d’un district : « Abidjan » donne « Abidjan, Cocody »." />
            <x-button wire:loading.attr="disabled" wire:target="addCity">Ajouter la ville</x-button>
        </form>
    </section>
</div>
