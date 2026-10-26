@php
    $n = $zumras->total();
    $summary = $hasFilters ? ($n > 1 ? $n.' ZUMRA correspondent' : $n.' ZUMRA correspond').' à cette recherche' : $n.' ZUMRA';
    $chip = 'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-chip border px-4 text-[14px] font-semibold';
    $explore = [
        ['sphere', 'professional', 'ZUMRA professionnelles', 'Innovation, métiers, entrepreneuriat', 'project'],
        ['sphere', 'social', 'ZUMRA sociales', 'Entraide, solidarité, développement', 'heart'],
        ['sphere', 'religious', 'ZUMRA religieuses', 'Foi, valeurs, actions communautaires', 'home'],
        ['format', 'online', 'ZUMRA numériques', 'En ligne, à distance', 'compass'],
        ['format', 'hybrid', 'ZUMRA hybrides', 'Présence locale et en ligne', 'community'],
    ];
@endphp

<div class="zumra-directory flex flex-col gap-6" x-data="{ filters: {{ $hasFilters && ($country !== '' || $city !== '' || $domain !== '') ? 'true' : 'false' }} }">
    <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
        <div class="flex min-w-0 max-w-3xl flex-col gap-2">
            <a href="{{ route('fil') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold"><x-icon name="back" :size="16" />ZUMRA</a>
            <h1 class="text-[clamp(28px,4vw,40px)] font-extrabold leading-tight tracking-[-0.8px]">Des communautés qui font la différence</h1>
            <p class="text-[16px] leading-relaxed text-muted">Les ZUMRA rassemblent des personnes et des compétences autour d’un domaine d’activité. Elles apprennent, travaillent et évoluent ensemble pour créer un impact positif.</p>
        </div>
        @if ($viewer)
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <x-button class="gap-2" :href="route('zumras.create')" variant="accent"><x-icon name="community" :size="20" />Créer une ZUMRA</x-button>
                <x-button :href="route('espace.zumras')" variant="secondary">Mes ZUMRA</x-button>
                @unless (\App\Support\Experience::initial())<x-button :href="route('projets.index')" variant="secondary">Voir les projets</x-button>@endunless
            </div>
        @else
            <x-button class="w-full gap-2 sm:w-auto" :href="route('register')" variant="accent">Créer un compte pour créer une ZUMRA</x-button>
        @endif
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="flex min-w-0 flex-col gap-6">
            <section aria-label="Rechercher une ZUMRA" class="flex flex-col gap-3 rounded-card border border-line bg-card p-3 md:p-4">
                <form wire:submit="$refresh" class="flex flex-wrap gap-2 sm:flex-nowrap">
                    <div class="flex min-h-12 min-w-0 flex-1 basis-full items-center gap-3 rounded-button border border-line px-3 sm:basis-0">
                        <x-icon name="search" :size="22" class="shrink-0" />
                        <label for="recherche" class="sr-only">Rechercher une ZUMRA par nom, domaine ou lieu</label>
                        <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Rechercher une ZUMRA par nom, domaine ou lieu…" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted">
                    </div>
                    <button type="button" x-on:click="filters = ! filters" x-bind:aria-expanded="filters.toString()" aria-controls="filtres-zumra" class="{{ $chip }} border-line bg-card"><x-icon name="filter" :size="18" />Filtres</button>
                </form>

                <div id="filtres-zumra" x-show="filters" x-cloak class="grid gap-2 sm:grid-cols-3">
                    <div><label for="filtre-pays" class="sr-only">Pays</label><select id="filtre-pays" wire:model.live="country" class="zumra-filter"><option value="">Tous les pays</option>@foreach ($countries as $pays)<option value="{{ $pays->id }}">{{ $pays->name }}</option>@endforeach</select></div>
                    <div><label for="filtre-ville" class="sr-only">Ville</label><select id="filtre-ville" wire:model.live="city" class="zumra-filter"><option value="">Toutes les villes</option>@foreach ($cities as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }}</option>@endforeach</select></div>
                    <div><label for="filtre-domaine" class="sr-only">Domaine</label><select id="filtre-domaine" wire:model.live="domain" class="zumra-filter"><option value="">Tous les domaines</option>@foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach</select></div>
                </div>

                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="group" aria-label="Filtrer par nature et par fonctionnement">
                    <button type="button" wire:click="clearFilters" aria-pressed="{{ $hasFilters ? 'false' : 'true' }}" @class([$chip, 'border-ink bg-ink text-white' => ! $hasFilters, 'border-line bg-card text-ink' => $hasFilters])>Toutes</button>
                    @foreach ($spheres as $option)
                        <button type="button" wire:click="pick('sphere', '{{ $option->value }}')" aria-pressed="{{ $sphere === $option->value ? 'true' : 'false' }}" @class([$chip, 'border-ink bg-ink text-white' => $sphere === $option->value, 'border-line bg-card text-ink' => $sphere !== $option->value])><x-icon :name="$option->icon()" :size="18" />{{ $option->label() }}</button>
                    @endforeach
                    <span class="w-px shrink-0 self-stretch bg-line" aria-hidden="true"></span>
                    @foreach ($formats as $mode)
                        <button type="button" wire:click="pick('format', '{{ $mode->value }}')" aria-pressed="{{ $format === $mode->value ? 'true' : 'false' }}" @class([$chip, 'border-ink bg-ink text-white' => $format === $mode->value, 'border-line bg-card text-ink' => $format !== $mode->value])>{{ $mode->label() }}</button>
                    @endforeach
                </div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="min-h-11 self-start text-[14px] font-semibold text-link underline">Réinitialiser</button>@endif
            </section>

            @if ($featured->isNotEmpty())
                <section aria-labelledby="a-la-une" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div><h2 id="a-la-une" class="text-[24px] font-extrabold leading-tight">ZUMRA à la une</h2><p class="text-[14px] text-muted">Des communautés reconnues par GAMAD, présentées dans un ordre qui change chaque jour.</p></div>
                        <a href="#toutes-zumra" class="inline-flex min-h-11 items-center gap-1.5 text-[14px] font-semibold">Voir toutes les ZUMRA<x-icon name="arrow-right" :size="16" /></a>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ($featured as $zumra)
                            @include('livewire.partials.zumra-card', ['zumra' => $zumra, 'prefix' => 'une'])
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="toutes-zumra" aria-labelledby="titre-toutes" class="flex scroll-mt-24 flex-col gap-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h2 id="titre-toutes" class="text-[24px] font-extrabold leading-tight">Toutes les ZUMRA</h2><p class="text-[14px] text-muted">Explorez les communautés selon vos centres d’intérêt.</p></div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-button border border-line" role="group" aria-label="Présentation">
                            <button type="button" wire:click="$set('layout', 'grille')" aria-pressed="{{ $layout === 'grille' ? 'true' : 'false' }}" @class(['inline-flex min-h-11 items-center gap-1.5 rounded-button px-3 text-[14px] font-semibold', 'bg-ink text-white' => $layout === 'grille'])><x-icon name="grid" :size="18" />Grille</button>
                            <button type="button" wire:click="$set('layout', 'liste')" aria-pressed="{{ $layout === 'liste' ? 'true' : 'false' }}" @class(['inline-flex min-h-11 items-center gap-1.5 rounded-button px-3 text-[14px] font-semibold', 'bg-ink text-white' => $layout === 'liste'])><x-icon name="menu" :size="18" />Liste</button>
                        </div>
                        <div><label for="tri-zumra" class="sr-only">Tri</label><select id="tri-zumra" wire:model.live="sort" class="zumra-filter !w-auto"><option value="recentes">Tri : plus récentes</option><option value="alphabetique">Tri : ordre alphabétique</option></select></div>
                    </div>
                </div>
                <p class="text-[14px] font-semibold text-muted" role="status" aria-live="polite">{{ $summary }}</p>
                <div wire:loading.delay class="text-[14px] font-semibold text-muted" role="status">Recherche en cours…</div>
                <div @class(['grid gap-4', 'sm:grid-cols-2 xl:grid-cols-3' => $layout === 'grille']) wire:loading.class="opacity-60">
                    @forelse ($zumras as $zumra)
                        @include('livewire.partials.zumra-card', ['zumra' => $zumra, 'prefix' => 'zumra', 'row' => $layout === 'liste', 'engagedIds' => $engagedIds])
                    @empty
                        <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 lg:col-span-full">
                            <h3 id="vide" class="text-[18px] font-extrabold">{{ $hasFilters ? 'Aucune ZUMRA ne correspond à cette recherche.' : 'Aucune ZUMRA pour l’instant.' }}</h3>
                            @if ($hasFilters)<p class="text-muted">Essayez un autre mot, ou élargissez la recherche.</p><x-button class="gap-2" type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
                            @elseif ($viewer)<p class="text-muted">Soyez la première personne à en créer une : une communauté autour d’un seul domaine.</p><x-button class="gap-2" :href="route('zumras.create')" variant="accent">Créer une ZUMRA</x-button>
                            @else<p class="text-muted">Créez un compte : vous pourrez créer la première ZUMRA.</p><x-button class="gap-2" :href="route('register')">Créer un compte</x-button>@endif
                        </section>
                    @endforelse
                </div>
                @if ($zumras->hasPages())
                    <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3"><x-button class="gap-2" type="button" variant="secondary" wire:click="previousPage" :disabled="$zumras->onFirstPage()">Précédent</x-button><span class="text-[13px] font-semibold text-muted">Page {{ $zumras->currentPage() }} sur {{ $zumras->lastPage() }}</span><x-button class="gap-2" type="button" variant="secondary" wire:click="nextPage" :disabled="! $zumras->hasMorePages()">Suivant</x-button></nav>
                @endif
            </section>
        </div>

        <aside class="flex min-w-0 flex-col gap-4" aria-label="Pour explorer">
            <section aria-labelledby="explorer" class="flex flex-col gap-1 rounded-card border border-line bg-card p-4">
                <h2 id="explorer" class="mb-1 text-[18px] font-extrabold">Explorer les ZUMRA</h2>
                @foreach ($explore as [$kind, $value, $label, $hint, $icon])
                    <button type="button" wire:click="pick('{{ $kind }}', '{{ $value }}')" class="flex min-h-14 items-center gap-3 rounded-button px-1 py-2 text-left hover:bg-ground">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-button bg-soft-blue" aria-hidden="true"><x-icon :name="$icon" :size="20" /></span>
                        <span class="flex min-w-0 flex-1 flex-col"><span class="text-[14px] font-extrabold">{{ $label }}</span><span class="text-[12px] text-muted">{{ $hint }}</span></span>
                        <x-icon name="next" :size="16" />
                    </button>
                @endforeach
                <button type="button" x-on:click="filters = true; $nextTick(() => document.getElementById('filtre-ville').focus())" class="flex min-h-14 items-center gap-3 rounded-button px-1 py-2 text-left hover:bg-ground">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-button bg-soft-blue" aria-hidden="true"><x-icon name="pin" :size="20" /></span>
                    <span class="flex min-w-0 flex-1 flex-col"><span class="text-[14px] font-extrabold">ZUMRA dans ma région</span><span class="text-[12px] text-muted">Par localité ou pays</span></span>
                    <x-icon name="next" :size="16" />
                </button>
            </section>

            <section aria-labelledby="cest-quoi" class="flex flex-col gap-3 rounded-card bg-soft-blue p-4">
                <h2 id="cest-quoi" class="text-[18px] font-extrabold">Une ZUMRA, c’est quoi ?</h2>
                <ul class="flex flex-col gap-2 text-[14px]">
                    @foreach (['Une communauté de personnes', 'Un domaine d’activité spécifique', 'Des compétences partagées', 'Des actions concrètes', 'Un impact positif dans la durée'] as $point)
                        <li class="flex items-start gap-2"><x-icon name="check" :size="18" class="mt-0.5 shrink-0" /><span>{{ $point }}</span></li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>
</div>
