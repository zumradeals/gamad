@php
    $tabs = ['tout' => 'Tout', 'savoirs' => 'Savoirs', 'realisations' => 'Réalisations', 'besoins' => 'Besoins', 'personnes' => 'Personnes', 'entreprises' => 'Entreprises'];
    // Autres vues, déjà disponibles : elles restent accessibles sans allonger la barre d'onglets.
    $views = ['suivis' => 'Mes suivis', 'decouvrir' => 'À découvrir', 'partages' => 'Partages', 'apprendre' => 'Envies d’apprendre', 'aides' => 'Aides proposées', 'zumra' => 'ZUMRA', 'projets' => 'Projets'];
    if (\App\Support\Experience::initial()) {
        unset($views['zumra'], $views['projets']);
    }
    $navigation = [
        ['Fil d’actualité', route('fil'), 'feed', true],
        ['Mes publications', route('espace', ['rubrique' => 'publications']), 'document', false],
        ['Mes enregistrements', route('espace.enregistrements'), 'book', false],
        ['Mes échanges', route('espace', ['rubrique' => 'echanges']), 'chat', false],
        ['À faire maintenant', route('espace').'#afaire', 'tasks', false],
        ['Paramètres', route('espace', ['rubrique' => 'parametres']), 'settings', false],
    ];
    $directories = [
        ['Trouver une personne', 'Rechercher par capacité ou par nom', route('personnes.index'), 'group', 'bg-type-call-bg text-type-call-fg'],
        ['Entreprises', 'Des organisations à découvrir', route('entreprises.index'), 'project', 'bg-type-knowledge-bg text-type-knowledge-fg'],
        ['Besoins à découvrir', 'Voir où vous pouvez aider', route('fil', ['filtre' => 'besoins']), 'heart', 'bg-type-need-bg text-type-need-fg'],
        ['Savoirs à explorer', 'Des connaissances à partager', route('fil', ['filtre' => 'savoirs']), 'idea', 'bg-soft-blue text-link'],
    ];
@endphp

<div class="feed-layout">
    <h1 class="sr-only">Accueil</h1>

    <aside aria-label="Mon profil" class="feed-profile">
        <section class="feed-panel overflow-hidden">
            <div class="feed-cover" aria-hidden="true"></div>
            <div class="px-4 pb-5">
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="relative -mt-10 mb-3 inline-flex rounded-full border-4 border-card" aria-label="Voir mon profil">
                    <x-avatar :profile="$profile" :size="76" />
                </a>
                <h2 class="flex flex-wrap items-center gap-x-2 break-words text-[19px] font-extrabold leading-tight">{{ $viewer->name }}@if ($identityVerified)<span class="inline-flex size-[18px] items-center justify-center rounded-full bg-link text-white" title="Identité vérifiée par GAMAD"><x-icon name="check" :size="12"/><span class="sr-only">Identité vérifiée par GAMAD</span></span>@endif</h2>
                @if ($profile->city)
                    <p class="mt-1 flex items-center gap-1 text-[13px] text-muted"><x-icon name="pin" :size="14" />{{ $profile->city->label() }}</p>
                @endif
                @if ($profile->bio)
                    <p class="mt-2 whitespace-pre-line break-words text-[13px] leading-relaxed text-muted">{{ $profile->bio }}</p>
                @endif

                <div class="mt-4 space-y-4 border-t border-line-soft pt-4">
                    <div>
                        <h3 class="mb-2 text-[11px] font-extrabold tracking-wide text-type-knowledge-fg">JE PEUX APPORTER</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($profile->offeredSkills as $skill)
                                <a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}" class="feed-skill bg-type-knowledge-bg text-type-knowledge-fg hover:text-type-knowledge-fg">{{ $skill->name }}</a>
                            @empty
                                <a href="{{ route('espace.profil') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold">Ajouter ce que je peux apporter</a>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-2 text-[11px] font-extrabold tracking-wide text-type-learning-fg">JE VEUX APPRENDRE</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($profile->wantedSkills as $skill)
                                <a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'wanted']) }}" class="feed-skill bg-type-learning-bg text-type-learning-fg hover:text-type-learning-fg">{{ $skill->name }}</a>
                            @empty
                                <a href="{{ route('espace.profil') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold">Ajouter ce que je veux apprendre</a>
                            @endforelse
                        </div>
                    </div>
                </div>
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link underline underline-offset-4">Voir mon profil public <x-icon name="arrow-right" :size="16" /></a>
            </div>
        </section>

        <nav aria-label="Mes raccourcis" class="feed-panel p-2">
            @foreach ($navigation as [$label, $url, $icon, $current])
                <a href="{{ $url }}" @if ($current) aria-current="page" @endif @class(['flex min-h-11 items-center gap-3 rounded-button px-3 text-[14px] no-underline', 'bg-soft-blue font-bold text-link' => $current, 'font-semibold text-ink hover:bg-ground' => ! $current])><x-icon :name="$icon" :size="20" /><span class="min-w-0 flex-1">{{ $label }}</span></a>
            @endforeach
        </nav>

        @if (array_sum($activity) > 0)
            <section aria-labelledby="mon-activite" class="feed-panel p-4">
                <h2 id="mon-activite" class="text-[15px] font-extrabold">Mon activité sur GAMAD</h2>
                <dl class="mt-3 grid grid-cols-3 divide-x divide-line-soft text-center">
                    @foreach ($activity as $label => $total)
                        <div class="px-1"><dd class="text-[22px] font-extrabold leading-none">{{ $total }}</dd><dt class="mt-1 break-words text-[10px] tracking-tight text-muted">{{ $label }}</dt></div>
                    @endforeach
                </dl>
            </section>
        @endif
    </aside>

    <div class="feed-main">
        @unless (\App\Support\Experience::initial())
        <section aria-labelledby="recherche-fil" class="feed-panel p-4">
            <h2 id="recherche-fil" class="mb-3 text-[17px] font-extrabold">Que cherchez-vous ?</h2>
            <form wire:submit="$refresh" class="flex flex-col gap-3">
                <label for="fil-query" class="text-[13px] font-semibold text-muted">Un savoir, un besoin ou une personne</label>
                <div class="flex min-w-0 items-center gap-2 rounded-button border border-chip-line px-3 focus-within:outline focus-within:outline-[3px] focus-within:outline-link"><x-icon name="search" :size="20" class="shrink-0" /><input id="fil-query" type="search" wire:model.live.debounce.300ms="query" maxlength="100" placeholder="Une capacité, un mot ou un nom…" class="min-h-12 min-w-0 flex-1 border-0 bg-transparent text-[14px] outline-none"></div>
                <div class="flex flex-wrap gap-2"><x-button type="submit" variant="secondary" class="!px-3 !text-[13px]">Dans les publications</x-button><x-button type="button" wire:click="findPeople" variant="accent" class="gap-2 !px-3 !text-[13px]"><x-icon name="group" :size="18" />Trouver une personne</x-button>@if ($searchText !== '')<button type="button" wire:click="clearSearch" class="min-h-11 text-[13px] font-semibold underline">Effacer</button>@endif</div>
            </form>
            @if ($searchText !== '')<p class="mt-3 text-[13px] text-muted" role="status">Publications contenant « {{ $searchText }} » dans leur texte, leurs capacités ou le nom de leur auteur.</p>@endif
        </section>
        @endunless
        <livewire:fil-composer />

        @if (\App\Support\Experience::initial() && $searchText !== '')
            <div class="flex flex-wrap items-center justify-between gap-2 px-1 text-[13px]">
                <p class="min-w-0 text-muted [overflow-wrap:anywhere]" role="status">Recherche dans les publications : « {{ $searchText }} »</p>
                <button type="button" wire:click="clearSearch" class="min-h-11 shrink-0 font-semibold underline">Effacer la recherche</button>
            </div>
        @endif

        <section aria-label="Filtrer les publications" class="feed-panel p-4">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <nav aria-label="Catégories" class="-mx-1 flex max-w-full gap-2 overflow-x-auto px-1 py-1">
                    @foreach ($tabs as $key => $label)
                        <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" @class(['min-h-11 shrink-0 rounded-full px-4 text-[13px] font-bold', 'bg-link text-white' => $filter === $key, 'bg-ground text-ink' => $filter !== $key])>{{ $label }}</button>
                    @endforeach
                </nav>
                <p class="text-[13px] text-muted">Tri : <span class="font-semibold text-ink">Plus récents</span></p>
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[12px] text-muted">
                <span>{{ match ($filter) { 'suivis' => 'Publications des personnes et entreprises suivies', 'decouvrir' => 'Publications publiques de nouvelles personnes et entreprises', 'partages' => 'Partages et réalisations récents', 'entreprises' => 'Publications des entreprises vérifiées', 'personnes' => 'Publications des personnes', default => 'Les publications les plus récentes' } }}, sans classement de popularité.</span>
                <label class="flex items-center gap-2">Autres vues
                    <select wire:change="setFilter($event.target.value)" class="zumra-filter !w-auto">
                        <option value="" @selected(array_key_exists($filter, $tabs))>—</option>
                        @foreach ($views as $key => $label)<option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
        </section>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif
        <div wire:loading.delay wire:target="setFilter, more" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

        <div class="feed-posts" wire:loading.class="opacity-60" wire:target="setFilter, more">
            @forelse ($posts as $post)
                <x-publication-card compact wire:key="publication-{{ $post->id }}" :publication="$post" :viewer="$viewer" :gesture="$gestures[$post->id] ?? null" :supported="$supported" :saved="in_array($post->id, $savedIds, true)" :show-follow="true" :followed="in_array($post->author_id, $followedAuthorIds, true)" :managed="$managedZumraIds" :member-of="$memberOf" />
                @if ($sponsored && $loop->iteration === min(4, $posts->count()))
                    <div wire:key="sponsorise-{{ $sponsored->id }}" class="feed-sponsored">
                        @include('livewire.partials.publicite-apercu', ['name' => $sponsored->advertiserName(), 'verified' => (bool) $sponsored->company, 'title' => (string) $sponsored->title, 'body' => (string) $sponsored->body, 'imageUrl' => $sponsored->image_path ? route('publicite.image', $sponsored) : null, 'ctaLabel' => $sponsored->cta?->label() ?? '', 'ctaUrl' => route('publicite.cta', $sponsored), 'external' => $sponsored->cta === \App\Enums\AdCta::Link, 'why' => $sponsoredWhy, 'menu' => true, 'adId' => $sponsored->id])
                    </div>
                @endif
            @empty
                <section aria-labelledby="vide" class="feed-panel flex flex-col items-start gap-3 p-5 md:p-6">
                    <span class="flex size-11 items-center justify-center rounded-button bg-type-call-bg text-type-call-fg" aria-hidden="true"><x-icon name="idea" :size="24" /></span>
                    @if ($searchText !== '')
                        <h2 id="vide" class="text-[18px] font-extrabold">Aucune publication ne correspond à cette recherche.</h2>
                        <p class="text-[14px] text-muted">Essayez un autre mot, ou cherchez une personne par ses capacités.</p>
                        <x-button type="button" variant="secondary" wire:click="clearSearch">Effacer la recherche</x-button>
                    @elseif ($filter === 'suivis')
                        <h2 id="vide" class="text-[18px] font-extrabold">Rien de nouveau chez les personnes ou entreprises suivies.</h2>
                        <p class="text-[14px] text-muted">Découvrez d'autres parcours puis choisissez librement qui suivre.</p>
                        <x-button type="button" variant="secondary" wire:click="setFilter('decouvrir')">Découvrir des personnes</x-button>
                    @elseif ($filter === 'decouvrir')
                        <h2 id="vide" class="text-[18px] font-extrabold">Aucune nouvelle publication à découvrir.</h2>
                        <p class="text-[14px] text-muted">Les publications publiques de personnes que vous ne suivez pas apparaîtront ici, de la plus récente à la plus ancienne.</p>
                        <x-button type="button" variant="secondary" wire:click="setFilter('tout')">Voir tout l'Accueil</x-button>
                    @elseif ($filter === 'tout')
                        <p><strong id="vide" class="text-[18px] font-extrabold">Les publications apparaîtront ici.</strong> <span class="text-muted"><a href="{{ route('espace.profil') }}" class="font-semibold">Commencez par compléter votre profil.</a></span></p>
                    @else
                        <h2 id="vide" class="text-[18px] font-extrabold">Rien dans cette catégorie pour l’instant.</h2>
                    @endif
                    @if ($filter === 'partages')
                        <p class="text-[14px] leading-relaxed text-muted">Les expériences et réalisations apparaîtront ici dès qu'elles seront partagées.</p>
                        <x-button :href="route('agir.publier', 'partage')" variant="accent">Partager mon expérience</x-button>
                    @elseif (! in_array($filter, ['suivis', 'decouvrir'], true))
                        <p class="text-[14px] leading-relaxed text-muted">Vous pouvez partager un besoin, un savoir ou une expérience, à votre rythme.</p>
                        <x-button :href="route('agir')" variant="accent"><x-icon name="plus" :size="18" class="mr-2" />Agir</x-button>
                    @endif
                </section>
            @endforelse
        </div>

        @if ($hasMore)
            <x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled" wire:target="more">Voir plus de publications</x-button>
        @endif
    </div>

    <aside aria-label="Explorer et agir" class="feed-explore">
        <section class="feed-panel p-4">
            <h2 class="mb-2 text-[17px] font-extrabold">Explorer GAMAD</h2>
            <nav aria-label="Explorer GAMAD" class="flex flex-col gap-1">
                @foreach ($directories as [$label, $description, $url, $icon, $colors])
                    <a href="{{ $url }}" class="group flex min-h-16 items-center gap-3 rounded-card border border-line-soft px-3 py-2 text-ink no-underline hover:bg-ground hover:text-ink">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-button {{ $colors }}"><x-icon :name="$icon" :size="24" /></span>
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-extrabold">{{ $label }}</span><span class="mt-0.5 block text-[12px] leading-snug text-muted">{{ $description }}</span></span>
                        <x-icon name="next" :size="16" />
                    </a>
                @endforeach
            </nav>
        </section>

        @if ($suggestions->isNotEmpty())
            <section aria-labelledby="suggestions-fil" class="feed-panel p-4">
                <div class="flex items-start justify-between gap-2">
                    <h2 id="suggestions-fil" class="text-[17px] font-extrabold">Suggestions pour vous</h2>
                    <a href="{{ route('personnes.index', ['liste' => 'suggestions']) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Voir plus</a>
                </div>
                <ul class="mt-2 space-y-1">
                    @foreach ($suggestions as $person)
                        @php($isFollowed = in_array($person->user_id, $suggestionFollowedIds, true))
                        <li class="flex min-w-0 items-center gap-3" wire:key="suggestion-{{ $person->id }}">
                            <x-avatar :profile="$person" :size="48" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('personnes.show', $person->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold text-ink no-underline hover:underline">{{ $person->user->name }}</a>
                                <p class="-mt-2 break-words text-[12px] text-muted">{{ $person->reason }}@if ($person->city) · {{ $person->city->label() }}@endif</p>
                            </div>
                            <button type="button" wire:click="followSuggestion('{{ $person->id }}')" class="min-h-11 shrink-0 rounded-button border border-ink px-3 text-[13px] font-bold">{{ $isFollowed ? 'Suivie' : 'Suivre' }}</button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="flex flex-col gap-3 rounded-card bg-ink p-5 text-white">
            <x-icon name="heart" :size="28" class="text-accent" />
            <h2 class="text-[18px] font-extrabold text-white">Quand vous le souhaitez</h2>
            <p class="text-[14px] leading-relaxed text-white/85">Partager une expérience, une réalisation ou un besoin est toujours un choix. Vous pouvez aussi simplement découvrir, lire et revenir quand vous voulez.</p>
            <button type="button" x-data x-on:click="$dispatch('open-composer')" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-button bg-accent px-5 text-[15px] font-bold text-ink">Partager <x-icon name="arrow-right" :size="16" /></button>
        </section>
    </aside>

    <div class="feed-dialog"><livewire:gesture-sheet /></div>
</div>
