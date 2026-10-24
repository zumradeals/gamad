@php
    $offered = $mode === 'offered';
    $n = $people->total();
    $guest = ! auth()->check();
    $summary = $n.' '.($n > 1 ? 'personnes' : 'personne').' '.($key !== ''
        ? ($offered ? ($n > 1 ? 'peuvent apporter' : 'peut apporter') : ($n > 1 ? 'veulent apprendre' : 'veut apprendre')).' « '.\App\Support\Normalizer::display($this->query).' »'
        : 'à découvrir');
@endphp

<div class="person-directory flex flex-col gap-6">
    @if (\App\Support\Experience::initial())<a href="{{ auth()->check() ? route('fil') : route('home') }}" class="inline-flex min-h-11 items-center gap-2 self-start text-[14px] font-semibold"><x-icon name="back" :size="18" />{{ auth()->check() ? 'Retour à l’accueil' : 'Accueil GAMAD' }}</a>@endif
    <header class="person-discovery-hero">
        <div class="flex min-w-0 flex-col gap-4">
            <span class="text-[12px] font-extrabold uppercase tracking-[2px]">CARREFOUR PERSONNES</span>
            <h1 class="zumra-discovery-title">Des personnes à rencontrer. Des savoirs à partager.</h1>
            <p class="max-w-xl text-[17px] leading-relaxed text-muted">Trouvez qui peut vous aider, ou qui veut apprendre ce que vous savez.</p>
        </div>
        <x-people-illustration class="mx-auto block h-auto w-full max-w-[520px]" />
    </header>

    <form wire:submit="$refresh" aria-label="Rechercher une personne" class="person-searchbar">
        <div class="flex min-w-0 items-center gap-3 px-3">
            <x-icon name="search" :size="22" class="shrink-0" />
            <label for="recherche" class="sr-only">Rechercher une capacité ou un nom</label>
            <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Une capacité ou un nom…" class="min-h-12 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted">
        </div>
        <div>
            <label for="filtre-ville" class="sr-only">Ville</label>
            <select id="filtre-ville" wire:model.live="city" class="zumra-filter">
                <option value="">Toutes les villes</option>
                @foreach ($cities as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="filtre-domaine" class="sr-only">Domaine</label>
            <select id="filtre-domaine" wire:model.live="domain" class="zumra-filter">
                <option value="">Tous les domaines</option>
                @foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach
            </select>
        </div>
        <x-button type="submit" variant="accent">Rechercher</x-button>
    </form>

    <fieldset class="min-w-0 rounded-card border border-line bg-card p-4 md:p-5">
        <legend class="sr-only">Je cherche quelqu’un qui…</legend>
        <p aria-hidden="true" class="mb-3 text-[16px] font-extrabold">Je cherche quelqu’un qui…</p>
        <div class="grid gap-3 md:grid-cols-2">
            <button type="button" wire:click="setMode('offered')" aria-pressed="{{ $offered ? 'true' : 'false' }}" class="person-mode person-mode-offered">
                <x-icon name="group" :size="36" class="shrink-0" />
                <span class="min-w-0"><span class="block text-[18px] font-extrabold">Peut l’apporter</span><span class="mt-1 block text-[14px] leading-relaxed">Une personne qui peut partager cette capacité.</span></span>
            </button>
            <button type="button" wire:click="setMode('wanted')" aria-pressed="{{ $offered ? 'false' : 'true' }}" class="person-mode person-mode-wanted">
                <x-icon name="book" :size="36" class="shrink-0" />
                <span class="min-w-0"><span class="block text-[18px] font-extrabold">Veut l’apprendre</span><span class="mt-1 block text-[14px] leading-relaxed">Une personne qui souhaite apprendre cette capacité.</span></span>
            </button>
        </div>
    </fieldset>

    <section aria-labelledby="les-personnes" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="les-personnes" class="text-[28px] font-extrabold leading-tight">Des capacités à découvrir</h2><p class="mt-2 text-[14px] font-semibold text-muted" role="status" aria-live="polite">{{ $summary }}</p></div>
            <div class="flex flex-wrap items-center gap-4"><span class="text-[13px] font-semibold text-muted">Ordre alphabétique</span>@if ($hasFilters)<button type="button" wire:click="clearFilters" class="min-h-11 text-[14px] font-semibold text-link underline">Réinitialiser</button>@endif</div>
        </div>
        <div wire:loading.delay class="text-[14px] font-semibold text-muted" role="status">Recherche en cours…</div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
        @forelse ($people as $profile)
            <x-person-discovery-card wire:key="personne-{{ $profile->id }}" :profile="$profile" />
    @empty
        <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 md:col-span-full">
            <h2 id="vide" class="text-[18px] font-extrabold">
                @if ($hasFilters)
                    Personne ne correspond à cette recherche.
                @else
                    {{ $offered ? 'Personne n’a encore indiqué ce qu’elle peut apporter.' : 'Personne n’a encore indiqué ce qu’elle veut apprendre.' }}
                @endif
            </h2>

            @if ($guest)
                <p class="text-muted">Créez un compte : vous pourrez dire ce que vous savez faire, et trouver qui peut vous aider.</p>
                <x-button :href="route('register')">Créer un compte</x-button>
            @elseif (! $viewerProfile?->hasSkills())
                <p class="text-muted">Vous n’apparaissez pas encore ici. Dites ce que vous pouvez apporter et ce que vous voulez apprendre : d’autres vous trouveront.</p>
                <x-button :href="route('espace.profil')">Compléter mon profil</x-button>
            @else
                <p class="text-muted">Essayez une autre capacité, ou élargissez la recherche.</p>
            @endif

            @if ($hasFilters)
                <x-button type="button" variant="secondary" wire:click="clearFilters">Effacer la recherche</x-button>
            @endif
        </section>
    @endforelse
        </div>
    @if ($people->hasPages())
        <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$people->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $people->currentPage() }} sur {{ $people->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $people->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
    </section>

    @if ($people->isNotEmpty())
        <section aria-labelledby="partager-capacites" class="flex flex-wrap items-center justify-between gap-5 rounded-card bg-ink p-6 text-white">
            <div class="flex min-w-0 flex-1 basis-80 items-start gap-4"><x-icon name="user" :size="40" class="shrink-0 text-accent" /><div><h2 id="partager-capacites" class="text-[23px] font-extrabold leading-tight">Et vous, que pouvez-vous apporter ?</h2><p class="mt-2 text-[15px] leading-relaxed text-white/90">Indiquez vos capacités et vos envies d’apprendre pour que d’autres vous trouvent.</p></div></div>
            <x-button :href="$guest ? route('register') : route('espace.profil')" variant="accent" class="gap-2">{{ $guest ? 'Créer un compte' : 'Compléter mon profil' }}<x-icon name="arrow-right" :size="18" /></x-button>
        </section>
    @endif
    <p class="flex items-center justify-center gap-2 text-center text-[13px] text-muted"><x-icon name="lock" :size="16" class="shrink-0" />Votre e-mail et votre téléphone restent privés.</p>
</div>
