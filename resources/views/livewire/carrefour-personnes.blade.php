@php
    $guest = ! auth()->check();
    $n = $people->total();
    $summary = $n.' '.($n > 1 ? 'personnes' : 'personne').' '.($this->query !== '' ? ($mode === 'all' ? 'pour' : ($mode === 'offered' ? ($n > 1 ? 'peuvent apporter' : 'peut apporter') : ($n > 1 ? 'veulent apprendre' : 'veut apprendre'))).' « '.\App\Support\Normalizer::display($this->query).' »' : 'à découvrir');
@endphp
<div class="person-directory min-w-0" x-data="{ filtersOpen: false }">
    <h1 class="sr-only">CARREFOUR — Trouvez qui peut vous aider</h1>
    <div class="mb-3 flex items-center justify-between lg:hidden">
        <h2 class="text-lg font-extrabold">Explorer les personnes</h2>
        <button type="button" x-on:click="filtersOpen = true" aria-controls="person-filters" x-bind:aria-expanded="filtersOpen.toString()" class="min-h-11 rounded-button border border-line bg-card px-4 font-bold">Filtres</button>
    </div>
    <div class="directory-grid">
        <div x-show="filtersOpen" x-cloak x-on:click="filtersOpen = false" class="fixed inset-0 z-30 bg-ink/50 lg:hidden" aria-hidden="true"></div>
        <aside id="person-filters" aria-label="Navigation et filtres des personnes" class="directory-filters directory-panel" x-bind:class="{ 'is-open': filtersOpen }" x-trap.noscroll="filtersOpen" x-on:keydown.escape.window="filtersOpen = false">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[17px] font-extrabold">Explorer les personnes</h2>
                <button type="button" x-on:click="filtersOpen = false" aria-label="Fermer les filtres" class="flex size-11 items-center justify-center lg:hidden"><x-icon name="close" :size="20" /></button>
            </div>
            <nav aria-label="Explorer" class="mt-3 space-y-1">
                @foreach (['all' => ['Toutes les personnes', 'group'], 'following' => ['Personnes suivies', 'user'], 'contacts' => ['Mes contacts', 'group'], 'suggestions' => ['Suggestions', 'compass'], 'nearby' => ['Autour de moi', 'pin']] as $value => [$label, $icon])
                    @if ($value === 'all' || ! $guest)
                        <button type="button" wire:click="setSection('{{ $value }}')" x-on:click="filtersOpen = false" aria-pressed="{{ $section === $value ? 'true' : 'false' }}" class="directory-nav {{ $section === $value ? 'bg-soft-blue text-link font-bold' : '' }}"><x-icon :name="$icon" :size="18" />{{ $label }}</button>
                    @else
                        <a href="{{ route('login') }}" class="directory-nav"><x-icon :name="$icon" :size="18" />{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
            <section class="directory-filter-section">
                <h3>Filtres par localité</h3>
                <label for="filtre-pays" class="sr-only">Pays</label>
                <select id="filtre-pays" wire:model.live="country" class="zumra-filter w-full"><option value="">Tous les pays</option>@foreach ($countries as $pays)<option value="{{ $pays->id }}">{{ $pays->name }}</option>@endforeach</select>
                <label for="filtre-ville" class="sr-only">Localité</label>
                <select id="filtre-ville" wire:model.live="city" class="zumra-filter mt-2 w-full"><option value="">Toutes les villes</option>@foreach ($cities as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }}</option>@endforeach</select>
            </section>
            <section class="directory-filter-section">
                <label for="filtre-domaine" class="mb-2 block font-extrabold">Domaines de compétence</label>
                @foreach ($domains->take(3) as $domaine)
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 text-[12px]"><input type="radio" name="domain-preview" wire:model.live="domain" value="{{ $domaine->id }}" class="size-4 shrink-0 accent-link">{{ $domaine->name }}</label>
                @endforeach
                <details class="mt-1"><summary class="min-h-11 cursor-pointer content-center text-[12px] font-bold text-link">Tous les domaines</summary>
                <select id="filtre-domaine" wire:model.live="domain" class="zumra-filter w-full"><option value="">Tous les domaines</option>@foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach</select></details>
            </section>
            <section class="directory-filter-section">
                <h3>Disponibilité</h3>
                <label class="flex min-h-11 cursor-pointer items-start gap-2 py-2 text-[13px]"><input type="checkbox" wire:model.live="recentOffer" class="mt-1 size-4 shrink-0 accent-link">Aide proposée ces 30 derniers jours</label>
                <p class="text-[11px] leading-relaxed text-muted">D’après les publications visibles. Disponibilité à confirmer avec la personne.</p>
            </section>
            <section class="directory-filter-section">
                <h3>Type de profil</h3>
                <p class="mb-2 text-[11px] text-muted">Selon les expériences renseignées</p>
                <label for="profile-type" class="sr-only">Type de profil selon les expériences</label>
                <select id="profile-type" wire:model.live="profileType" class="zumra-filter w-full">
                    <option value="">Tous les parcours</option>
                    <option value="professional">Expérience professionnelle</option>
                    <option value="volunteer">Expérience bénévole</option>
                    <option value="learning">Formation ou apprentissage</option>
                    <option value="personal">Expérience personnelle</option>
                </select>
            </section>
            <button type="button" wire:click="clearFilters" class="mt-4 min-h-11 w-full rounded-button border border-line px-3 text-[13px] font-bold">Réinitialiser les filtres</button>
            <button type="button" x-on:click="filtersOpen = false" class="mt-2 min-h-11 w-full rounded-button bg-accent px-3 font-bold lg:hidden">Voir les résultats</button>
        </aside>
        <section aria-label="Annuaire des personnes" class="directory-panel min-w-0">
            <div class="directory-tabs" aria-label="Découvrir les capacités">
                @foreach (['all' => 'Tous', 'offered' => 'Peut apporter', 'wanted' => 'Veut apprendre'] as $value => $label)
                    <button type="button" wire:click="setMode('{{ $value }}')" aria-pressed="{{ $mode === $value ? 'true' : 'false' }}" class="{{ $mode === $value ? 'bg-link text-white' : 'bg-ground text-muted' }}">{{ $label }}</button>
                @endforeach
                <button type="button" x-on:click="filtersOpen = window.innerWidth < 1024; $nextTick(() => (document.getElementById('filtre-domaine').closest('details').open = true, document.getElementById('filtre-domaine').focus()))" class="bg-ground text-muted">Par domaine</button>
                <button type="button" x-on:click="filtersOpen = window.innerWidth < 1024; $nextTick(() => document.getElementById('filtre-ville').focus())" class="bg-ground text-muted">Par localité</button>
            </div>
            <div class="my-4 flex flex-wrap items-center justify-between gap-2 text-[12px] text-muted">
                <span role="status" aria-live="polite">{{ $summary }}</span>
                <label class="flex items-center gap-1">Trier par : <select wire:model.live="sort" class="min-h-11 max-w-full bg-transparent font-semibold text-ink" aria-label="Trier les personnes"><option value="name">Ordre alphabétique</option><option value="recent">Nouveaux profils</option></select></label>
            </div>
            <section aria-label="Résultats des personnes" class="space-y-3">
                <div wire:loading.delay role="status" class="text-[14px] text-muted">Recherche en cours…</div>
                @forelse ($people as $profile)
                    <x-person-discovery-card wire:key="personne-{{ $profile->id }}" :profile="$profile" :conversation-id="$connections->get($profile->user_id)?->conversation_id" :following="in_array($profile->user_id, $following, true)" :directory="true" />
                @empty
                    <div class="rounded-card bg-ground p-5">
                        <h2 class="text-[17px] font-bold">@if ($hasFilters)Personne ne correspond à cette recherche.@else Personne n’a encore indiqué ce qu’elle peut apporter. @endif</h2>
                        @if ($section === 'suggestions')<p class="mt-2 text-muted">Les suggestions rapprochent vos envies d’apprendre des capacités proposées. Renseignez-les dans votre profil.</p>@endif
                        @if ($section === 'nearby' && ! $viewerProfile?->city_id)<p class="mt-2 text-muted">Renseignez votre ville dans votre profil pour découvrir les personnes de la même localité.</p>@endif
                        @if ($guest)<p class="mt-2 text-muted">Créez un compte pour partager vos capacités.</p><x-button :href="route('register')" class="mt-3">Créer un compte</x-button>@elseif (! $viewerProfile?->hasSkills())<p class="mt-2 text-muted">Vous n’apparaissez pas encore ici. Complétez votre profil pour être découvert.</p>@endif
                        <p class="mt-2 text-[14px] text-muted">Essayez une autre capacité, une autre localité ou un autre domaine.</p>
                        @if ($hasFilters)<x-button type="button" variant="secondary" wire:click="clearFilters" class="mt-3">Effacer la recherche</x-button>@endif
                    </div>
                @endforelse
            </section>
            @if ($people->hasPages())
                <nav aria-label="Pages de résultats" class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$people->onFirstPage()">Précédent</x-button>
                    <span class="text-[12px] text-muted">Page {{ $people->currentPage() }} sur {{ $people->lastPage() }}</span>
                    <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $people->hasMorePages()">Suivant</x-button>
                </nav>
            @endif
        </section>
        <aside aria-label="Découvrir le réseau" class="directory-discovery min-w-0 space-y-4">
            <section class="directory-panel">
                <h2>Recherche rapide</h2>
                <form wire:submit="$refresh" aria-label="Rechercher une personne" class="mt-3 space-y-3">
                    <label for="recherche" class="sr-only">Rechercher une capacité ou un nom</label>
                    <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" placeholder="Nom, compétence…" class="min-h-11 w-full min-w-0 rounded-button border border-line bg-card px-3 text-[13px]">
                    <x-button type="submit" variant="accent" class="w-full">Rechercher</x-button>
                </form>
            </section>
            <section class="directory-panel">
                <h2>Suggestions pour vous</h2>
                <p class="mt-2 text-[12px] text-muted">Des capacités pour ce que vous voulez apprendre.</p>
                @forelse ($suggestions as $profile)
                    @include('partials.person-directory-suggestion', ['showFollow' => true])
                @empty
                    <p class="mt-4 text-[13px] text-muted">{{ $guest ? 'Connectez-vous pour découvrir des profils selon vos envies d’apprendre.' : 'Renseignez vos envies d’apprendre pour découvrir des profils complémentaires.' }}</p>
                @endforelse
                @if ($suggestions->isNotEmpty())<button type="button" wire:click="setSection('suggestions')" class="mt-3 min-h-11 w-full border-t border-line text-[13px] font-bold text-link">Voir toutes les suggestions →</button>@endif
            </section>
            @if ($viewerProfile?->city_id)
                <section class="directory-panel">
                    <h2>Personnes près de vous</h2>
                    <p class="mt-2 text-[12px] text-muted">Même localité : {{ $viewerProfile->city?->label() }}</p>
                    @forelse ($nearbyPeople as $profile)
                        @include('partials.person-directory-suggestion', ['showFollow' => false])
                    @empty
                        <p class="mt-4 text-[13px] text-muted">Aucun profil visible dans votre localité pour le moment.</p>
                    @endforelse
                    @if ($nearbyPeople->isNotEmpty())<button type="button" wire:click="setSection('nearby')" class="mt-3 min-h-11 w-full border-t border-line text-[13px] font-bold text-link">Voir autour de moi →</button>@endif
                </section>
            @endif
            <section class="directory-panel">
                <h2>Faites partie du réseau</h2>
                <p class="mt-2 text-[13px] leading-relaxed text-muted">Présentez vos capacités et vos expériences pour être découvert.</p>
                <x-button :href="$guest ? route('register') : route('espace.profil')" variant="accent" class="mt-4 w-full">{{ $guest ? 'Créer un compte' : ($viewerProfile?->hasSkills() ? 'Mon profil' : 'Compléter mon profil') }}</x-button>
            </section>
        </aside>
    </div>
</div>
