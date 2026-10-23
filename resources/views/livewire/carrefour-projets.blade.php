@php
    $n = $projects->total();
    $summary = $hasFilters ? ($n > 1 ? $n.' projets correspondent' : $n.' projet correspond').' à cette recherche' : $n.' '.($n > 1 ? 'projets' : 'projet');
@endphp

<div class="flex flex-col gap-4">
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[22px] pt-3.5 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-bold tracking-[1.5px] text-accent">CARREFOUR</span>
            <h1 class="text-[28px] font-extrabold leading-tight tracking-[-0.5px]">Projets</h1>
            <p class="text-[14px] leading-normal text-[#d6e0e8]">Des actions concrètes menées par des ZUMRA : où elles en sont, et ce qu’elles veulent obtenir.</p>
        </div>

        <div class="flex min-h-12 items-center gap-2.5 rounded-button bg-card px-3.5 text-muted focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-white">
            <x-icon name="search" :size="20" />
            <label for="recherche" class="sr-only">Rechercher un projet par son nom ou son but</label>
            <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off"
                   placeholder="Un nom ou un but"
                   class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] text-ink outline-none placeholder:text-muted">
        </div>
    </header>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div class="flex flex-col gap-1">
            <label for="filtre-etat" class="sr-only">État</label>
            <select id="filtre-etat" wire:model.live="state" class="min-h-11 w-full rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                <option value="">Tous les états</option>
                @foreach ($states as $etat)
                    <option value="{{ $etat->value }}">{{ $etat->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label for="filtre-domaine" class="sr-only">Domaine</label>
            <select id="filtre-domaine" wire:model.live="domain" class="min-h-11 w-full rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                <option value="">Tous les domaines</option>
                @foreach ($domains as $domaine)
                    <option value="{{ $domaine->id }}">{{ $domaine->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label for="filtre-ville" class="sr-only">Ville</label>
            <select id="filtre-ville" wire:model.live="city" class="min-h-11 w-full rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                <option value="">Toutes les villes</option>
                @foreach ($cities as $ville)
                    <option value="{{ $ville->id }}">{{ $ville->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div wire:loading.delay class="text-[13px] font-semibold text-muted" role="status">Recherche en cours…</div>

    <div class="flex items-baseline justify-between gap-2">
        <h2 class="text-[20px] font-extrabold">Tous les projets</h2>
        <span class="text-[12px] font-semibold text-muted">Par ordre alphabétique</span>
    </div>
    <p class="-mt-2 text-[13px] font-bold text-muted" role="status" aria-live="polite">{{ $summary }}</p>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($projects as $project)
        @php
            $zumra = $project->zumra;
            $meta = collect([$zumra->domain->name, $zumra->city?->label()])->filter()->implode(' · ');
        @endphp
        <article wire:key="projet-{{ $project->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $project->state->badgeClasses() }}">{{ $project->state->label() }}</span>
            <a href="{{ route('projets.show', $project) }}" class="inline-flex min-h-11 items-center break-words text-[17px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $project->name }}</a>
            <p class="-mt-2 whitespace-pre-line break-words text-[14px] leading-normal">{{ \Illuminate\Support\Str::limit($project->goal, 180) }}</p>
            <a href="{{ route('zumras.show', $zumra) }}" class="flex min-h-11 items-center gap-2.5 text-ink no-underline hover:text-ink">
                <x-zumra-initials :zumra="$zumra" :size="36" />
                <span class="flex min-w-0 flex-col leading-tight">
                    <span class="break-words text-[13px] font-extrabold">{{ $zumra->name }}</span>
                    <span class="text-[12px] text-muted">{{ $meta }}</span>
                </span>
            </a>
            <x-button :href="route('projets.show', $project)" variant="secondary">Voir le projet</x-button>
        </article>
    @empty
        <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 md:col-span-full">
            <h2 id="vide" class="text-[18px] font-extrabold">{{ $hasFilters ? 'Aucun projet ne correspond à cette recherche.' : 'Aucun projet pour l’instant.' }}</h2>
            @if ($hasFilters)
                <p class="text-muted">Essayez un autre mot, ou élargissez la recherche.</p>
                <x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
            @else
                <p class="text-muted">Un projet naît dans une ZUMRA : son fondateur ou un responsable le crée depuis la ZUMRA.</p>
                <x-button :href="route('zumras.index')">Voir les ZUMRA</x-button>
            @endif
        </section>
    @endforelse
    </div>

    @if ($projects->hasPages())
        <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$projects->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $projects->currentPage() }} sur {{ $projects->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $projects->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
