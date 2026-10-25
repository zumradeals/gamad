@php
    $offered = $mode === 'offered';
    $guest = ! auth()->check();
    $n = $people->total();
@endphp
<div class="person-directory min-w-0">
    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-[230px_minmax(0,1fr)_260px]">
        <aside aria-label="Navigation et filtres des personnes" class="min-w-0 space-y-4 rounded-card border border-line bg-card p-4">
            <h2 class="text-[18px] font-extrabold">Explorer les personnes</h2>
            <nav aria-label="Explorer">
                <a href="{{ route('personnes.index') }}" class="flex min-h-11 items-center gap-2 rounded-button bg-soft-blue px-3 text-[14px] font-bold text-link"><x-icon name="group" :size="18"/> Toutes les personnes</a>
                <a href="{{ auth()->check() ? route('espace.profil') : route('register') }}" class="flex min-h-11 items-center gap-2 px-3 text-[14px] font-semibold"><x-icon name="user" :size="18"/> Mon profil</a>
            </nav>
            <div class="border-t border-line pt-4">
                <h3 class="mb-3 text-[16px] font-extrabold">Filtres</h3>
                <label for="filtre-ville" class="mb-2 block text-[13px] font-bold">Localité</label>
                <select id="filtre-ville" wire:model.live="city" class="zumra-filter w-full">
                    <option value="">Toutes les villes</option>
                    @foreach ($cities as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }}</option>@endforeach
                </select>
                <label for="filtre-domaine" class="mb-2 mt-4 block text-[13px] font-bold">Domaine de compétence</label>
                <select id="filtre-domaine" wire:model.live="domain" class="zumra-filter w-full">
                    <option value="">Tous les domaines</option>
                    @foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach
                </select>
                <p class="mb-2 mt-4 text-[13px] font-bold">Je cherche une personne qui…</p>
                <div class="space-y-2">
                    <button type="button" wire:click="setMode('offered')" aria-pressed="{{ $offered ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center gap-2 rounded-button px-3 text-left text-[13px] font-semibold {{ $offered ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'border border-line' }}">Peut apporter</button>
                    <button type="button" wire:click="setMode('wanted')" aria-pressed="{{ ! $offered ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center gap-2 rounded-button px-3 text-left text-[13px] font-semibold {{ ! $offered ? 'bg-type-learning-bg text-type-learning-fg' : 'border border-line' }}">Veut apprendre</button>
                </div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="mt-4 min-h-11 w-full rounded-button border border-line px-3 text-[13px] font-bold">Réinitialiser les filtres</button>@endif
            </div>
        </aside>
        <main class="min-w-0 space-y-4">
            <h1 class="sr-only">Découvrir des personnes sur GAMAD</h1>
            <div class="rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="setMode('offered')" class="min-h-10 rounded-full px-4 text-[13px] font-bold {{ $offered ? 'bg-ink text-white' : 'bg-ground' }}">Peut apporter</button>
                    <button type="button" wire:click="setMode('wanted')" class="min-h-10 rounded-full px-4 text-[13px] font-bold {{ ! $offered ? 'bg-ink text-white' : 'bg-ground' }}">Veut apprendre</button>
                    @if ($hasFilters)<button type="button" wire:click="clearFilters" class="min-h-10 rounded-full bg-ground px-4 text-[13px] font-semibold">Tous les filtres</button>@endif
                </div>
                <form wire:submit="$refresh" aria-label="Rechercher une personne" class="mt-4 flex min-w-0 gap-2">
                    <label for="recherche" class="sr-only">Rechercher une capacité ou un nom</label>
                    <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Une personne, une capacité…" class="min-h-12 min-w-0 flex-1 rounded-button border border-line bg-card px-4 text-[14px] outline-none focus:border-link">
                    <x-button type="submit" variant="accent">Rechercher</x-button>
                </form>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[13px] text-muted">
                    <span role="status" aria-live="polite">{{ $n }} {{ $n > 1 ? 'personnes trouvées' : 'personne trouvée' }}</span>
                    <span>Ordre alphabétique</span>
                </div>
            </div>
            <section aria-label="Résultats des personnes" class="space-y-3">
                <div wire:loading.delay role="status" class="text-[14px] text-muted">Recherche en cours…</div>
                @forelse ($people as $profile)
                    <x-person-discovery-card wire:key="personne-{{ $profile->id }}" :profile="$profile" />
                @empty
                    <div class="rounded-card border border-line bg-card p-6">
                        <h2 class="text-[18px] font-bold">Aucune personne ne correspond à votre recherche.</h2>
                        <p class="mt-2 text-[14px] text-muted">Essayez un autre domaine, une autre localité ou un autre type de capacité.</p>
                        @if ($hasFilters)<x-button type="button" variant="secondary" wire:click="clearFilters" class="mt-3">Effacer la recherche</x-button>@endif
                    </div>
                @endforelse
            </section>
            @if ($people->hasPages())
                <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
                    <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$people->onFirstPage()">Précédent</x-button>
                    <span class="text-[13px] text-muted">Page {{ $people->currentPage() }} sur {{ $people->lastPage() }}</span>
                    <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $people->hasMorePages()">Suivant</x-button>
                </nav>
            @endif
        </main>
        <aside aria-label="Découvrir le réseau" class="min-w-0 space-y-4 lg:col-start-2 xl:col-start-3">
            <section class="rounded-card border border-line bg-card p-4">
                <h2 class="text-[17px] font-extrabold">Recherche rapide</h2>
                <p class="mt-2 text-[13px] text-muted">Recherchez par nom ou capacité dans la liste centrale.</p>
                <a href="#recherche" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[14px] font-bold text-link"><x-icon name="search" :size="18"/> Rechercher</a>
            </section>
            <section class="rounded-card border border-line bg-card p-4">
                <h2 class="text-[17px] font-extrabold">Découvrir autrement</h2>
                <div class="mt-3 space-y-2">
                    <button type="button" wire:click="setMode('offered')" class="flex min-h-11 w-full items-center gap-2 text-left text-[13px] font-semibold"><x-icon name="group" :size="18"/> Capacités à partager</button>
                    <button type="button" wire:click="setMode('wanted')" class="flex min-h-11 w-full items-center gap-2 text-left text-[13px] font-semibold"><x-icon name="book" :size="18"/> Envies d'apprendre</button>
                </div>
            </section>
            <section class="rounded-card border border-line bg-card p-4">
                <h2 class="text-[17px] font-extrabold">Faites partie du réseau</h2>
                <p class="mt-2 text-[13px] leading-relaxed text-muted">Présentez vos capacités et vos expériences pour être découvert.</p>
                <x-button :href="$guest ? route('register') : route('espace.profil')" variant="accent" class="mt-4 w-full">{{ $guest ? 'Créer un compte' : 'Compléter mon profil' }}</x-button>
            </section>
        </aside>
    </div>
    <p class="mt-5 flex items-center justify-center gap-2 text-center text-[13px] text-muted"><x-icon name="lock" :size="16"/> Votre e-mail et votre téléphone restent privés.</p>
</div>
