@php
    $n = $needs->total();
    $noun = $onlyOpen && ! $hasFilters ? ($n > 1 ? 'besoins ouverts' : 'besoin ouvert') : ($n > 1 ? 'besoins' : 'besoin');
    $summary = $hasFilters ? ($n > 1 ? $n.' besoins correspondent' : $n.' besoin correspond').' à cette recherche' : $n.' '.$noun;
    $stateLabels = ['open' => 'Ouverts', 'in_progress' => 'En cours', 'resolved' => 'Résolus', 'closed' => 'Fermés sans suite'];
@endphp

<div class="need-directory flex flex-col gap-6">
    <header class="need-discovery-hero">
        <div class="flex min-w-0 flex-col gap-4">
            <span class="text-[12px] font-extrabold uppercase tracking-[2px]">Carrefour Besoins</span>
            <h1 class="zumra-discovery-title">Votre aide peut faire la différence.</h1>
            <p class="max-w-xl text-[17px] leading-relaxed text-muted">Un savoir-faire, du temps, un coup de main : découvrez ce qui manque pour faire avancer une action.</p>
        </div>
        <x-needs-illustration class="mx-auto block h-auto w-full max-w-[520px]" />
    </header>

    <section aria-label="Rechercher un besoin" class="flex flex-col gap-3">
        <form wire:submit="$refresh" class="need-searchbar">
            <div class="flex min-w-0 items-center gap-3 px-3">
                <x-icon name="search" :size="22" class="shrink-0" />
                <label for="recherche" class="sr-only">Rechercher un besoin par son texte ou par une capacité</label>
                <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Rechercher un mot ou une capacité…" class="min-h-12 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted">
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-2">
            <div role="group" aria-label="Filtrer les besoins par état" class="flex flex-wrap gap-x-4 gap-y-1">
                @foreach ($states as $etat)
                    <button type="button" wire:click="$set('state', '{{ $etat->value }}')" aria-pressed="{{ $selectedState === $etat->value ? 'true' : 'false' }}" @class(['need-state-filter', 'border-ink font-extrabold' => $selectedState === $etat->value, 'border-transparent font-semibold text-muted' => $selectedState !== $etat->value])>{{ $stateLabels[$etat->value] }}</button>
                @endforeach
                <button type="button" wire:click="$set('state', '{{ \App\Livewire\CarrefourBesoins::ALL }}')" aria-label="Tous les états" aria-pressed="{{ $selectedState === \App\Livewire\CarrefourBesoins::ALL ? 'true' : 'false' }}" @class(['need-state-filter', 'border-ink font-extrabold' => $selectedState === \App\Livewire\CarrefourBesoins::ALL, 'border-transparent font-semibold text-muted' => $selectedState !== \App\Livewire\CarrefourBesoins::ALL])>Tous</button>
            </div>
            <div class="flex max-w-full flex-wrap items-center gap-3">
                <div class="min-w-0"><label for="filtre-emetteur" class="sr-only">Qui exprime le besoin</label>
                    <select id="filtre-emetteur" wire:model.live="emitter" class="zumra-filter">
                        <option value="">Personnes, ZUMRA et projets</option>
                        @foreach ($emitters as $valeur => $libelle)<option value="{{ $valeur }}">{{ $libelle }} seulement</option>@endforeach
                    </select>
                </div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="min-h-11 text-[14px] font-semibold text-link underline">Réinitialiser</button>@endif
            </div>
        </div>
    </section>

    <section aria-labelledby="les-besoins" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="les-besoins" class="text-[26px] font-extrabold leading-tight">Un besoin. Une occasion d’agir.</h2><p class="mt-2 text-[14px] font-semibold text-muted" role="status" aria-live="polite">{{ $summary }}</p></div>
            <span class="text-[13px] font-semibold text-muted">Les plus récents d’abord</span>
        </div>
        <div wire:loading.delay class="text-[14px] font-semibold text-muted" role="status">Recherche en cours…</div>
        <div class="grid gap-5 lg:grid-cols-2" wire:loading.class="opacity-60">
            @forelse ($needs as $need)
                <x-need-discovery-card wire:key="besoin-{{ $need->id }}" :publication="$need" :viewer="$viewer" :managed="$managed" />
            @empty
                <section aria-labelledby="vide" class="flex flex-col items-center gap-4 rounded-card border border-line bg-card px-5 py-10 text-center lg:col-span-full">
                    <span aria-hidden="true" class="flex size-20 items-center justify-center rounded-full bg-type-call-bg text-ink"><x-icon name="heart" :size="38" /></span>
                    <h3 id="vide" class="text-[24px] font-extrabold leading-tight">{{ $hasFilters ? 'Aucun besoin ne correspond à cette recherche.' : 'Les prochains gestes commencent ici.' }}</h3>
                    @if ($hasFilters)
                        <p class="max-w-xl text-[16px] leading-relaxed text-muted">Essayez un autre mot, ou élargissez la recherche.</p>
                        <x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
                    @else
                        <p class="max-w-xl text-[16px] leading-relaxed text-muted">Aucun besoin ouvert pour l’instant. Vous pouvez exprimer le vôtre depuis Agir.</p>
                        <p class="max-w-xl text-[14px] leading-relaxed text-muted">Un besoin se publie depuis « Agir », par une personne, une ZUMRA ou un projet.</p>
                        <x-button :href="$viewer ? route('agir') : route('register')" variant="accent" class="gap-2">{{ $viewer ? 'Agir' : 'Créer un compte' }}<x-icon name="arrow-right" :size="18" /></x-button>
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
    </section>

    @if ($needs->isNotEmpty())
        <section aria-labelledby="exprimer-besoin" class="flex flex-wrap items-center justify-between gap-5 rounded-card bg-ink p-6 text-white">
            <div class="flex min-w-0 flex-1 basis-80 items-start gap-4"><x-icon name="heart" :size="40" class="shrink-0 text-accent" /><div><h2 id="exprimer-besoin" class="text-[23px] font-extrabold leading-tight">Vous avez besoin d’un coup de main ?</h2><p class="mt-2 text-[15px] leading-relaxed text-white/90">Exprimez ce qui vous manque et les capacités qui pourraient vous aider.</p></div></div>
            <x-button :href="$viewer ? route('agir') : route('register')" variant="accent" class="gap-2">{{ $viewer ? 'Agir' : 'Créer un compte' }}<x-icon name="arrow-right" :size="18" /></x-button>
        </section>
    @endif
</div>
