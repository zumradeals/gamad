@php
    $n = $needs->total();
    $noun = $onlyOpen && ! $hasFilters ? ($n > 1 ? 'besoins ouverts' : 'besoin ouvert') : ($n > 1 ? 'besoins' : 'besoin');
    $summary = $hasFilters ? ($n > 1 ? $n.' besoins correspondent' : $n.' besoin correspond').' à cette recherche' : $n.' '.$noun;
@endphp

<div class="flex flex-col gap-4">
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[22px] pt-3.5 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-bold tracking-[1.5px] text-accent">CARREFOUR</span>
            <h1 class="text-[28px] font-extrabold leading-tight tracking-[-0.5px]">Besoins</h1>
            <p class="text-[14px] leading-normal text-[#d6e0e8]">Ce qui manque pour faire avancer une action : exprimé par une personne, une ZUMRA ou un projet.</p>
        </div>

        <div class="flex min-h-12 items-center gap-2.5 rounded-button bg-card px-3.5 text-muted focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-white">
            <x-icon name="search" :size="20" />
            <label for="recherche" class="sr-only">Rechercher un besoin par son texte ou par une capacité</label>
            <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off"
                   placeholder="Un mot ou une capacité"
                   class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] text-ink outline-none placeholder:text-muted">
        </div>
    </header>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex flex-col gap-1">
            <label for="filtre-etat" class="sr-only">État</label>
            <select id="filtre-etat" wire:model.live="state" class="min-h-11 w-full rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                @foreach ($states as $etat)
                    <option value="{{ $etat->value }}" @selected($selectedState === $etat->value)>{{ $etat === \App\Enums\NeedState::Open ? 'Besoins ouverts' : $etat->label() }}</option>
                @endforeach
                <option value="{{ \App\Livewire\CarrefourBesoins::ALL }}" @selected($selectedState === \App\Livewire\CarrefourBesoins::ALL)>Tous les états</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label for="filtre-emetteur" class="sr-only">Qui exprime le besoin</label>
            <select id="filtre-emetteur" wire:model.live="emitter" class="min-h-11 w-full rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                <option value="">Personnes, ZUMRA et projets</option>
                @foreach ($emitters as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }} seulement</option>
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
        <h2 class="text-[20px] font-extrabold">Les besoins</h2>
        <span class="text-[12px] font-semibold text-muted">Les plus récents d’abord</span>
    </div>
    <p class="-mt-2 text-[13px] font-bold text-muted" role="status" aria-live="polite">{{ $summary }}</p>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($needs as $need)
        <x-publication-card wire:key="besoin-{{ $need->id }}" browse :publication="$need" :viewer="$viewer" :managed="$managed" />
    @empty
        <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 md:col-span-full">
            <h2 id="vide" class="text-[18px] font-extrabold">{{ $hasFilters ? 'Aucun besoin ne correspond à cette recherche.' : 'Aucun besoin ouvert pour l’instant.' }}</h2>
            @if ($hasFilters)
                <p class="text-muted">Essayez un autre mot, ou élargissez la recherche.</p>
                <x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
            @else
                <p class="text-muted">Un besoin se publie depuis « Agir », par une personne, une ZUMRA ou un projet.</p>
                @auth
                    <x-button :href="route('agir')" variant="accent">Agir</x-button>
                @else
                    <x-button :href="route('register')">Créer un compte</x-button>
                @endauth
            @endif
        </section>
    @endforelse
    </div>

    @if ($needs->hasPages())
        <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$needs->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $needs->currentPage() }} sur {{ $needs->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $needs->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
