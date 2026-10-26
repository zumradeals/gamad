@php
    $guest = ! auth()->check();
    $n = $people->total();
    $labels = ['all' => 'Tous', 'offered' => 'Peut apporter', 'wanted' => 'Veut apprendre', 'domains' => 'Par domaine', 'cities' => 'Par localité'];
    $lists = ['toutes' => ['Toutes les personnes', 'group'], 'suivies' => ['Personnes suivies', 'heart'], 'contacts' => ['Mes contacts', 'user'], 'suggestions' => ['Suggestions', 'idea'], 'autour' => ['Autour de moi', 'pin']];
    $sorts = ['nom' => 'Nom (A → Z)', 'nom-desc' => 'Nom (Z → A)', 'recents' => 'Arrivées récentes'];
    $summary = $n.' '.($n > 1 ? 'personnes' : 'personne').' '.($key !== '' ? ($mode === 'offered' ? ($n > 1 ? 'peuvent apporter' : 'peut apporter') : ($mode === 'wanted' ? ($n > 1 ? 'veulent apprendre' : 'veut apprendre') : ($n > 1 ? 'correspondent à' : 'correspond à'))).' « '.\App\Support\Normalizer::display($this->query).' »' : 'à découvrir');
    $domainsWithCount = $domains->filter(fn ($d) => ($domainCounts[$d->id] ?? 0) > 0 || $d->id === $domainId);
    $citiesWithCount = $cities->filter(fn ($c) => ($cityCounts[$c->id] ?? 0) > 0 || $c->id === $cityId);
@endphp
<div class="person-directory min-w-0" x-data="{ filters: false }">
    @if (\App\Support\Experience::initial())<a href="{{ auth()->check() ? route('fil') : route('home') }}" class="mb-3 inline-flex min-h-11 items-center gap-2 text-[14px] font-semibold lg:hidden">Retour à l’accueil</a>@endif
    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[255px_minmax(0,1fr)_340px]">
        <aside aria-label="Navigation et filtres des personnes" class="min-w-0 rounded-card border border-line bg-card p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[18px] font-extrabold">Explorer les personnes</h2>
                <button type="button" x-on:click="filters = ! filters" x-bind:aria-expanded="filters" aria-controls="panneau-filtres" class="inline-flex min-h-11 items-center rounded-button border border-line px-3 text-[13px] font-bold lg:hidden">Filtres @if ($hasFilters) ({{ ($key !== '' ? 1 : 0) + ($cityId !== '' ? 1 : 0) + ($domainId !== '' ? 1 : 0) }})@endif</button>
            </div>
            <nav aria-label="Explorer" class="mt-3 hidden space-y-1 lg:block" x-bind:class="filters ? '!block' : ''">
                @foreach ($lists as $slug => [$label, $icon])
                    @continue($guest && $slug !== 'toutes')
                    <button type="button" wire:click="setList('{{ $slug }}')" @if ($list === $slug) aria-current="page" @endif class="flex min-h-11 w-full items-center gap-2 rounded-button px-3 text-left text-[14px] {{ $list === $slug ? 'bg-soft-blue font-bold text-link' : 'font-semibold' }}"><x-icon :name="$icon" :size="18"/> {{ $label }}</button>
                @endforeach
            </nav>
            <div id="panneau-filtres" class="mt-4 hidden border-t border-line pt-4 lg:block" x-bind:class="filters ? '!block' : ''">
                <h3 class="mb-3 text-[16px] font-extrabold">Filtres</h3>
                <label for="filtre-ville" class="mb-2 block text-[13px] font-bold">Localité</label>
                <select id="filtre-ville" wire:model.live="city" class="zumra-filter w-full">
                    <option value="">Toutes les localités</option>
                    @foreach ($citiesWithCount as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }} ({{ $cityCounts[$ville->id] ?? 0 }})</option>@endforeach
                </select>
                <fieldset class="mt-4" x-data="{ more: {{ $domainId !== '' ? 'true' : 'false' }} }">
                    <legend class="mb-2 text-[13px] font-bold">Domaines de compétence</legend>
                    <div class="space-y-0.5">
                        @foreach ($domainsWithCount as $domaine)
                            <button type="button" @if ($loop->index >= 5) x-show="more" x-cloak @endif wire:click="$set('domain', '{{ $domainId === $domaine->id ? '' : $domaine->id }}')" aria-pressed="{{ $domainId === $domaine->id ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center gap-3 rounded-button px-1 text-left text-[14px] font-medium">
                                <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-[4px] border {{ $domainId === $domaine->id ? 'border-ink bg-ink text-white' : 'border-line' }}">@if ($domainId === $domaine->id)<x-icon name="check" :size="12"/>@endif</span>
                                <span class="min-w-0 flex-1 break-words">{{ $domaine->name }}</span><span class="shrink-0 text-muted">{{ $domainCounts[$domaine->id] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                    @if ($domainsWithCount->count() > 5)<button type="button" x-on:click="more = ! more" class="mt-1 inline-flex min-h-11 items-center text-[14px] font-bold text-link"><span x-text="more ? 'Voir moins' : 'Voir plus'">Voir plus</span></button>@endif
                </fieldset>
                <button type="button" wire:click="clearFilters" class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-button border border-ink px-3 text-[14px] font-bold">Réinitialiser les filtres</button>
            </div>
        </aside>
        <div class="min-w-0 space-y-4">
            <h1 class="sr-only">Personnes — Trouvez qui peut vous aider</h1>
            <div class="rounded-card border border-line bg-card p-4">
                <div role="tablist" aria-label="Type de capacité" class="-mx-1 flex max-w-full gap-2 overflow-x-auto px-1 py-1">
                    @foreach ($labels as $slug => $label)
                        <button type="button" role="tab" aria-selected="{{ $mode === $slug ? 'true' : 'false' }}" wire:click="setMode('{{ $slug }}')" class="min-h-11 shrink-0 rounded-full px-4 text-[13px] font-bold {{ $mode === $slug ? 'bg-link text-white' : 'border border-line bg-card' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="mt-4 xl:hidden">
                    <label for="recherche" class="sr-only">Rechercher une capacité ou un nom</label>
                    <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Une personne, une capacité…" class="min-h-12 w-full min-w-0 rounded-button border border-line bg-card px-4 text-[14px] outline-none focus:border-link">
                </div>
                @if ($mode === 'domains' || $mode === 'cities')
                    <div class="mt-4">
                        <p class="mb-2 text-[13px] font-bold">{{ $mode === 'domains' ? 'Choisissez un domaine' : 'Choisissez une localité' }}</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($mode === 'domains' ? $domainsWithCount : $citiesWithCount as $item)
                                @php($current = $mode === 'domains' ? $domainId : $cityId)
                                <button type="button" wire:click="$set('{{ $mode === 'domains' ? 'domain' : 'city' }}', '{{ $current === $item->id ? '' : $item->id }}')" aria-pressed="{{ $current === $item->id ? 'true' : 'false' }}" class="min-h-11 max-w-full rounded-chip px-3 text-left text-[13px] font-semibold {{ $current === $item->id ? 'bg-ink text-white' : 'border border-line' }}">{{ $mode === 'domains' ? $item->name : $item->label() }} · {{ ($mode === 'domains' ? $domainCounts : $cityCounts)[$item->id] ?? 0 }}</button>
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
            <section aria-label="Résultats des personnes" class="space-y-3">
                <div wire:loading.delay role="status" class="text-[14px] text-muted">Recherche en cours…</div>
                @forelse ($people as $profile)
                    <x-person-discovery-card wire:key="personne-{{ $profile->id }}" :profile="$profile" :followed="in_array($profile->user_id, $followedIds, true)" :contact="array_key_exists($profile->user_id, $contacts)" :conversation="$contacts[$profile->user_id] ?? null" :self="auth()->id() === $profile->user_id" :can-act="! $guest" />
                @empty
                    <div class="rounded-card border border-line bg-card p-6">
                        <h2 class="text-[18px] font-bold">
                            @if ($list === 'suivies')Vous ne suivez encore personne.
                            @elseif ($list === 'contacts')Vous n’avez pas encore de contact.
                            @elseif ($list === 'suggestions')Aucune suggestion pour le moment.
                            @elseif ($list === 'autour')@if ($viewerProfile?->city_id)Personne d’autre n’a indiqué cette localité.@else Indiquez votre localité pour trouver des personnes autour de vous.@endif
                            @elseif ($hasFilters)Personne ne correspond à cette recherche.
                            @else {{ $mode === 'wanted' ? 'Personne n’a encore indiqué ce qu’elle veut apprendre.' : ($mode === 'offered' ? 'Personne n’a encore indiqué ce qu’elle peut apporter.' : 'Personne n’a encore indiqué de capacité.') }} @endif
                        </h2>
                        @if ($list === 'suggestions' && ! $viewerProfile?->hasSkills())<p class="mt-2 text-muted">Indiquez ce que vous pouvez apporter ou apprendre : les suggestions s’appuient sur ces capacités.</p>@endif
                        @if ($list === 'toutes')
                            @if ($guest)<p class="mt-2 text-muted">Créez un compte pour partager vos capacités.</p><x-button :href="route('register')" class="mt-3">Créer un compte</x-button>@elseif (! $viewerProfile?->hasSkills())<p class="mt-2 text-muted">Vous n’apparaissez pas encore ici. Complétez votre profil pour être découvert.</p>@endif
                            <p class="mt-2 text-[14px] text-muted">Essayez une autre capacité, une autre localité ou un autre domaine.</p>
                        @else
                            <button type="button" wire:click="setList('toutes')" class="mt-3 inline-flex min-h-11 items-center text-[14px] font-bold text-link">Voir toutes les personnes</button>
                        @endif
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
        </div>
        <aside aria-label="Découvrir le réseau" class="min-w-0 space-y-4 lg:col-start-2 xl:col-start-3">
            <section class="hidden rounded-card border border-line bg-card p-4 xl:block">
                <h2 class="text-[17px] font-extrabold">Recherche rapide</h2>
                <form wire:submit="$refresh" class="mt-3 space-y-3">
                    <label for="recherche-rapide" class="sr-only">Un nom ou une capacité</label>
                    <div class="flex items-center gap-2 rounded-button border border-line bg-card px-3 focus-within:border-link"><x-icon name="search" :size="18"/><input id="recherche-rapide" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Nom, compétence…" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent px-0 text-[14px] outline-none focus:ring-0"></div>
                    <x-button type="submit" variant="accent" class="w-full">Rechercher</x-button>
                </form>
            </section>
            @foreach ([['suggestions', 'Suggestions pour vous', $suggestions, 'sugg', 'Voir plus de suggestions →', 'suggestions'], ['nearby', 'Personnes près de vous', $nearby, 'proche', 'Voir tout', 'autour']] as [$id, $title, $items, $prefix, $more, $target])
                @continue($items->isEmpty())
                <section aria-labelledby="{{ $id }}-titre" class="rounded-card border border-line bg-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h2 id="{{ $id }}-titre" class="text-[17px] font-extrabold">{{ $title }}</h2>
                        @if ($id === 'nearby')<button type="button" wire:click="setList('{{ $target }}')" class="inline-flex min-h-11 items-center text-[13px] font-bold text-link">{{ $more }}</button>@endif
                    </div>
                    <p class="text-[12px] text-muted">{{ $id === 'nearby' ? 'Même localité déclarée : '.$viewerProfile->city->label().'.' : 'D’après vos capacités et envies d’apprendre, par ordre alphabétique.' }}</p>
                    <ul class="mt-3 space-y-3">
                        @foreach ($items as $item)
                            @php($isFollowed = in_array($item->user_id, $followedIds, true))
                            <li class="flex min-w-0 items-center gap-3" wire:key="{{ $prefix }}-{{ $item->id }}">
                                <x-avatar :profile="$item" :size="44" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('personnes.show', $item->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $item->user->name }}</a>
                                    <p class="-mt-2 break-words text-[12px] text-muted">{{ $id === 'suggestions' ? $item->reason : ($item->headline ?: $item->domains->pluck('name')->take(2)->join(' · ')) }}</p>
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
                    <li class="flex items-start gap-2"><x-icon name="user" :size="18"/> Indiquez vos capacités pour être trouvé</li>
                    <li class="flex items-start gap-2"><x-icon name="search" :size="18"/> Trouvez des personnes selon vos besoins</li>
                    <li class="flex items-start gap-2"><x-icon name="chat" :size="18"/> Échangez et collaborez facilement</li>
                </ul>
                <x-button :href="$guest ? route('register') : route('espace.profil')" variant="accent" class="mt-4 w-full">{{ $guest ? 'Créer un compte' : ($viewerProfile?->hasSkills() ? 'Gérer mon profil' : 'Compléter mon profil') }} <x-icon name="arrow-right" :size="16" class="ml-1.5"/></x-button>
            </section>
        </aside>
    </div>
    <p class="mt-5 flex items-center justify-center gap-2 text-center text-[13px] text-muted"><x-icon name="lock" :size="16"/> Votre e-mail et votre téléphone restent privés.</p>
</div>
