@php
    $n = $zumras->total();
    $summary = $hasFilters ? ($n > 1 ? $n.' ZUMRA correspondent' : $n.' ZUMRA correspond').' à cette recherche' : $n.' ZUMRA';
@endphp

<div class="zumra-directory flex flex-col gap-6">
    <header class="zumra-discovery-hero">
        <div class="flex min-w-0 flex-col items-start gap-4">
            <span class="text-[12px] font-extrabold tracking-[2px]">CARREFOUR ZUMRA</span>
            <h1 class="zumra-discovery-title">Ensemble, faites grandir vos idées.</h1>
            <p class="max-w-xl text-[17px] leading-relaxed text-muted">Trouvez une communauté pour apprendre, transmettre et agir.</p>
        </div>
        <figure class="min-w-0">
            <img src="{{ asset('images/gamad-accueil-hero.webp') }}" width="510" height="280" fetchpriority="high" alt="Illustration de personnes échangeant autour d’un ordinateur." class="block h-auto w-full rounded-card">
            <figcaption class="mt-2 text-[13px] text-muted">Des capacités qui se rencontrent.</figcaption>
        </figure>
    </header>

    <section aria-label="Rechercher une ZUMRA" class="flex flex-col gap-3">
        <form wire:submit="$refresh" class="zumra-searchbar">
            <div class="flex min-w-0 items-center gap-3 px-3">
                <x-icon name="search" :size="22" class="shrink-0" />
                <label for="recherche" class="sr-only">Rechercher une ZUMRA par son nom ou sa mission</label>
                <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Un nom ou une mission…" class="min-h-12 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted">
            </div>
            <div><label for="filtre-pays" class="sr-only">Pays</label><select id="filtre-pays" wire:model.live="country" class="zumra-filter"><option value="">Tous les pays</option>@foreach ($countries as $pays)<option value="{{ $pays->id }}">{{ $pays->name }}</option>@endforeach</select></div>
            <div><label for="filtre-ville" class="sr-only">Ville</label><select id="filtre-ville" wire:model.live="city" class="zumra-filter"><option value="">Toutes les villes</option>@foreach ($cities as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }}</option>@endforeach</select></div>
            <div><label for="filtre-domaine" class="sr-only">Domaine</label><select id="filtre-domaine" wire:model.live="domain" class="zumra-filter"><option value="">Tous les domaines</option>@foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach</select></div>
            <x-button type="submit" variant="accent" aria-label="Rechercher"><x-icon name="search" :size="21" /><span class="lg:sr-only">Rechercher</span></x-button>
        </form>
        <div class="flex flex-wrap items-center gap-3">
            <div><label for="filtre-format" class="sr-only">Format</label><select id="filtre-format" wire:model.live="format" class="zumra-filter !w-auto rounded-chip"><option value="">Tous les formats</option>@foreach ($formats as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach</select></div>
            <button type="button" wire:click="toggleRecognized" aria-pressed="{{ $recognized ? 'true' : 'false' }}" @class(['inline-flex min-h-11 items-center gap-2 rounded-chip border px-4 text-[13px] font-bold', 'border-ink bg-ink text-white' => $recognized, 'border-line bg-card text-ink' => ! $recognized])><x-icon name="check" :size="17" />Reconnues par GAMAD</button>
            <a href="{{ route('projets.index') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">Voir les projets<x-icon name="arrow-right" :size="17" class="ml-2" /></a>
            @if ($hasFilters)<button type="button" wire:click="clearFilters" class="ml-auto min-h-11 text-[14px] font-semibold text-link underline">Réinitialiser</button>@endif
        </div>
    </section>

    @if ($viewer)
        <section aria-labelledby="mes-zumra" class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-card border border-line bg-card p-4">
            <h2 id="mes-zumra" class="flex items-center gap-2 text-[16px] font-extrabold"><x-icon name="community" :size="24" />Mes ZUMRA</h2>
            <div class="flex min-w-0 flex-1 flex-wrap gap-x-5 gap-y-3">
                @forelse ($mine as $zumra)
                    <a wire:key="mienne-{{ $zumra->id }}" href="{{ route('zumras.show', $zumra) }}" class="flex min-h-11 min-w-0 items-center gap-3 text-ink no-underline hover:underline">
                        <x-zumra-initials :zumra="$zumra" :size="40" />
                        <span class="min-w-0"><span class="block break-words text-[14px] font-extrabold">{{ $zumra->name }}</span><span class="block text-[13px] text-muted">{{ $zumra->memberships->first()->role->label() }}</span></span><x-icon name="arrow-right" :size="18" class="shrink-0" />
                    </a>
                @empty
                    <p class="self-center text-[14px] text-muted">Vous n’êtes dans aucune ZUMRA pour l’instant.</p>
                @endforelse
            </div>
            <x-button :href="route('zumras.create')" variant="secondary"><x-icon name="plus" :size="18" />Créer une ZUMRA</x-button>
        </section>
    @endif

    <section aria-labelledby="toutes-zumra" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="toutes-zumra" class="text-[26px] font-extrabold leading-tight">Découvrez les ZUMRA</h2><p class="mt-1 text-[15px] text-muted">Des missions différentes. Une même envie d’agir.</p></div>
            <span class="text-[13px] font-semibold text-muted">Par ordre alphabétique</span>
        </div>
        <p class="text-[14px] font-semibold text-muted" role="status" aria-live="polite">{{ $summary }}</p>
        <div wire:loading.delay class="text-[14px] font-semibold text-muted" role="status">Recherche en cours…</div>
        <div class="grid gap-5 lg:grid-cols-2" wire:loading.class="opacity-60">
            @forelse ($zumras as $zumra)
                <article wire:key="zumra-{{ $zumra->id }}" class="zumra-mission-card">
                    <div class="zumra-mission-cover" aria-hidden="true"><x-zumra-initials :zumra="$zumra" :size="72" /></div>
                    <div class="flex min-w-0 flex-1 flex-col gap-3 p-5">
                        <p class="text-[12px] font-extrabold uppercase tracking-wide text-muted">{{ $zumra->domain->name }}</p>
                        <div><h3 class="text-[20px] font-extrabold leading-tight"><a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words text-ink no-underline hover:underline">{{ $zumra->name }}</a></h3>
                        @if ($zumra->isRecognized())<span class="inline-flex items-center gap-1 rounded-chip bg-type-knowledge-bg px-2 py-1 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check" :size="14" />Reconnue par GAMAD</span>@endif</div>
                        <p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ \Illuminate\Support\Str::limit($zumra->mission, 180) }}</p>
                        <p class="flex items-start gap-2 text-[13px] leading-relaxed text-muted"><x-icon name="pin" :size="18" class="shrink-0" /><span>{{ collect([$zumra->city?->label(), $zumra->city?->country?->name, $zumra->format->label()])->filter()->implode(' · ') }}</span></p>
                        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2"><span class="inline-flex items-center gap-2 text-[13px] text-muted"><x-icon name="group" :size="18" />{{ $zumra->members_count }} {{ $zumra->members_count > 1 ? 'membres' : 'membre' }}</span><x-button :href="route('zumras.show', $zumra)">Découvrir<x-icon name="arrow-right" :size="18" /></x-button></div>
                        @unless (in_array($zumra->id, $memberOf, true))<a href="{{ route('zumras.show', $zumra).'#rejoindre' }}" class="inline-flex min-h-11 items-center self-end text-[13px] font-semibold">Je veux rejoindre<x-icon name="arrow-right" :size="16" class="ml-2" /></a>@endunless
                    </div>
                </article>
            @empty
                <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 lg:col-span-full">
                    <h3 id="vide" class="text-[18px] font-extrabold">{{ $hasFilters ? 'Aucune ZUMRA ne correspond à cette recherche.' : 'Aucune ZUMRA pour l’instant.' }}</h3>
                    @if ($hasFilters)<p class="text-muted">Essayez un autre mot, ou élargissez la recherche.</p><x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
                    @elseif ($viewer)<p class="text-muted">Soyez la première personne à en créer une : une communauté autour d’un seul domaine.</p><x-button :href="route('zumras.create')" variant="accent">Créer une ZUMRA</x-button>
                    @else<p class="text-muted">Créez un compte : vous pourrez fonder la première ZUMRA.</p><x-button :href="route('register')">Créer un compte</x-button>@endif
                </section>
            @endforelse
        </div>
        @if ($zumras->hasPages())
            <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3"><x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$zumras->onFirstPage()">Précédent</x-button><span class="text-[13px] font-semibold text-muted">Page {{ $zumras->currentPage() }} sur {{ $zumras->lastPage() }}</span><x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $zumras->hasMorePages()">Suivant</x-button></nav>
        @endif
    </section>

    <section class="flex flex-wrap items-center justify-between gap-5 rounded-card bg-ink p-6 text-white" aria-labelledby="creer-ensemble"><div><h2 id="creer-ensemble" class="text-[23px] font-extrabold">Une mission vous rassemble ?</h2><p class="mt-2 text-[15px] text-white/90">Donnez-lui un espace pour apprendre et agir ensemble.</p></div><x-button :href="$viewer ? route('zumras.create') : route('register')" variant="accent"><x-icon name="plus" :size="18" />{{ $viewer ? 'Créer une ZUMRA' : 'Créer un compte' }}</x-button></section>
</div>
