@php
    $guest = ! auth()->check();
    $n = $opportunities->total();
    $summary = $n.' '.($n > 1 ? 'opportunités trouvées' : 'opportunité trouvée');
    $hasFilters = $activeFilters > 0;
@endphp
<div class="opportunity-catalog min-w-0" x-data="{ filters: false }" x-on:keydown.escape.window="filters = false">
    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[260px_minmax(0,1fr)] xl:grid-cols-[270px_minmax(0,1fr)_330px]">
        <header class="min-w-0 rounded-card border border-line bg-soft-blue p-5 col-span-full">
            <div class="flex min-w-0 flex-wrap items-center gap-4">
                <span class="flex size-16 shrink-0 items-center justify-center rounded-card bg-accent text-ink"><x-icon name="project" :size="34" /></span>
                <div class="min-w-0 flex-1">
                    <h1 class="text-[28px] font-extrabold leading-tight">Opportunités</h1>
                    <p class="mt-1 max-w-xl text-[14px] leading-normal text-muted">Trouvez un emploi, un stage, une mission ou une collaboration avec les entreprises GAMAD.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($publishCompany)<x-button :href="route('entreprises.opportunities', $publishCompany)" variant="accent">Publier une opportunité</x-button>@endif
                    <x-button :href="route('entreprises.index')" variant="secondary" class="border-accent"><x-icon name="project" :size="18" class="mr-2" />Voir les entreprises</x-button>
                </div>
            </div>
        </header>

        <aside aria-label="Recherche et filtres" class="min-w-0 rounded-card border border-line bg-card p-4">
            <div class="flex items-center gap-2">
                <div class="flex min-w-0 flex-1 items-center gap-2 rounded-button border border-line bg-card px-3 focus-within:border-link">
                    <x-icon name="search" :size="18" />
                    <label for="opportunite-recherche" class="sr-only">Rechercher une opportunité</label>
                    <input id="opportunite-recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Rechercher…" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[14px] outline-none">
                </div>
                <button type="button" x-on:click="filters = true" aria-haspopup="dialog" aria-label="Filtres{{ $activeFilters > 0 ? ' ('.$activeFilters.' actifs)' : '' }}" class="flex size-11 shrink-0 items-center justify-center rounded-button border border-line lg:hidden"><x-icon name="menu" :size="20" /></button>
            </div>

            {{-- Panneau de filtres : colonne à gauche sur ordinateur, fenêtre plein écran sur téléphone. --}}
            <div id="opportunite-filtres" x-bind:class="filters ? 'fixed inset-0 z-40 block overflow-y-auto bg-card p-5' : 'hidden lg:block'" x-bind:role="filters ? 'dialog' : null" x-bind:aria-modal="filters ? 'true' : null" aria-label="Filtres des opportunités">
                <div class="mb-4 flex items-center justify-between lg:hidden"><h2 class="text-[20px] font-extrabold">Filtres</h2><button type="button" x-on:click="filters = false" aria-label="Fermer les filtres" class="flex size-11 items-center justify-center rounded-button border border-line"><x-icon name="close" :size="20" /></button></div>
                <fieldset class="mt-4">
                    <legend class="mb-2 text-[14px] font-extrabold">Type d’opportunité</legend>
                    <div class="space-y-0.5">
                        <button type="button" wire:click="setKind('')" aria-pressed="{{ $kind === '' ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center gap-2 rounded-button px-2 text-left text-[14px]">
                            <span class="flex size-[18px] shrink-0 items-center justify-center rounded-chip border {{ $kind === '' ? 'border-link bg-link text-white' : 'border-line' }}">@if ($kind === '')<x-icon name="check" :size="12" :stroke="3" />@endif</span>
                            <span class="flex-1">Tous</span><span class="text-muted">({{ $totalCount }})</span>
                        </button>
                        @foreach ($kinds as $k)
                            <button type="button" wire:click="setKind('{{ $kind === $k->value ? '' : $k->value }}')" aria-pressed="{{ $kind === $k->value ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center gap-2 rounded-button px-2 text-left text-[14px]">
                                <span class="flex size-[18px] shrink-0 items-center justify-center rounded-chip border {{ $kind === $k->value ? 'border-link bg-link text-white' : 'border-line' }}">@if ($kind === $k->value)<x-icon name="check" :size="12" :stroke="3" />@endif</span>
                                <span class="flex-1">{{ $k->pluralLabel() }}</span><span class="text-muted">({{ $kindCounts[$k->value] ?? 0 }})</span>
                            </button>
                        @endforeach
                    </div>
                </fieldset>
                <div class="mt-4"><label for="filtre-localite" class="mb-2 block text-[14px] font-extrabold">Localité</label>
                    <div class="flex items-center gap-2 rounded-button border border-line bg-card px-3 focus-within:border-link">
                        <input id="filtre-localite" type="text" list="opportunite-villes" wire:model.live.debounce.400ms="city" maxlength="80" autocomplete="off" placeholder="Ex. : Abidjan, Yopougon" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[14px] outline-none">
                        <x-icon name="pin" :size="16" class="shrink-0 text-muted" />
                    </div>
                    <datalist id="opportunite-villes">@foreach ($cities as $c)<option value="{{ $c->name }}">{{ $c->label() }}</option>@endforeach</datalist></div>
                <div class="mt-4"><label for="filtre-domaine" class="mb-2 block text-[14px] font-extrabold">Domaine</label>
                    <select id="filtre-domaine" wire:model.live="domain" class="zumra-filter w-full"><option value="">Tous les domaines</option>@foreach ($domains as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                <div class="mt-4"><label for="filtre-date" class="mb-2 block text-[14px] font-extrabold">Date de publication</label>
                    <select id="filtre-date" wire:model.live="date" class="zumra-filter w-full">@foreach (\App\Livewire\OpportunityCatalog::DATES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <button type="button" x-on:click="filters = false" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-button bg-accent px-3 text-[15px] font-bold text-ink lg:hidden">Voir les résultats ({{ $n }})</button>
                <button type="button" wire:click="clearFilters" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-button px-3 text-[14px] font-bold text-link border border-link">Réinitialiser les filtres</button>
            </div>
        </aside>

        <div class="min-w-0 space-y-4">
            <div class="rounded-card border border-line bg-card p-4">
                <div role="tablist" aria-label="Type d’opportunité" class="-mx-1 flex max-w-full gap-2 overflow-x-auto px-1 py-1">
                    <button type="button" role="tab" aria-selected="{{ $kind === '' ? 'true' : 'false' }}" wire:click="setKind('')" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-button px-4 text-[13px] font-bold {{ $kind === '' ? 'bg-link text-white' : 'border border-line bg-card' }}">Toutes <span class="rounded-chip px-2 text-[12px] {{ $kind === '' ? 'bg-card text-link' : 'bg-soft-blue text-link' }}">{{ $totalCount }}</span></button>
                    @foreach ($kinds as $k)
                        <button type="button" role="tab" aria-selected="{{ $kind === $k->value ? 'true' : 'false' }}" wire:click="setKind('{{ $k->value }}')" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-button px-4 text-[13px] font-bold {{ $kind === $k->value ? 'bg-link text-white' : 'border border-line bg-card' }}">{{ $k->tabLabel() }} <span class="rounded-chip px-2 text-[12px] {{ $kind === $k->value ? 'bg-card text-link' : 'bg-soft-blue text-link' }}">{{ $kindCounts[$k->value] ?? 0 }}</span></button>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p role="status" aria-live="polite" class="text-[14px] font-bold">{{ $summary }}</p>
                    <label class="flex items-center gap-2 text-[13px] text-muted">Trier par :
                        <select wire:model.live="sort" class="zumra-filter !w-auto font-semibold">@foreach (\App\Livewire\OpportunityCatalog::SORTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </label>
                </div>
            </div>
            <section aria-label="Résultats" class="space-y-3" wire:loading.class="opacity-60" wire:target="query,kind,city,domain,date,sort,setKind,clearFilters,gotoPage,nextPage,previousPage">
                @forelse ($opportunities as $opportunity)
                    <x-opportunity-card wire:key="opp-{{ $opportunity->id }}" :opportunity="$opportunity" :saved="in_array($opportunity->id, $savedIds, true)" :can-act="! $guest" />
                @empty
                    <div class="rounded-card border border-line bg-card p-6">
                        <h2 class="text-[18px] font-bold">{{ $hasFilters ? 'Aucune opportunité ne correspond à cette recherche.' : 'Aucune opportunité n’est publiée pour le moment.' }}</h2>
                        <p class="mt-2 text-[14px] text-muted">{{ $hasFilters ? 'Essayez d’élargir vos critères ou de réinitialiser les filtres.' : 'Les entreprises vérifiées publient ici leurs emplois, stages, missions et collaborations. Aucun exemple fictif n’est présenté.' }}</p>
                        @if ($hasFilters)<x-button type="button" variant="secondary" wire:click="clearFilters" class="mt-3">Réinitialiser les filtres</x-button>@endif
                    </div>
                @endforelse
            </section>
            @if ($opportunities->hasPages())
                <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
                    <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$opportunities->onFirstPage()">Précédent</x-button>
                    <span class="text-[13px] text-muted">Page {{ $opportunities->currentPage() }} sur {{ $opportunities->lastPage() }}</span>
                    <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $opportunities->hasMorePages()">Suivant</x-button>
                </nav>
            @endif
            <p class="flex items-start justify-center gap-2 text-center text-[13px] text-muted"><x-icon name="shield" :size="16" class="mt-0.5 shrink-0" /> Seules les entreprises vérifiées par GAMAD publient des opportunités. Cette vérification ne garantit ni le recrutement ni les conditions proposées.</p>
        </div>

        <aside aria-label="Découvrir" class="min-w-0 space-y-4 lg:col-start-2 xl:col-start-3">
            @if ($forYou->isNotEmpty())
                <section aria-labelledby="pour-vous-titre" class="rounded-card border border-line bg-card p-4">
                    <h2 id="pour-vous-titre" class="text-[17px] font-extrabold">Selon vos capacités</h2>
                    <p class="text-[12px] text-muted">D’après les capacités de votre profil, sans score.</p>
                    <ul class="mt-3 space-y-3">@foreach ($forYou as $item)
                        <li class="min-w-0"><a href="{{ route('opportunites.show', $item) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $item->title }}</a><p class="-mt-2 text-[12px] text-muted">{{ $item->company->name }}@if ($item->reason) · {{ $item->reason }}@endif</p></li>
                    @endforeach</ul>
                </section>
            @endif
            @if ($companies->isNotEmpty())
                <section aria-labelledby="entreprises-titre" class="rounded-card border border-line bg-card p-4">
                    <h2 id="entreprises-titre" class="text-[17px] font-extrabold">Entreprises qui proposent des opportunités</h2>
                    <ul class="mt-3 space-y-3">@foreach ($companies as $company)
                        <li class="flex min-w-0 items-center gap-3"><x-company-logo :company="$company" :size="44" /><div class="min-w-0 flex-1"><a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $company->name }}</a><p class="-mt-2 text-[12px] text-muted">{{ $company->opportunities_count }} {{ $company->opportunities_count > 1 ? 'opportunités' : 'opportunité' }}</p></div></li>
                    @endforeach</ul>
                </section>
            @endif
            @if ($recent->isNotEmpty())
                <section aria-labelledby="recentes-titre" class="rounded-card border border-line bg-card p-4">
                    <h2 id="recentes-titre" class="text-[17px] font-extrabold">Opportunités récentes</h2>
                    <ul class="mt-3 space-y-3">@foreach ($recent as $item)
                        <li class="flex min-w-0 items-center gap-3"><x-company-logo :company="$item->company" :size="44" /><div class="min-w-0 flex-1"><a href="{{ route('opportunites.show', $item) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $item->title }}</a><p class="-mt-2 text-[12px] text-muted">{{ $item->company->name }}@if ($item->city) · {{ $item->city->label() }}@endif</p></div></li>
                    @endforeach</ul>
                </section>
            @endif
        </aside>
    </div>
</div>
