@php
    $n = $zumras->total();
    $summary = $hasFilters ? ($n > 1 ? $n.' ZUMRA correspondent' : $n.' ZUMRA correspond').' à cette recherche' : $n.' ZUMRA';
@endphp

<div class="flex flex-col gap-4">
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[22px] pt-3.5 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-bold tracking-[1.5px] text-accent">CARREFOUR</span>
            <h1 class="text-[28px] font-extrabold leading-tight tracking-[-0.5px]">ZUMRA</h1>
            <p class="text-[14px] leading-normal text-[#d6e0e8]">Des communautés qui apprennent, travaillent et agissent ensemble autour d’un domaine.</p>
        </div>

        <div class="flex min-h-12 items-center gap-2.5 rounded-button bg-card px-3.5 text-muted focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-white">
            <x-icon name="search" :size="20" />
            <label for="recherche" class="sr-only">Rechercher une ZUMRA par son nom ou sa mission</label>
            <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off"
                   placeholder="Un nom ou une mission"
                   class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] text-ink outline-none placeholder:text-muted">
        </div>
    </header>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
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

    @if ($viewer)
        <section aria-labelledby="mes-zumra" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 id="mes-zumra" class="text-[17px] font-extrabold">Mes ZUMRA</h2>
                <a href="{{ route('zumras.create') }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Créer une ZUMRA</a>
            </div>
            @forelse ($mine as $zumra)
                <a wire:key="mienne-{{ $zumra->id }}" href="{{ route('zumras.show', $zumra) }}" class="flex min-h-14 items-center gap-3 text-ink no-underline hover:text-ink">
                    <x-zumra-initials :zumra="$zumra" :size="44" />
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="break-words text-[15px] font-extrabold">{{ $zumra->name }}</span>
                        <span class="text-[12px] text-muted">{{ $zumra->memberships->first()->role->label() }} · {{ $zumra->members_count }} {{ $zumra->members_count > 1 ? 'membres' : 'membre' }}</span>
                    </span>
                    <x-icon name="next" :size="18" />
                </a>
            @empty
                <p class="text-[14px] text-muted">Vous n’êtes dans aucune ZUMRA pour l’instant. Créez la vôtre autour d’un domaine.</p>
            @endforelse
        </section>
    @endif

    <div wire:loading.delay class="text-[13px] font-semibold text-muted" role="status">Recherche en cours…</div>

    <div class="flex items-baseline justify-between gap-2">
        <h2 class="text-[20px] font-extrabold">Toutes les ZUMRA</h2>
        <span class="text-[12px] font-semibold text-muted">Par ordre alphabétique</span>
    </div>
    <p class="-mt-2 text-[13px] font-bold text-muted" role="status" aria-live="polite">{{ $summary }}</p>

    @forelse ($zumras as $zumra)
        @php
            $meta = collect([$zumra->domain->name, $zumra->city?->label(), $zumra->format->label()])->filter()->implode(' · ');
        @endphp
        <article wire:key="zumra-{{ $zumra->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <div class="flex items-center gap-3">
                <x-zumra-initials :zumra="$zumra" :size="52" />
                <div class="flex min-w-0 flex-col">
                    <a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words text-[16px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $zumra->name }}</a>
                    <span class="-mt-2 text-[12px] font-semibold text-muted">{{ $meta }}</span>
                </div>
            </div>
            <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ \Illuminate\Support\Str::limit($zumra->mission, 180) }}</p>
            <div class="flex items-baseline justify-center gap-1.5 rounded-button bg-ground py-2.5">
                <span class="text-[18px] font-extrabold">{{ $zumra->members_count }}</span>
                <span class="text-[12px] text-muted">{{ $zumra->members_count > 1 ? 'membres' : 'membre' }}</span>
            </div>
            <x-button :href="route('zumras.show', $zumra)" variant="secondary">Entrer</x-button>
        </article>
    @empty
        <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5">
            <h2 id="vide" class="text-[18px] font-extrabold">{{ $hasFilters ? 'Aucune ZUMRA ne correspond à cette recherche.' : 'Aucune ZUMRA pour l’instant.' }}</h2>
            @if ($hasFilters)
                <p class="text-muted">Essayez un autre mot, ou élargissez la recherche.</p>
                <x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
            @elseif ($viewer)
                <p class="text-muted">Soyez la première personne à en créer une : une communauté autour d’un seul domaine.</p>
                <x-button :href="route('zumras.create')" variant="accent">Créer une ZUMRA</x-button>
            @else
                <p class="text-muted">Créez un compte : vous pourrez fonder la première ZUMRA.</p>
                <x-button :href="route('register')">Créer un compte</x-button>
            @endif
        </section>
    @endforelse

    @if ($zumras->hasPages())
        <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$zumras->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $zumras->currentPage() }} sur {{ $zumras->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $zumras->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
