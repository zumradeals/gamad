@php
    $filters = ['tout' => 'Tout', 'suivis' => 'Mes suivis', 'besoins' => 'Besoins', 'savoirs' => 'Savoirs', 'apprendre' => 'Apprendre', 'aides' => 'Aides', 'zumra' => 'ZUMRA', 'projets' => 'Projets'];
    if (\App\Support\Experience::initial()) {
        unset($filters['zumra'], $filters['projets']);
    }
    $shortcuts = [
        [\App\Enums\PublicationType::Need, 'idea', 'text-type-need-fg'],
        [\App\Enums\PublicationType::Knowledge, 'book', 'text-type-knowledge-fg'],
        [\App\Enums\PublicationType::LearningWish, 'learn', 'text-type-learning-fg'],
    ];
    $directories = [
        ['Personnes', 'Trouver des capacités', 'personnes.index', 'group', 'bg-type-call-bg text-type-call-fg'],
        ['Besoins', 'Voir ce qui manque', 'besoins.index', 'heart', 'bg-type-need-bg text-type-need-fg'],
        ['ZUMRA', 'Découvrir les communautés', 'zumras.index', 'community', 'bg-type-learning-bg text-type-learning-fg'],
        ['Projets', 'Voir les actions en cours', 'projets.index', 'project', 'bg-type-knowledge-bg text-type-knowledge-fg'],
    ];
    if (\App\Support\Experience::initial()) {
        $directories = [
            ['Trouver une personne', 'Rechercher par capacité ou par nom', 'personnes.index', 'group', 'bg-type-call-bg text-type-call-fg'],
            ['Besoins à découvrir', 'Voir où vous pouvez aider', 'fil', 'heart', 'bg-type-need-bg text-type-need-fg'],
        ];
    }
    $profileShortcuts = [['Mon profil', route('espace.profil'), 'user'], ['À faire maintenant', route('espace').'#afaire', 'tasks']];
    if (! \App\Support\Experience::initial()) {
        array_splice($profileShortcuts, 1, 0, [['Mes ZUMRA', route('zumras.index').'#mes-zumra', 'community']]);
    }
@endphp

<div class="feed-layout">
    <h1 class="sr-only">Accueil</h1>

    <aside aria-label="Mon profil" class="feed-profile">
        <section class="feed-panel overflow-hidden">
            <div class="feed-cover" aria-hidden="true"></div>
            <div class="px-4 pb-5">
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="relative -mt-8 mb-3 inline-flex rounded-full border-4 border-card" aria-label="Voir mon profil">
                    <x-avatar :profile="$profile" :size="76" />
                </a>
                <h2 class="break-words text-[19px] font-extrabold leading-tight">{{ $viewer->name }}</h2>
                @if ($profile->city)
                    <p class="mt-1 text-[13px] text-muted">{{ $profile->city->label() }}</p>
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
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-ink underline underline-offset-4 hover:text-link">Voir mon profil <x-icon name="arrow-right" :size="16" /></a>
            </div>
        </section>

        <nav aria-label="Mes raccourcis" class="feed-panel divide-y divide-line-soft px-4">
            @foreach ($profileShortcuts as [$label, $url, $icon])
                <a href="{{ $url }}" class="feed-shortcut"><x-icon :name="$icon" :size="21" /><span class="min-w-0 flex-1">{{ $label }}</span><x-icon name="next" :size="15" /></a>
            @endforeach
        </nav>
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
        <section aria-label="Publier" class="feed-panel p-4">
            <div class="flex items-center gap-3">
                <x-avatar :profile="$profile" :size="44" />
                <a href="{{ route('agir') }}" class="flex min-h-12 min-w-0 flex-1 items-center rounded-button border border-chip-line bg-card px-3.5 text-[14px] text-muted no-underline transition hover:border-ink hover:bg-ground hover:text-ink">Que voulez-vous faire avancer ?</a>
            </div>
            <div class="mt-3 grid grid-cols-3 divide-x divide-line-soft border-t border-line-soft pt-2">
                @foreach ($shortcuts as [$shortcut, $icon, $color])
                    <a href="{{ route('agir.publier', $shortcut->slug()) }}" class="feed-compose-action {{ $color }}">
                        <x-icon :name="$icon" :size="22" /><span>{{ $shortcut->label() }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <livewire:match-suggestions />

        @if (\App\Support\Experience::initial() && $searchText !== '')
            <div class="flex flex-wrap items-center justify-between gap-2 px-1 text-[13px]">
                <p class="min-w-0 text-muted [overflow-wrap:anywhere]" role="status">Recherche dans les publications : « {{ $searchText }} »</p>
                <button type="button" wire:click="clearSearch" class="min-h-11 shrink-0 font-semibold underline">Effacer la recherche</button>
            </div>
        @endif

        <nav aria-label="Filtrer les publications" class="feed-panel flex overflow-x-auto px-2">
            @foreach ($filters as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" @class(['feed-filter', 'is-active' => $filter === $key])>{{ $label }}</button>
            @endforeach
        </nav>

        <div class="flex items-center gap-3 px-0.5 text-[12px] text-muted">
            <span>Les publications les plus récentes</span><span class="h-px flex-1 bg-line" aria-hidden="true"></span>
        </div>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif
        <div wire:loading.delay wire:target="setFilter, more" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

        <div class="feed-posts" wire:loading.class="opacity-60" wire:target="setFilter, more">
            @forelse ($posts as $post)
                <x-publication-card compact wire:key="publication-{{ $post->id }}" :publication="$post" :viewer="$viewer" :gesture="$gestures[$post->id] ?? null" :supported="$supported" :managed="$managedZumraIds" :member-of="$memberOf" />
            @empty
                <section aria-labelledby="vide" class="feed-panel flex flex-col items-start gap-3 p-5 md:p-6">
                    <span class="flex size-11 items-center justify-center rounded-button bg-type-call-bg text-type-call-fg" aria-hidden="true"><x-icon name="idea" :size="24" /></span>
                    @if ($searchText !== '')
                        <h2 id="vide" class="text-[18px] font-extrabold">Aucune publication ne correspond à cette recherche.</h2>
                        <p class="text-[14px] text-muted">Essayez un autre mot, ou cherchez une personne par ses capacités.</p>
                        <x-button type="button" variant="secondary" wire:click="clearSearch">Effacer la recherche</x-button>
                    @elseif ($filter === 'tout')
                        <p><strong id="vide" class="text-[18px] font-extrabold">Les publications apparaîtront ici.</strong> <span class="text-muted"><a href="{{ route('espace.profil') }}" class="font-semibold">Commencez par compléter votre profil.</a></span></p>
                    @else
                        <h2 id="vide" class="text-[18px] font-extrabold">Rien dans cette catégorie pour l’instant.</h2>
                    @endif
                    <p class="text-[14px] leading-relaxed text-muted">Soyez la première personne à publier : un besoin, un savoir à partager, ce que vous voulez apprendre.</p>
                    <x-button :href="route('agir')" variant="accent"><x-icon name="plus" :size="18" class="mr-2" />Agir</x-button>
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
            <nav aria-label="Explorer GAMAD" class="flex flex-col">
                @foreach ($directories as [$label, $description, $route, $icon, $colors])
                    <a href="{{ route($route, $route === 'fil' ? ['filtre' => 'besoins'] : []) }}" class="group flex min-h-20 items-center gap-3 rounded-button py-3 text-ink no-underline hover:text-ink">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-button {{ $colors }}"><x-icon :name="$icon" :size="24" /></span>
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-extrabold group-hover:underline">{{ $label }}</span><span class="mt-0.5 block text-[12px] leading-relaxed text-muted">{{ $description }}</span></span>
                        <x-icon name="next" :size="16" />
                    </a>
                @endforeach
            </nav>
        </section>
        <section class="feed-panel flex flex-col gap-4 p-5">
            <span class="h-1 w-10 rounded-full bg-ink" aria-hidden="true"></span>
            <h2 class="text-[17px] font-extrabold">Votre prochaine action</h2>
            <p class="text-[14px] leading-relaxed text-muted">Un savoir à transmettre ? Un besoin à exprimer ? Faites le premier pas.</p>
            <x-button :href="route('agir')" variant="accent">Agir maintenant</x-button>
        </section>
    </aside>

    <div class="feed-dialog"><livewire:gesture-sheet /></div>
</div>
