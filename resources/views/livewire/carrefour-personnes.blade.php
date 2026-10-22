@php
    $offered = $mode === 'offered';
    $n = $people->total();
    $guest = ! auth()->check();
    $summary = $n.' '.($n > 1 ? 'personnes' : 'personne').' '.($key !== ''
        ? ($offered ? ($n > 1 ? 'peuvent apporter' : 'peut apporter') : ($n > 1 ? 'veulent apprendre' : 'veut apprendre')).' « '.\App\Support\Normalizer::display($this->query).' »'
        : 'à découvrir');
@endphp

<div class="flex flex-col gap-4">
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[22px] pt-3.5 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-bold tracking-[1.5px] text-accent">CARREFOUR</span>
            <h1 class="text-[28px] font-extrabold leading-tight tracking-[-0.5px]">Personnes</h1>
            <p class="text-[14px] leading-normal text-[#d6e0e8]">Trouvez qui peut vous aider, ou qui veut apprendre ce que vous savez.</p>
        </div>

        <div class="flex min-h-12 items-center gap-2.5 rounded-button bg-card px-3.5 text-muted focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-white">
            <x-icon name="search" :size="20" />
            <label for="recherche" class="sr-only">Rechercher une capacité ou un nom</label>
            <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off"
                   placeholder="Une capacité ou un nom"
                   class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] text-ink outline-none placeholder:text-muted">
        </div>
    </header>

    <fieldset class="flex flex-col gap-2">
        <legend class="mb-2 text-[13px] font-bold text-muted">Je cherche quelqu’un qui…</legend>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" wire:click="setMode('offered')" aria-pressed="{{ $offered ? 'true' : 'false' }}"
                    @class(['min-h-12 rounded-button px-3 text-[14px]', 'border-2 border-type-knowledge-fg bg-type-knowledge-bg font-extrabold text-type-knowledge-fg' => $offered, 'border border-chip-line bg-card font-semibold text-ink' => ! $offered])>
                peut l’apporter
            </button>
            <button type="button" wire:click="setMode('wanted')" aria-pressed="{{ $offered ? 'false' : 'true' }}"
                    @class(['min-h-12 rounded-button px-3 text-[14px]', 'border-2 border-type-learning-fg bg-type-learning-bg font-extrabold text-type-learning-fg' => ! $offered, 'border border-chip-line bg-card font-semibold text-ink' => $offered])>
                veut l’apprendre
            </button>
        </div>
    </fieldset>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="filtre-ville" class="sr-only">Ville</label>
            <select id="filtre-ville" wire:model.live="city" class="min-h-11 w-full rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                <option value="">Toutes les villes</option>
                @foreach ($cities as $ville)
                    <option value="{{ $ville->id }}">{{ $ville->label() }}</option>
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
    </div>

    <div wire:loading.delay class="text-[13px] font-semibold text-muted" role="status">Recherche en cours…</div>

    <p class="text-[13px] font-bold text-muted" role="status" aria-live="polite">{{ $summary }}</p>

    @forelse ($people as $profile)
        <article wire:key="personne-{{ $profile->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <div class="flex items-center gap-3">
                <x-avatar :profile="$profile" :size="52" />
                <div class="flex min-w-0 flex-col">
                    <a href="{{ route('personnes.show', $profile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[16px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $profile->user->name }}</a>
                    @if ($profile->city)
                        <span class="text-[12px] font-semibold text-muted">{{ $profile->city->label() }}</span>
                    @endif
                </div>
            </div>

            @if ($profile->offeredSkills->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    <span class="text-[11px] font-extrabold tracking-[0.8px] text-type-knowledge-fg">PEUT APPORTER</span>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($profile->offeredSkills as $skill)
                            <li class="rounded-chip bg-type-knowledge-bg px-2.5 py-[5px] text-[13px] font-bold text-type-knowledge-fg">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($profile->wantedSkills->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    <span class="text-[11px] font-extrabold tracking-[0.8px] text-type-learning-fg">VEUT APPRENDRE</span>
                    <p class="text-[13px]">{{ $profile->wantedSkills->pluck('name')->implode(' · ') }}</p>
                </div>
            @endif

            <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary">Voir le profil</x-button>
        </article>
    @empty
        <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5">
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

    @if ($people->hasPages())
        <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$people->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $people->currentPage() }} sur {{ $people->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $people->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
