@php
    $n = $projects->total();
    $summary = $hasFilters ? ($n > 1 ? $n.' projets correspondent' : $n.' projet correspond').' à cette recherche' : $n.' '.($n > 1 ? 'projets' : 'projet');
    $selectedState = \App\Enums\ProjectState::tryFrom($state);
@endphp

<div class="project-directory flex flex-col gap-6">
    <header class="project-discovery-hero">
        <div class="flex min-w-0 flex-col gap-4">
            <span class="text-[12px] font-extrabold tracking-[2px]">CARREFOUR PROJETS</span>
            <h1 class="zumra-discovery-title">Des idées aux actions concrètes.</h1>
            <p class="max-w-xl text-[17px] leading-relaxed text-muted">Découvrez les projets portés par les ZUMRA, leur objectif et où ils en sont.</p>
        </div>
        <x-projects-illustration class="mx-auto block h-auto w-full max-w-[520px]" />
    </header>

    <section aria-label="Rechercher un projet" class="flex flex-col gap-3">
        <form wire:submit="$refresh" class="project-searchbar">
            <div class="flex min-w-0 items-center gap-3 px-3">
                <x-icon name="search" :size="22" class="shrink-0" />
                <label for="recherche" class="sr-only">Rechercher un projet par son nom ou son but</label>
                <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Rechercher un nom ou un objectif…" class="min-h-12 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted">
            </div>
            <div>
                <label for="filtre-domaine" class="sr-only">Domaine</label>
                <select id="filtre-domaine" wire:model.live="domain" class="zumra-filter">
                    <option value="">Tous les domaines</option>
                    @foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="filtre-ville" class="sr-only">Ville</label>
                <x-city-picker id="filtre-ville" model="city" :value="$city" :live="true" placeholder="Toutes les villes" class="zumra-filter flex items-center" />
            </div>
            <x-button type="submit" variant="accent" class="gap-2"><x-icon name="search" :size="18" />Rechercher</x-button>
        </form>
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line">
            <div role="group" aria-label="Filtrer les projets par état" class="flex flex-wrap gap-x-4 gap-y-1">
                <button type="button" wire:click="$set('state', '')" aria-pressed="{{ $selectedState === null ? 'true' : 'false' }}" @class(['project-state-filter', 'border-ink font-extrabold' => $selectedState === null, 'border-transparent font-semibold text-muted' => $selectedState !== null])>Tous les projets</button>
                @foreach ($states as $etat)
                    <button type="button" wire:click="$set('state', '{{ $etat->value }}')" aria-pressed="{{ $selectedState === $etat ? 'true' : 'false' }}" @class(['project-state-filter', 'border-ink font-extrabold' => $selectedState === $etat, 'border-transparent font-semibold text-muted' => $selectedState !== $etat])>
                        <span aria-hidden="true" class="size-2.5 rounded-full {{ $etat->badgeClasses() }}" style="background-color: currentColor"></span>{{ $etat->label() }}
                    </button>
                @endforeach
            </div>
            @if ($hasFilters)<button type="button" wire:click="clearFilters" class="min-h-11 text-[14px] font-semibold text-link underline">Réinitialiser</button>@endif
        </div>
    </section>

    <section aria-labelledby="tous-projets" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="tous-projets" class="text-[26px] font-extrabold leading-tight">Des projets à découvrir</h2><p class="mt-2 text-[14px] font-semibold text-muted" role="status" aria-live="polite">{{ $summary }}</p></div>
            <span class="text-[13px] font-semibold text-muted">Par ordre alphabétique</span>
        </div>
        <div wire:loading.delay class="text-[14px] font-semibold text-muted" role="status">Recherche en cours…</div>
        <div class="grid gap-5 lg:grid-cols-2" wire:loading.class="opacity-60">
            @forelse ($projects as $project)
                @php
                    $zumra = $project->zumra;
                    $meta = collect([$zumra->domain->name, $zumra->city?->label()])->filter()->implode(' · ');
                @endphp
                <article wire:key="projet-{{ $project->id }}" class="flex min-w-0 flex-col gap-5 rounded-card border border-line bg-card p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <span aria-hidden="true" class="flex size-14 shrink-0 items-center justify-center rounded-card {{ $project->state->badgeClasses() }}"><x-icon name="project" :size="30" /></span>
                        <div class="flex min-w-0 flex-1 flex-col items-start gap-2">
                            <span class="rounded-chip px-3 py-1 text-[13px] font-bold {{ $project->state->badgeClasses() }}">{{ $project->state->label() }}</span>
                            <h3 class="max-w-full text-[21px] font-extrabold leading-tight"><a href="{{ route('projets.show', $project) }}" class="block min-h-11 content-center [overflow-wrap:anywhere] text-ink no-underline hover:underline">{{ $project->name }}</a></h3>
                        </div>
                    </div>
                    <p class="whitespace-pre-line break-words text-[16px] leading-relaxed">{{ \Illuminate\Support\Str::limit($project->goal, 180) }}</p>
                    <div class="mt-auto flex flex-wrap items-end justify-between gap-4 border-t border-line-soft pt-4">
                        <div class="min-w-0 flex-1 basis-48">
                            <p class="mb-2 text-[11px] font-extrabold tracking-wide text-muted">PORTÉ PAR</p>
                            <a href="{{ route('zumras.show', $zumra) }}" class="flex min-h-11 items-center gap-3 text-ink no-underline hover:underline">
                                <x-zumra-initials :zumra="$zumra" :size="44" />
                                <span class="min-w-0"><span class="block break-words text-[14px] font-extrabold">{{ $zumra->name }}</span><span class="mt-1 block text-[13px] leading-relaxed text-muted">{{ $meta }}</span></span>
                            </a>
                        </div>
                        <x-button :href="route('projets.show', $project)" class="gap-2 !px-4 !text-[14px]">Voir le projet<x-icon name="arrow-right" :size="18" /></x-button>
                    </div>
                </article>
            @empty
                <section aria-labelledby="vide" class="flex flex-col items-center gap-4 rounded-card border border-line bg-card px-5 py-10 text-center lg:col-span-full">
                    <span aria-hidden="true" class="flex size-20 items-center justify-center rounded-full bg-type-call-bg text-ink"><x-icon name="project" :size="38" /></span>
                    <h3 id="vide" class="text-[24px] font-extrabold leading-tight">{{ $hasFilters ? 'Aucun projet ne correspond à cette recherche.' : 'Les premières actions se préparent.' }}</h3>
                    @if ($hasFilters)
                        <p class="max-w-xl text-[16px] leading-relaxed text-muted">Essayez un autre mot, ou élargissez la recherche.</p>
                        <x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
                    @else
                        <p class="max-w-xl text-[16px] leading-relaxed text-muted">Aucun projet pour l’instant. Découvrez les ZUMRA et les communautés qui feront naître les prochaines actions.</p>
                        <p class="max-w-xl text-[14px] leading-relaxed text-muted">Un projet naît dans une ZUMRA : son fondateur ou un responsable le crée depuis la ZUMRA.</p>
                        <x-button :href="route('zumras.index')" variant="accent" class="gap-2">Découvrir les ZUMRA<x-icon name="arrow-right" :size="18" /></x-button>
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
    </section>

    @if ($projects->isNotEmpty())
        <section aria-labelledby="projet-zumra" class="flex flex-wrap items-center justify-between gap-5 rounded-card bg-ink p-6 text-white">
            <div class="flex min-w-0 flex-1 basis-80 items-start gap-4"><x-icon name="community" :size="42" class="shrink-0 text-accent" /><div><h2 id="projet-zumra" class="text-[23px] font-extrabold leading-tight">Un projet commence dans une ZUMRA.</h2><p class="mt-2 text-[15px] leading-relaxed text-white/90">Son fondateur ou un responsable peut le créer depuis la communauté.</p></div></div>
            <x-button :href="route('zumras.index')" variant="accent" class="gap-2">Explorer les ZUMRA<x-icon name="arrow-right" :size="18" /></x-button>
        </section>
    @endif
</div>
