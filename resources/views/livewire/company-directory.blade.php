@php
    $guest = ! auth()->check();
    $n = $companies->total();
    $labels = ['all' => 'Toutes', 'opportunities' => 'Opportunités', 'sectors' => 'Par secteur', 'cities' => 'Par localité'];
    $lists = ['toutes' => ['Toutes les entreprises', 'project'], 'suivies' => ['Entreprises suivies', 'heart'], 'suggestions' => ['Suggestions', 'idea'], 'autour' => ['Autour de moi', 'pin']];
    $sorts = ['nom' => 'Nom (A → Z)', 'nom-desc' => 'Nom (Z → A)', 'recents' => 'Vérifiées récemment'];
    $summary = $n.' '.($n > 1 ? 'entreprises trouvées' : 'entreprise trouvée');
    $activeFilters = ($search !== '' ? 1 : 0) + ($cityKey !== '' ? 1 : 0) + ($sectorKey !== '' ? 1 : 0);
    $followedIds = $followedIds ?? [];
@endphp
<div class="company-directory min-w-0" x-data="{ filters: false }">
    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[255px_minmax(0,1fr)_340px]">
        <aside aria-label="Navigation et filtres des entreprises" class="min-w-0 rounded-card border border-line bg-card p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[18px] font-extrabold">Explorer les entreprises</h2>
                <button type="button" x-on:click="filters = ! filters" x-bind:aria-expanded="filters" aria-controls="panneau-filtres-entreprises" class="inline-flex min-h-11 items-center rounded-button border border-line px-3 text-[13px] font-bold lg:hidden">Filtres @if ($activeFilters > 0)({{ $activeFilters }})@endif</button>
            </div>
            <nav aria-label="Explorer" class="mt-3 hidden space-y-1 lg:block" x-bind:class="filters ? '!block' : ''">
                @foreach ($lists as $slug => [$label, $icon])
                    @continue($guest && $slug !== 'toutes')
                    <button type="button" wire:click="setList('{{ $slug }}')" @if ($list === $slug) aria-current="page" @endif class="flex min-h-11 w-full items-center gap-2 rounded-button px-3 text-left text-[14px] {{ $list === $slug ? 'bg-soft-blue font-bold text-link' : 'font-semibold' }}"><x-icon :name="$icon" :size="18"/> {{ $label }}</button>
                @endforeach
            </nav>
            <div id="panneau-filtres-entreprises" class="mt-4 hidden border-t border-line pt-4 lg:block" x-bind:class="filters ? '!block' : ''">
                <h3 class="mb-3 text-[16px] font-extrabold">Filtres</h3>
                <label for="filtre-localite" class="mb-2 block text-[13px] font-bold">Localité</label>
                <select id="filtre-localite" wire:model.live="city" class="zumra-filter w-full">
                    <option value="">Toutes les localités</option>
                    @foreach ($cities as $ville)<option value="{{ $ville->facet_key }}">{{ $ville->label }} ({{ $ville->total }})</option>@endforeach
                </select>
                <fieldset class="mt-4" x-data="{ more: {{ $sectorKey !== '' ? 'true' : 'false' }} }">
                    <legend class="mb-2 text-[13px] font-bold">Secteurs d’activité</legend>
                    <div class="space-y-0.5">
                        @forelse ($sectors as $secteur)
                            <button type="button" @if ($loop->index >= 5) x-show="more" x-cloak @endif wire:click="$set('sector', '{{ $sectorKey === $secteur->facet_key ? '' : addslashes($secteur->facet_key) }}')" aria-pressed="{{ $sectorKey === $secteur->facet_key ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center gap-3 rounded-button px-1 text-left text-[14px] font-medium">
                                <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-[4px] border {{ $sectorKey === $secteur->facet_key ? 'border-ink bg-ink text-white' : 'border-line' }}">@if ($sectorKey === $secteur->facet_key)<x-icon name="check" :size="12"/>@endif</span>
                                <span class="min-w-0 flex-1 break-words">{{ $secteur->label }}</span><span class="shrink-0 text-muted">{{ $secteur->total }}</span>
                            </button>
                        @empty
                            <p class="text-[13px] text-muted">Aucun secteur à proposer pour le moment.</p>
                        @endforelse
                    </div>
                    @if ($sectors->count() > 5)<button type="button" x-on:click="more = ! more" class="mt-1 inline-flex min-h-11 items-center text-[14px] font-bold text-link"><span x-text="more ? 'Voir moins' : 'Voir plus'">Voir plus</span></button>@endif
                </fieldset>
                <button type="button" wire:click="clearFilters" class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-button border border-ink px-3 text-[14px] font-bold">Réinitialiser les filtres</button>
            </div>
        </aside>
        <main class="min-w-0 space-y-4">
            <h1 class="sr-only">Entreprises — Découvrez les entreprises vérifiées</h1>
            <div class="rounded-card border border-line bg-card p-4">
                <div role="tablist" aria-label="Type d’entreprise" class="-mx-1 flex max-w-full gap-2 overflow-x-auto px-1 py-1">
                    @foreach ($labels as $slug => $label)
                        <button type="button" role="tab" aria-selected="{{ $mode === $slug ? 'true' : 'false' }}" wire:click="setMode('{{ $slug }}')" class="min-h-11 shrink-0 rounded-full px-4 text-[13px] font-bold {{ $mode === $slug ? 'bg-link text-white' : 'border border-line bg-card' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="mt-4 xl:hidden">
                    <label for="company-search" class="sr-only">Rechercher une entreprise</label>
                    <input id="company-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" autocomplete="off" placeholder="Nom, secteur, ville…" class="min-h-12 w-full min-w-0 rounded-button border border-line bg-card px-4 text-[14px] outline-none focus:border-link">
                </div>
                @if ($mode === 'sectors' || $mode === 'cities')
                    @php($groups = $mode === 'sectors' ? $sectors : $cities)
                    @php($current = $mode === 'sectors' ? $sectorKey : $cityKey)
                    <div class="mt-4">
                        <p class="mb-2 text-[13px] font-bold">{{ $mode === 'sectors' ? 'Choisissez un secteur' : 'Choisissez une localité' }}</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($groups as $group)
                                <button type="button" wire:click="$set('{{ $mode === 'sectors' ? 'sector' : 'city' }}', '{{ $current === $group->facet_key ? '' : addslashes($group->facet_key) }}')" aria-pressed="{{ $current === $group->facet_key ? 'true' : 'false' }}" class="min-h-11 max-w-full rounded-chip px-3 text-left text-[13px] font-semibold {{ $current === $group->facet_key ? 'bg-ink text-white' : 'border border-line' }}">{{ $group->label }} · {{ $group->total }}</button>
                            @empty
                                <span class="text-[13px] text-muted">Aucune donnée à regrouper pour le moment.</span>
                            @endforelse
                        </div>
                    </div>
                @endif
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p role="status" aria-live="polite" class="text-[14px] text-muted">{{ $summary }}</p>
                    <label class="flex items-center gap-2 text-[13px] text-muted">Ordre :
                        <select wire:model.live="sort" class="zumra-filter !w-auto font-semibold">
                            @foreach ($sorts as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            </div>
            <section aria-label="Résultats des entreprises" class="space-y-3" wire:loading.class="opacity-60">
                @forelse ($companies as $company)
                    <x-company-discovery-card wire:key="company-{{ $company->id }}" :company="$company" :followed="in_array($company->id, $followedIds, true)" :own="auth()->id() === $company->owner_id" :can-act="! $guest" />
                @empty
                    <div class="rounded-card border border-line bg-card p-6">
                        <h2 class="text-[18px] font-bold">
                            @if ($list === 'suivies')Vous ne suivez encore aucune entreprise.
                            @elseif ($list === 'suggestions')Aucune suggestion pour le moment.
                            @elseif ($list === 'autour')@if ($viewerCity)Aucune entreprise vérifiée n’a indiqué cette localité.@else Indiquez votre localité pour trouver des entreprises autour de vous.@endif
                            @elseif ($mode === 'opportunities' && ! $hasFilters)Aucune entreprise ne recherche de compétences pour le moment.
                            @elseif ($hasFilters)Aucune entreprise ne correspond à cette recherche.
                            @else Aucune entreprise à afficher pour l’instant. @endif
                        </h2>
                        @if ($list === 'suggestions')<p class="mt-2 text-[14px] text-muted">Les suggestions s’appuient sur les capacités que vous proposez dans votre profil.</p>@endif
                        @if ($list === 'toutes' && ! $hasFilters && $mode === 'all')<p class="mt-2 text-[14px] text-muted">Les pages vérifiées apparaîtront ici. Aucun exemple fictif n’est présenté.</p>@endif
                        @if ($list !== 'toutes')<button type="button" wire:click="setList('toutes')" class="mt-3 inline-flex min-h-11 items-center text-[14px] font-bold text-link">Voir toutes les entreprises</button>@endif
                        @if ($hasFilters)<x-button type="button" variant="secondary" wire:click="clearFilters" class="mt-3">Effacer la recherche</x-button>@endif
                    </div>
                @endforelse
            </section>
            @if ($companies->hasPages())
                <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
                    <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$companies->onFirstPage()">Précédent</x-button>
                    <span class="text-[13px] text-muted">Page {{ $companies->currentPage() }} sur {{ $companies->lastPage() }}</span>
                    <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $companies->hasMorePages()">Suivant</x-button>
                </nav>
            @endif
            <p class="flex items-start justify-center gap-2 text-center text-[13px] text-muted"><x-icon name="shield" :size="16" class="mt-0.5 shrink-0"/> Seules les pages dont l’immatriculation a été vérifiée par GAMAD figurent ici. Cette vérification ne certifie ni les prestations, ni l’identité du gestionnaire.</p>
        </main>
        <aside aria-label="Découvrir les entreprises" class="min-w-0 space-y-4 lg:col-start-2 xl:col-start-3">
            <section class="hidden rounded-card border border-line bg-card p-4 xl:block">
                <h2 class="text-[17px] font-extrabold">Recherche rapide</h2>
                <form wire:submit="$refresh" class="mt-3 space-y-3">
                    <label for="recherche-rapide-entreprises" class="sr-only">Nom, secteur ou ville</label>
                    <div class="flex items-center gap-2 rounded-button border border-line bg-card px-3 focus-within:border-link"><x-icon name="search" :size="18"/><input id="recherche-rapide-entreprises" type="search" wire:model.live.debounce.300ms="search" maxlength="100" autocomplete="off" placeholder="Nom, secteur, ville…" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent px-0 text-[14px] outline-none focus:ring-0"></div>
                    <x-button type="submit" variant="accent" class="w-full">Rechercher</x-button>
                </form>
            </section>
            @foreach ([['suggestions', 'Suggestions pour vous', $suggestions, 'sugg', 'Voir plus de suggestions →', 'suggestions'], ['nearby', 'Entreprises près de vous', $nearby, 'proche', 'Voir tout', 'autour']] as [$id, $title, $items, $prefix, $more, $target])
                @continue($items->isEmpty())
                <section aria-labelledby="{{ $id }}-entreprises-titre" class="rounded-card border border-line bg-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h2 id="{{ $id }}-entreprises-titre" class="text-[17px] font-extrabold">{{ $title }}</h2>
                        @if ($id === 'nearby')<button type="button" wire:click="setList('{{ $target }}')" class="inline-flex min-h-11 items-center text-[13px] font-bold text-link">{{ $more }}</button>@endif
                    </div>
                    <p class="text-[12px] text-muted">{{ $id === 'nearby' ? 'Même localité déclarée : '.$viewerCity->label().'.' : 'D’après les capacités que vous proposez, par ordre alphabétique.' }}</p>
                    <ul class="mt-3 space-y-3">
                        @foreach ($items as $item)
                            @php($isFollowed = in_array($item->id, $followedIds, true))
                            <li class="flex min-w-0 items-center gap-3" wire:key="{{ $prefix }}-{{ $item->id }}">
                                <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-button border border-line bg-soft-blue text-[14px] font-extrabold">
                                    @if ($item->logo_path)<img src="{{ route('entreprises.visual', [$item, 'logo']) }}" alt="" loading="lazy" class="size-full bg-white object-contain">@else{{ mb_strtoupper(mb_substr($item->name, 0, 2)) }}@endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('entreprises.show', $item) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $item->name }}</a>
                                    <p class="-mt-2 break-words text-[12px] text-muted">{{ $id === 'suggestions' ? ($item->reason ?? $item->sector) : $item->sector }}</p>
                                </div>
                                <button type="button" wire:click="follow('{{ $item->id }}', {{ $isFollowed ? 'false' : 'true' }})" class="min-h-11 shrink-0 rounded-button border border-ink px-3 text-[13px] font-bold">{{ $isFollowed ? 'Suivie' : 'Suivre' }}</button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($id === 'suggestions')<button type="button" wire:click="setList('suggestions')" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold text-link">{{ $more }}</button>@endif
                </section>
            @endforeach
            <section class="rounded-card border border-line bg-card p-4">
                <h2 class="text-[17px] font-extrabold">Faites partie du réseau</h2>
                <ul class="mt-3 space-y-2 text-[14px]">
                    <li class="flex items-start gap-2"><x-icon name="project" :size="18" class="mt-0.5 shrink-0"/> Présentez votre entreprise sur GAMAD</li>
                    <li class="flex items-start gap-2"><x-icon name="search" :size="18" class="mt-0.5 shrink-0"/> Soyez découvert pour vos savoir-faire</li>
                    <li class="flex items-start gap-2"><x-icon name="chat" :size="18" class="mt-0.5 shrink-0"/> Entrez en relation avec des personnes</li>
                </ul>
                <x-button :href="$guest ? route('register') : ($ownsCompany ? route('espace', ['rubrique' => 'entreprise']) : route('entreprises.create'))" variant="accent" class="mt-4 w-full">{{ $guest ? 'Créer un compte' : ($ownsCompany ? 'Gérer mon entreprise' : 'Créer le profil de mon entreprise') }} <x-icon name="arrow-right" :size="16" class="ml-1.5"/></x-button>
            </section>
        </aside>
    </div>
</div>
