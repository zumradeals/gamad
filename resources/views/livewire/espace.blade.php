@php
    $nav = [
        '' => [
            ['vue-ensemble', 'Vue d’ensemble', 'grid'],
            ['profil', 'Mon profil', 'user'],
            ['capacites', 'Mes capacités', 'idea'],
            ['realisations', 'Mes réalisations', 'project'],
            ['publications', 'Mes publications', 'document'],
            ['relations', 'Mes relations', 'community'],
            ['compte', 'Paramètres', 'settings'],
        ],
        'Gestion' => array_values(array_filter([
            ['echanges', 'Mes échanges', 'chat'],
            ['enregistrements', 'Mes enregistrements', 'book'],
            ['attestations', 'Mes attestations', 'shield'],
            ['entreprise', 'Mon entreprise', 'home'],
            \App\Support\ZumraModule::accessibleTo(auth()->user()) ? ['zumras', 'Mes ZUMRA', 'community'] : null,
            \App\Support\ZumraModule::accessibleTo(auth()->user()) ? ['projets', 'Mes projets', 'project'] : null,
            \App\Support\ContributionsModule::accessibleTo(auth()->user()) ? ['contributions', 'Mes contributions', 'heart'] : null,
            ['pistes', 'Mes pistes', 'compass'],
            ($hasLegacy || $walletsEnabled) ? ['activites', 'Activités antérieures', 'tasks'] : null,
        ])),
    ];
    if (auth()->user()?->can('moderate') || auth()->user()?->can('super-admin')) {
        $nav['Plateforme'] = array_values(array_filter([
            auth()->user()->can('moderate') ? ['moderation', 'Modération', 'shield'] : null,
            auth()->user()->can('super-admin') ? ['administration', 'Administration', 'settings'] : null,
        ]));
    }
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('M Y');
    $place = collect([$profile->city?->label(), $profile->country?->name])->filter()->unique()->implode(', ');
    $title = fn ($post) => \Illuminate\Support\Str::limit(trim(preg_split('/\R/', trim($post->body))[0] ?? ''), 80);
    $summary = function ($post) {
        $rest = trim(implode(' ', array_slice(preg_split('/\R/', trim($post->body)), 1)));

        return \Illuminate\Support\Str::limit($post->outcome ?: $rest, 140);
    };
    $card = 'rounded-card border border-line bg-card';
@endphp

<div id="vue-ensemble" class="space-dashboard">
    <h1 class="sr-only">Mon espace</h1>

    {{-- Colonne gauche : navigation personnelle, visibilité, complétude, confidentialité --}}
    <aside aria-label="Mon espace personnel" class="min-w-0 max-xl:contents">
        <div class="{{ $card }} p-4 max-xl:order-1 xl:rounded-b-none xl:border-b-0 xl:pb-0">
            <p class="hidden text-[22px] font-extrabold leading-tight xl:block">Mon espace</p>

            <nav aria-label="Rubriques de Mon espace" class="space-menu -mx-1 flex gap-1 overflow-x-auto px-1 py-1 xl:mx-0 xl:mt-3 xl:flex-col xl:overflow-visible xl:px-0">
                @foreach ($nav as $group => $items)
                    @if ($group !== '')
                        <p class="hidden px-2.5 pb-1 pt-3 text-[11px] font-extrabold uppercase tracking-wide text-muted xl:block">{{ $group }}</p>
                    @endif
                    @foreach ($items as [$id, $label, $icon])
 @if ($id === 'contributions')
                            <a href="{{ route('espace.mes-contributions') }}" class="space-menu-link shrink-0 whitespace-nowrap !text-[13px] xl:whitespace-normal"><x-icon :name="$icon" :size="20" /><span>{{ $label }}</span></a>
                        @elseif ($id === 'projets')
                            <a href="{{ route('espace.projets') }}" class="space-menu-link shrink-0 whitespace-nowrap !text-[13px] xl:whitespace-normal"><x-icon :name="$icon" :size="20" /><span>{{ $label }}</span></a>
                        @elseif ($id === 'zumras')
                            <a href="{{ route('espace.zumras') }}" class="space-menu-link shrink-0 whitespace-nowrap !text-[13px] xl:whitespace-normal"><x-icon :name="$icon" :size="20" /><span>{{ $label }}</span></a>
                        @elseif ($id === 'profil')
                            <a href="{{ route('espace.profil') }}" class="space-menu-link shrink-0 whitespace-nowrap !text-[13px] xl:whitespace-normal"><x-icon :name="$icon" :size="20" /><span>{{ $label }}</span></a>
                        @else
                            <a href="{{ $id === 'vue-ensemble' ? route('espace') : route('espace', ['rubrique' => $id]) }}" wire:click.prevent="show('{{ $id }}')" @if ($section === $id) aria-current="page" @endif class="space-menu-link shrink-0 whitespace-nowrap !text-[13px] xl:whitespace-normal"><x-icon :name="$icon" :size="20" /><span>{{ $label }}</span></a>
                        @endif
                    @endforeach
                @endforeach
            </nav>
        </div>

        <div class="{{ $card }} flex flex-col gap-4 p-4 max-xl:order-4 xl:rounded-t-none xl:border-t-0 xl:pt-0">
            <div class="flex flex-col gap-4 xl:border-t xl:border-line-soft xl:pt-4">
                <fieldset>
                    <legend class="text-[15px] font-extrabold">Visibilité du profil</legend>
                    @foreach ([\App\Models\Profile::PUBLIC => 'Public', \App\Models\Profile::MEMBERS => 'Inscrits seulement'] as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[14px]">
                            <input type="radio" name="visibility" value="{{ $value }}" class="size-5 accent-[#0e2a3f]" @checked($profile->visibility === $value) wire:click="setVisibility('{{ $value }}')">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">{{ $profile->isPublic() ? 'Votre profil est visible par tous, avec ou sans compte.' : 'Votre profil n’est visible que des personnes inscrites.' }}</p>
                </fieldset>

                <div>
                    <p class="text-[15px] font-extrabold">Complétude du profil</p>
                    <div class="mt-2 flex items-center gap-3">
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-line-soft" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completeness['percent'] }}" aria-label="Complétude du profil"><div class="h-full rounded-full bg-accent" style="width: {{ $completeness['percent'] }}%"></div></div>
                        <span class="text-[14px] font-extrabold" data-testid="completude">{{ $completeness['percent'] }} %</span>
                    </div>
                    <ul class="mt-2 flex flex-col">
                        @foreach ($completeness['items'] as $item)
                            <li><a href="{{ $item['url'] }}" class="flex min-h-11 items-center gap-3 text-[14px] text-ink no-underline hover:text-ink"><span @class(['flex size-6 shrink-0 items-center justify-center rounded-full', 'bg-type-knowledge-fg text-white' => $item['done'], 'border border-chip-line' => ! $item['done']])>@if ($item['done'])<x-icon name="check" :size="14" :stroke="3" />@endif</span><span>{{ $item['label'] }}</span><span class="sr-only">{{ $item['done'] ? ' : renseigné' : ' : à renseigner' }}</span></a></li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-button bg-soft-blue p-3">
                    <p class="flex items-center gap-2 text-[13px] font-extrabold"><x-icon name="lock" :size="17" />Votre espace est privé.</p>
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">Cet espace n’est visible que de vous : vos échanges, vos enregistrements et vos réglages restent privés. Seules les informations de votre profil public sont visibles selon votre visibilité.</p>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="border-t border-line-soft pt-3">
                    @csrf
                    <button type="submit" class="space-menu-link w-full"><x-icon name="logout" :size="20" />Se déconnecter</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Colonne centrale : tableau de bord personnel --}}
    <div class="flex min-w-0 flex-col gap-4 max-xl:order-2">
        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        @if ($section === 'vue-ensemble')
        <section aria-labelledby="identite" class="{{ $card }} p-4 md:p-5">
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start">
                <x-avatar :profile="$profile" :size="104" class="self-start" />
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <h2 id="identite" class="flex flex-wrap items-center gap-2 break-words text-[24px] font-extrabold leading-tight">{{ $user->name }}@if ($identityVerified)<span class="text-link" title="Identité vérifiée par GAMAD"><x-icon name="check-circle" :size="24" /><span class="sr-only">Identité vérifiée par GAMAD</span></span>@endif</h2>
                    @if (filled($profile->headline))<p class="break-words text-[15px] text-muted">{{ $profile->headline }}</p>@endif
                    @if ($place !== '' || $available)
                        <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[14px] text-muted">
                            @if ($place !== '')<span class="inline-flex items-center gap-1.5"><x-icon name="pin" :size="16" />{{ $place }}</span>@endif
                            @if ($available)<span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-type-knowledge-fg"></span>Disponible</span>@endif
                        </p>
                    @endif
                    @if (filled($profile->bio))<p class="mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $profile->bio }}</p>@endif
                    @if ($profile->offeredSkills->isNotEmpty())
                        <ul aria-label="Capacités principales" class="mt-1 flex flex-wrap gap-1.5">
                            <li class="rounded-chip bg-type-knowledge-bg px-3 py-1 text-[12px] font-bold text-type-knowledge-fg">Peut apporter</li>
                            @foreach ($profile->offeredSkills->take(3) as $skill)
                                <li class="max-w-full break-words rounded-chip bg-type-learning-bg px-3 py-1 text-[12px] font-semibold text-type-learning-fg">{{ $skill->name }}</li>
                            @endforeach
                            @if ($profile->offeredSkills->count() > 3)<li class="rounded-chip bg-ground px-3 py-1 text-[12px] font-semibold">+{{ $profile->offeredSkills->count() - 3 }}</li>@endif
                        </ul>
                    @endif
                </div>
                <div class="flex shrink-0 flex-col gap-2 sm:w-52">
                    <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary" class="!px-3.5 !text-[14px]">Voir mon profil public</x-button>
                    <x-button :href="route('espace.profil')" variant="accent" class="!px-3.5 !text-[14px]"><x-icon name="edit" :size="18" class="mr-2" />Modifier mon profil</x-button>
                </div>
            </div>
        </section>

        <section aria-label="Mes chiffres" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['Capacités', $counts['skills'], 'idea', 'bg-type-learning-bg text-type-learning-fg', 'capacites', 'Voir mes capacités'],
                ['Réalisations', $counts['achievements'], 'project', 'bg-type-call-bg text-type-call-fg', 'realisations', 'Voir mes réalisations'],
                ['Publications', $counts['posts'], 'document', 'bg-type-project-bg text-type-project-fg', 'publications', 'Voir mes publications'],
                ['Relations', $counts['relations'], 'community', 'bg-type-learning-bg text-type-learning-fg', 'relations', 'Voir mes relations'],
            ] as [$label, $count, $icon, $tone, $href, $cta])
                <div class="{{ $card }} flex min-w-0 flex-col gap-1 p-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $tone }}"><x-icon :name="$icon" :size="20" /></span>
                        <div class="min-w-0"><p class="text-[24px] font-extrabold leading-none" data-testid="compteur-{{ \Illuminate\Support\Str::slug($label) }}">{{ $count }}</p><p class="text-[12px] text-muted">{{ $label }}</p></div>
                    </div>
                    <a href="{{ route('espace', ['rubrique' => $href]) }}" wire:click.prevent="show('{{ $href }}')" class="inline-flex min-h-11 items-center gap-1 text-[12px] font-bold">{{ $cta }}<x-icon name="arrow-right" :size="14" /></a>
                </div>
            @endforeach
        </section>

        @if (count($todo) > 0)
        <div wire:poll.30s>
            <section aria-labelledby="afaire" @class(['space-panel flex flex-col gap-4 border-l-[3px] p-4 md:p-5', 'border-l-accent' => true])>
            <div class="flex items-center gap-2.5">
                <x-icon name="tasks" :size="23" />
                <h2 id="afaire" class="text-[18px] font-extrabold">À faire maintenant</h2>
                @if (count($todo) > 0)
                    <span class="ml-auto min-w-6 rounded-full bg-accent px-2 text-center text-[13px] font-extrabold leading-6 text-ink" aria-label="{{ count($todo) }} à faire">{{ count($todo) }}</span>
                @endif
            </div>
            @foreach ($todo as $line)
                <div wire:key="{{ $line['key'] }}" class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3">
                    <div class="flex min-w-0 flex-1 basis-44 flex-col">
                        <span class="break-words text-[15px] font-extrabold">{{ $line['title'] }}</span>
                        <span class="break-words text-[13px] text-muted">{{ $line['detail'] }}</span>
                    </div>
                    <x-button :href="$line['url']" variant="secondary" class="shrink-0 !px-4 !text-[13px]">{{ $line['action'] }}</x-button>
                </div>
            @endforeach
            <p class="border-t border-line-soft pt-3 text-[12px] leading-relaxed text-muted">Cette liste ne montre que ce qui attend une action de votre part.</p>
        </section>


        </div>
        @else
        <div wire:poll.30s></div>
        @endif
        <section id="capacites" aria-labelledby="capacites-titre" class="{{ $card }} flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 id="capacites-titre" class="text-[18px] font-extrabold">Mes capacités</h2>
                <a href="{{ route('espace.profil') }}#offered-titre" class="inline-flex min-h-11 items-center text-[13px] font-bold">Modifier</a>
            </div>
            <div>
                <h3 class="text-[13px] font-semibold text-muted">Ce que je peux apporter</h3>
                @if ($profile->offeredSkills->isNotEmpty())
                    <ul class="mt-2 flex flex-wrap gap-2" aria-label="Ce que je peux apporter">
                        @foreach ($profile->offeredSkills as $skill)
                            <li class="max-w-full break-words rounded-chip bg-type-learning-bg px-3.5 py-1.5 text-[13px] font-semibold text-type-learning-fg">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1 text-[14px] text-muted">Ajoutez vos savoir-faire pour que d’autres vous trouvent.</p>
                @endif
            </div>
            <div>
                <h3 class="text-[13px] font-semibold text-muted">Ce que je souhaite apprendre</h3>
                <ul class="mt-2 flex flex-wrap gap-2" aria-label="Ce que je souhaite apprendre">
                    @foreach ($profile->wantedSkills as $skill)
                        <li class="max-w-full break-words rounded-chip bg-type-knowledge-bg px-3.5 py-1.5 text-[13px] font-semibold text-type-knowledge-fg">{{ $skill->name }}</li>
                    @endforeach
                    <li><a href="{{ route('espace.profil') }}#{{ $profile->wantedSkills->isEmpty() ? 'wanted-titre' : 'offered-titre' }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-chip border border-dashed border-chip-line px-3.5 text-[13px] font-semibold text-ink no-underline hover:text-ink"><x-icon name="plus" :size="15" />Ajouter une capacité</a></li>
                </ul>
            </div>
        </section>

        <section id="realisations" aria-labelledby="realisations-titre" class="{{ $card }} flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 id="realisations-titre" class="text-[18px] font-extrabold">{{ $section === 'vue-ensemble' ? 'Mes réalisations récentes' : 'Mes réalisations' }}</h2>
                @if ($counts['achievements'] > 3 && $section === 'vue-ensemble')<a href="{{ route('espace', ['rubrique' => 'realisations']) }}" wire:click.prevent="show('realisations')" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold">Voir tout<x-icon name="arrow-right" :size="15" /></a>@elseif ($counts['achievements'] > 0)<a href="{{ route('personnes.show', $profile->public_id) }}#realisations" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold">Sur mon profil public<x-icon name="arrow-right" :size="15" /></a>@endif
            </div>
            @forelse ($achievements as $post)
                <article wire:key="realisation-{{ $post->id }}" class="flex min-w-0 gap-3 border-t border-line-soft pt-3 first:border-t-0 first:pt-0">
                    @if ($post->media_kind === 'image' && $post->media_path)
                        <img src="{{ route('publications.media', $post) }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-button object-cover sm:size-20">
                    @else
                        <span class="flex size-16 shrink-0 items-center justify-center rounded-button bg-type-learning-bg text-type-learning-fg sm:size-20"><x-icon name="project" :size="30" /></span>
                    @endif
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a href="{{ route('publications.show', $post) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:underline">{{ $title($post) }}</a>
                        @if ($summary($post) !== '')<p class="break-words text-[13px] leading-relaxed text-muted">{{ $summary($post) }}</p>@endif
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="text-[12px] text-muted">{{ $date($post->created_at) }}</span>
                            @foreach ($post->skills->take(3) as $skill)<span class="rounded-chip bg-type-learning-bg px-2.5 py-0.5 text-[12px] font-semibold text-type-learning-fg">{{ $skill->name }}</span>@endforeach
                            @if ($post->isHidden())<span class="text-[12px] font-extrabold text-danger">Masquée</span>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="flex flex-col items-start gap-3 py-1">
                    <p class="text-[14px] text-muted">Montrez ce que vous avez fait, même un premier travail ou un apprentissage.</p>
                    <x-button :href="route('agir.publier', 'ma-realisation')" variant="secondary" class="!px-4 !text-[13px]"><x-icon name="plus" :size="16" class="mr-2" />Ajouter une réalisation</x-button>
                </div>
            @endforelse
        </section>

        <section id="publications" aria-labelledby="publications-titre" class="{{ $card }} flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3"><h2 id="publications-titre" class="text-[18px] font-extrabold">{{ $section === 'vue-ensemble' ? 'Mes publications récentes' : 'Mes publications' }}</h2>@if ($counts['posts'] > 5 && $section === 'vue-ensemble')<a href="{{ route('espace', ['rubrique' => 'publications']) }}" wire:click.prevent="show('publications')" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold">Voir tout<x-icon name="arrow-right" :size="15" /></a>@endif</div>
            @forelse ($publications as $publication)
                <article wire:key="espace-publication-{{ $publication->id }}" class="flex min-w-0 gap-3 border-t border-line-soft pt-3 first:border-t-0 first:pt-0">
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-button bg-type-project-bg text-type-project-fg sm:size-20"><x-icon name="document" :size="30" /></span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a href="{{ route('publications.show', $publication) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:underline">{{ $title($publication) }}</a>
                        @if ($summary($publication) !== '')<p class="break-words text-[13px] leading-relaxed text-muted">{{ $summary($publication) }}</p>@endif
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="rounded-chip px-2.5 py-0.5 text-[12px] font-bold {{ $publication->type->badgeClasses() }}">{{ $publication->displayLabel() }}</span>
                            <span class="text-[12px] text-muted">{{ $date($publication->created_at) }}</span>
                            @if ($publication->isHidden())<span class="text-[12px] font-extrabold text-danger">Masquée</span>@endif
                            @if ($publication->need_state && $publication->need_state !== \App\Enums\NeedState::Open)<span class="text-[12px] font-extrabold text-muted">{{ $publication->need_state->label() }}</span>@endif
                            @if ($publication->live_gestures_count > 0)<span class="text-[12px] font-semibold text-muted">{{ $publication->type->factLabel($publication->live_gestures_count) }}</span>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="flex flex-col items-start gap-3 py-1">
                    <h3 class="text-[16px] font-extrabold">Votre première action commence ici</h3>
                    <p class="-mt-2 text-[14px] text-muted">Un besoin, un savoir ou une aide à proposer : partagez-le dans les publications.</p>
                    <x-button :href="route('agir')" variant="accent" class="!px-5 !text-[13px]"><x-icon name="plus" :size="18" class="mr-2" />Agir</x-button>
                </div>
            @endforelse
        </section>

        @else
        <a href="{{ route('espace') }}" wire:click.prevent="show('vue-ensemble')" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-bold"><x-icon name="back" :size="16" />Vue d’ensemble</a>
        @if ($section === 'capacites')
        <section id="capacites" aria-labelledby="capacites-titre" class="{{ $card }} flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 id="capacites-titre" class="text-[18px] font-extrabold">Mes capacités</h2>
                <a href="{{ route('espace.profil') }}#offered-titre" class="inline-flex min-h-11 items-center text-[13px] font-bold">Modifier</a>
            </div>
            <div>
                <h3 class="text-[13px] font-semibold text-muted">Ce que je peux apporter</h3>
                @if ($profile->offeredSkills->isNotEmpty())
                    <ul class="mt-2 flex flex-wrap gap-2" aria-label="Ce que je peux apporter">
                        @foreach ($profile->offeredSkills as $skill)
                            <li class="max-w-full break-words rounded-chip bg-type-learning-bg px-3.5 py-1.5 text-[13px] font-semibold text-type-learning-fg">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1 text-[14px] text-muted">Ajoutez vos savoir-faire pour que d’autres vous trouvent.</p>
                @endif
            </div>
            <div>
                <h3 class="text-[13px] font-semibold text-muted">Ce que je souhaite apprendre</h3>
                <ul class="mt-2 flex flex-wrap gap-2" aria-label="Ce que je souhaite apprendre">
                    @foreach ($profile->wantedSkills as $skill)
                        <li class="max-w-full break-words rounded-chip bg-type-knowledge-bg px-3.5 py-1.5 text-[13px] font-semibold text-type-knowledge-fg">{{ $skill->name }}</li>
                    @endforeach
                    <li><a href="{{ route('espace.profil') }}#{{ $profile->wantedSkills->isEmpty() ? 'wanted-titre' : 'offered-titre' }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-chip border border-dashed border-chip-line px-3.5 text-[13px] font-semibold text-ink no-underline hover:text-ink"><x-icon name="plus" :size="15" />Ajouter une capacité</a></li>
                </ul>
            </div>
        </section>

        @elseif ($section === 'realisations')
        <section id="realisations" aria-labelledby="realisations-titre" class="{{ $card }} flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 id="realisations-titre" class="text-[18px] font-extrabold">{{ $section === 'vue-ensemble' ? 'Mes réalisations récentes' : 'Mes réalisations' }}</h2>
                @if ($counts['achievements'] > 3 && $section === 'vue-ensemble')<a href="{{ route('espace', ['rubrique' => 'realisations']) }}" wire:click.prevent="show('realisations')" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold">Voir tout<x-icon name="arrow-right" :size="15" /></a>@elseif ($counts['achievements'] > 0)<a href="{{ route('personnes.show', $profile->public_id) }}#realisations" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold">Sur mon profil public<x-icon name="arrow-right" :size="15" /></a>@endif
            </div>
            @forelse ($achievements as $post)
                <article wire:key="realisation-{{ $post->id }}" class="flex min-w-0 gap-3 border-t border-line-soft pt-3 first:border-t-0 first:pt-0">
                    @if ($post->media_kind === 'image' && $post->media_path)
                        <img src="{{ route('publications.media', $post) }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-button object-cover sm:size-20">
                    @else
                        <span class="flex size-16 shrink-0 items-center justify-center rounded-button bg-type-learning-bg text-type-learning-fg sm:size-20"><x-icon name="project" :size="30" /></span>
                    @endif
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a href="{{ route('publications.show', $post) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:underline">{{ $title($post) }}</a>
                        @if ($summary($post) !== '')<p class="break-words text-[13px] leading-relaxed text-muted">{{ $summary($post) }}</p>@endif
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="text-[12px] text-muted">{{ $date($post->created_at) }}</span>
                            @foreach ($post->skills->take(3) as $skill)<span class="rounded-chip bg-type-learning-bg px-2.5 py-0.5 text-[12px] font-semibold text-type-learning-fg">{{ $skill->name }}</span>@endforeach
                            @if ($post->isHidden())<span class="text-[12px] font-extrabold text-danger">Masquée</span>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="flex flex-col items-start gap-3 py-1">
                    <p class="text-[14px] text-muted">Montrez ce que vous avez fait, même un premier travail ou un apprentissage.</p>
                    <x-button :href="route('agir.publier', 'ma-realisation')" variant="secondary" class="!px-4 !text-[13px]"><x-icon name="plus" :size="16" class="mr-2" />Ajouter une réalisation</x-button>
                </div>
            @endforelse
        </section>

        @elseif ($section === 'publications')
        <section id="publications" aria-labelledby="publications-titre" class="{{ $card }} flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3"><h2 id="publications-titre" class="text-[18px] font-extrabold">{{ $section === 'vue-ensemble' ? 'Mes publications récentes' : 'Mes publications' }}</h2>@if ($counts['posts'] > 5 && $section === 'vue-ensemble')<a href="{{ route('espace', ['rubrique' => 'publications']) }}" wire:click.prevent="show('publications')" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold">Voir tout<x-icon name="arrow-right" :size="15" /></a>@endif</div>
            @forelse ($publications as $publication)
                <article wire:key="espace-publication-{{ $publication->id }}" class="flex min-w-0 gap-3 border-t border-line-soft pt-3 first:border-t-0 first:pt-0">
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-button bg-type-project-bg text-type-project-fg sm:size-20"><x-icon name="document" :size="30" /></span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a href="{{ route('publications.show', $publication) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:underline">{{ $title($publication) }}</a>
                        @if ($summary($publication) !== '')<p class="break-words text-[13px] leading-relaxed text-muted">{{ $summary($publication) }}</p>@endif
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="rounded-chip px-2.5 py-0.5 text-[12px] font-bold {{ $publication->type->badgeClasses() }}">{{ $publication->displayLabel() }}</span>
                            <span class="text-[12px] text-muted">{{ $date($publication->created_at) }}</span>
                            @if ($publication->isHidden())<span class="text-[12px] font-extrabold text-danger">Masquée</span>@endif
                            @if ($publication->need_state && $publication->need_state !== \App\Enums\NeedState::Open)<span class="text-[12px] font-extrabold text-muted">{{ $publication->need_state->label() }}</span>@endif
                            @if ($publication->live_gestures_count > 0)<span class="text-[12px] font-semibold text-muted">{{ $publication->type->factLabel($publication->live_gestures_count) }}</span>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="flex flex-col items-start gap-3 py-1">
                    <h3 class="text-[16px] font-extrabold">Votre première action commence ici</h3>
                    <p class="-mt-2 text-[14px] text-muted">Un besoin, un savoir ou une aide à proposer : partagez-le dans les publications.</p>
                    <x-button :href="route('agir')" variant="accent" class="!px-5 !text-[13px]"><x-icon name="plus" :size="18" class="mr-2" />Agir</x-button>
                </div>
            @endforelse
        </section>

        @elseif ($section === 'relations')
        <div id="relations"><livewire:my-network /></div>
        @elseif ($section === 'echanges')
        <section aria-labelledby="echanges" class="space-panel flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <h2 id="echanges" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="chat" :size="23" />Mes échanges</h2>
            <p class="text-[14px] text-muted">Vos échanges privés et vos conversations avec les personnes en relation avec vous.</p>
            @forelse ($conversations as $conversation)
                <a wire:key="espace-conversation-{{ $conversation->id }}" href="{{ route('conversations.show', $conversation) }}" class="flex min-h-11 items-center justify-between gap-3 border-t border-line-soft py-2 text-[14px] font-semibold"><span class="min-w-0 [overflow-wrap:anywhere]">{{ $conversation->otherPerson($user)?->name ?? 'Personne supprimée' }}</span><x-icon name="arrow-right" :size="18" class="shrink-0" /></a>
            @empty
                <p class="text-[14px] text-muted">Vos premiers échanges commenceront par un geste dans les publications.</p>
            @endforelse
            {{ $conversations->links() }}
        </section>

        @elseif ($section === 'enregistrements')
        <section class="space-panel flex flex-col gap-2 p-4 md:p-5">
            <h2 class="flex items-center gap-2 text-[18px] font-extrabold"><x-icon name="book" :size="22" />Mes enregistrements</h2>
            <p class="text-[13px] text-muted">Retrouvez les publications que vous avez conservées pour les relire.</p>
            <x-button :href="route('espace.enregistrements')" variant="secondary" class="self-start">Ouvrir mes enregistrements</x-button>
            <p class="mt-2 text-[13px] text-muted">Vos réponses et les opportunités que vous avez sauvegardées se trouvent à part.</p>
            <x-button :href="route('espace.opportunites')" variant="secondary" class="self-start">Mes opportunités</x-button>
        </section>

        @elseif ($section === 'attestations')
        <section class="space-panel p-5"><h2 class="text-[18px] font-extrabold">Mes attestations</h2><p class="mt-2 text-[14px] text-muted">Faire vérifier mon identité, répondre aux confirmations d’expérience et suivre mes recours.</p><a href="{{ route('espace.attestations') }}" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Ouvrir mes attestations →</a></section>
        @elseif ($section === 'entreprise')
        <section class="space-panel flex flex-wrap items-center justify-between gap-3 border-l-[3px] border-l-type-knowledge-fg p-4 md:p-5">
            <div class="min-w-0">
                <h2 class="text-[17px] font-extrabold">Mon entreprise sur GAMAD</h2>
                <p class="mt-1 text-[13px] text-muted">Créez votre page entreprise et retrouvez vos dossiers en attente de vérification administrative.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-button :href="route('entreprises.create')" variant="secondary">Mes pages entreprises</x-button>
                @if (auth()->user()?->isSuperAdmin())
                    <x-button :href="route('moderation.entreprises')" variant="secondary">Vérifier les entreprises</x-button>
                @endif
            </div>
        </section>

        @elseif ($section === 'pistes')
        <livewire:match-suggestions />
        @elseif ($section === 'activites')
        @if ($hasLegacy || $walletsEnabled)
        @if ($legacyZumras->isNotEmpty() || $legacyWallets->isNotEmpty())
            <details class="space-panel p-4 md:p-5">
                <summary class="min-h-11 cursor-pointer content-center text-[16px] font-extrabold">Mes activités antérieures</summary>
                <p class="mb-3 text-[13px] leading-relaxed text-muted">Vos activités et historiques restent accessibles. Les nouvelles créations collectives, recharges et contributions sont en pause.</p>
                <div class="flex flex-col gap-2">
                    @foreach ($legacyZumras as $legacyZumra)
                        @if (\App\Support\ZumraModule::accessibleTo(auth()->user()))
                            <a href="{{ route('zumras.show', $legacyZumra) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold [overflow-wrap:anywhere]">{{ $legacyZumra->name }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center text-[14px] font-semibold [overflow-wrap:anywhere]">{{ $legacyZumra->name }}</span>
                        @endif
                    @endforeach
                    @foreach ($legacyWallets as $legacyWallet)
                        @php $legacyOwner = $legacyWallet->isPerson() ? null : $legacyZumras->firstWhere('id', $legacyWallet->owner_id); @endphp
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $legacyWallet->isPerson() ? route('portefeuille') : route('zumras.portefeuille', $legacyWallet->owner_id) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">{{ $legacyWallet->isPerson() ? 'Mon historique de portefeuille' : 'Portefeuille : '.$legacyOwner?->name }}</a>
                            <a href="{{ $legacyWallet->isPerson() ? route('contribution') : route('zumras.contribution', $legacyWallet->owner_id) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">Historique des contributions</a>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($walletsEnabled)
            <section aria-labelledby="portefeuille" class="space-panel flex flex-col gap-3 p-4 md:p-5">
                <h2 id="portefeuille" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="lock" :size="23" />Mon portefeuille</h2>
                <p class="text-[13px] text-muted">Vos ZAHAB et leurs mouvements. Privé : vous seul le voyez.</p>
                <div class="flex flex-col gap-2 md:flex-row">
                    <x-button :href="route('portefeuille')" variant="secondary">Ouvrir mon portefeuille</x-button>
                    <x-button :href="route('contribution')" variant="secondary">Ma contribution</x-button>
                </div>
            </section>
        @endif

        @else
        <p class="rounded-card border border-line bg-card p-5 text-[14px] text-muted">Aucune activité antérieure à afficher.</p>
        @endif
        @elseif ($section === 'compte')
        <section aria-labelledby="compte" class="space-panel flex flex-col gap-4 p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line-soft pb-3">
                <h2 id="compte" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="settings" :size="23" />Mon compte</h2>
                <span class="flex items-center gap-2 text-[11px] text-muted"><x-icon name="lock" :size="15" />Informations privées</span>
            </div>
            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                <form wire:submit="updateName" class="flex min-w-0 flex-col gap-2">
                    <label for="name" class="text-[13px] font-bold">Nom affiché</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="name" type="text" wire:model="name" autocomplete="name" required maxlength="255"
                               @class(['min-h-11 min-w-0 w-full flex-1 rounded-button border bg-card px-3.5 text-[14px]', $errors->has('name') ? 'border-danger' : 'border-chip-line'])
                               @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                        <x-button wire:loading.attr="disabled" wire:target="updateName" class="shrink-0 !px-3.5 !text-[12px]">
                            <span wire:loading.remove wire:target="updateName">Enregistrer le nom</span>
                            <span wire:loading wire:target="updateName">Enregistrement…</span>
                        </x-button>
                    </div>
                    @error('name')<p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </form>
                <div class="min-w-0 border-t border-line-soft pt-4 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
                    <p class="text-[13px] font-bold">Adresse e-mail</p>
                    <p class="mt-1 break-words text-[14px]">{{ $user->email }}</p>
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">Votre adresse e-mail n’est visible que par vous.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-line-soft pt-3 lg:hidden">
                @csrf
                <x-button variant="secondary" class="!text-[13px]"><x-icon name="logout" :size="18" class="mr-2" />Se déconnecter</x-button>
            </form>
        </section>
        <details wire:ignore.self class="space-panel group" @if ($errors->has('password')) open @endif x-data="{ confirming: {{ $errors->has('password') ? 'true' : 'false' }} }">
            <summary class="space-delete-summary">
                <x-icon name="warning" :size="24" class="shrink-0 text-danger" />
                <span class="min-w-0 flex-1"><span id="suppression" class="block text-[14px] font-bold text-danger">Suppression du compte</span><span class="mt-0.5 block text-[12px] leading-relaxed text-muted">Une action définitive, à confirmer avec votre mot de passe.</span></span>
                <x-icon name="chevron-down" :size="18" class="shrink-0 transition-transform group-open:rotate-180" />
            </summary>
            <div class="flex flex-col gap-3 border-t border-line-soft p-4 md:p-5">
                <p class="text-[14px] leading-relaxed text-muted">La suppression est définitive : votre compte et vos données personnelles sont effacés et ne peuvent pas être restaurés.</p>
                <div x-show="!confirming"><x-button type="button" variant="secondary" x-on:click="confirming = true; $nextTick(() => $refs.deletePassword.focus())" class="!border-danger !text-danger">Supprimer mon compte</x-button></div>
        <form wire:submit="deleteAccount" x-show="confirming" x-cloak class="flex flex-col gap-3">
            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-[13px] font-semibold">Confirmez avec votre mot de passe</label>
                <input id="password" x-ref="deletePassword" type="password" wire:model="password" autocomplete="current-password" required
                       @class(['min-h-11 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('password') ? 'border-danger' : 'border-chip-line'])
                       @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                @error('password')
                    <p id="password-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button wire:loading.attr="disabled" wire:target="deleteAccount" class="sm:flex-1">
                    <span wire:loading.remove wire:target="deleteAccount">Supprimer définitivement</span>
                    <span wire:loading wire:target="deleteAccount">Suppression…</span>
                </x-button>
                <x-button type="button" variant="secondary" class="sm:flex-1" x-on:click="confirming = false; $wire.set('password', '')">Annuler</x-button>
            </div>
        </form>
            </div>
        </details>
        @elseif ($section === 'moderation')
        <section aria-labelledby="moderation" class="space-panel flex flex-col gap-3 p-4 md:p-5">
            <h2 id="moderation" class="text-[18px] font-extrabold">Modération</h2>
            <p class="text-muted">Les publications signalées, celles que vous avez masquées et l’explication de leur auteur ; les avis et suspensions de personnes ; le journal des actes.</p>
            <x-button :href="route('moderation')">Ouvrir la file de modération</x-button>
            <x-button variant="secondary" :href="route('moderation.personnes')">Modération des personnes</x-button>
            <x-button variant="secondary" :href="route('moderation.journal')">Journal d’administration</x-button>
        </section>

        @elseif ($section === 'administration')
        <section aria-labelledby="administration" class="space-panel flex flex-col gap-3 p-4 md:p-5">
            <h2 id="administration" class="text-[18px] font-extrabold">Administration</h2>
            <p class="text-muted">Courriel, nom et adresse de la plateforme, limites de sécurité, domaines, territoires, charte et mentions légales.</p>
            <x-button :href="route('espace.parametres')">Paramètres</x-button>
        </section>


        @endif
        @endif
    </div>

    {{-- Colonne droite : accompagnement --}}
    <aside aria-label="Accompagnement" class="flex min-w-0 flex-col gap-4 max-xl:order-3 md:grid md:grid-cols-2 md:items-start xl:flex xl:flex-col">
        <section aria-labelledby="actions-rapides" class="{{ $card }} p-4">
            <h2 id="actions-rapides" class="text-[17px] font-extrabold">Actions rapides</h2>
            <ul class="mt-2 flex flex-col gap-2">
                @foreach ([
                    ['Ajouter une réalisation', 'plus', 'bg-type-learning-bg text-type-learning-fg', route('agir.publier', 'ma-realisation')],
                    ['Créer une publication', 'document', 'bg-type-learning-bg text-type-learning-fg', route('agir')],
                    ['Modifier mes capacités', 'idea', 'bg-type-call-bg text-type-call-fg', route('espace.profil').'#offered-titre'],
                    ['Voir mon profil public', 'external', 'bg-type-learning-bg text-type-learning-fg', route('personnes.show', $profile->public_id)],
                ] as [$label, $icon, $tone, $href])
                    <li><a href="{{ $href }}" class="flex min-h-12 items-center gap-3 rounded-button border border-line px-3 text-[14px] font-semibold text-ink no-underline hover:bg-ground hover:text-ink"><span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $tone }}"><x-icon :name="$icon" :size="17" /></span><span class="flex-1">{{ $label }}</span><x-icon name="next" :size="16" /></a></li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="coup-d-oeil" class="{{ $card }} p-4">
            <h2 id="coup-d-oeil" class="text-[17px] font-extrabold">Mon profil en un coup d’œil</h2>
            @if ($completeness['next'])
                <p class="mt-1 text-[13px] text-muted">Éléments à renseigner</p>
                <ul class="mt-2 flex flex-col">
                    @foreach ($completeness['items'] as $item)
                        <li class="flex min-h-9 items-center gap-3 text-[14px]"><span @class(['flex size-6 shrink-0 items-center justify-center rounded-full', 'bg-type-knowledge-fg text-white' => $item['done'], 'border border-chip-line' => ! $item['done']])>@if ($item['done'])<x-icon name="check" :size="14" :stroke="3" />@endif</span>{{ $item['label'] }}<span class="sr-only">{{ $item['done'] ? ' : renseigné' : ' : à renseigner' }}</span></li>
                    @endforeach
                </ul>
                <x-button :href="$completeness['next']['url']" variant="accent" class="mt-3 w-full !text-[14px]">Compléter mon profil<x-icon name="arrow-right" :size="16" class="ml-2" /></x-button>
            @else
                <p class="mt-2 flex items-center gap-2 text-[14px] font-semibold text-type-knowledge-fg"><x-icon name="check-circle" :size="20" />Votre profil est complet.</p>
                <p class="mt-1 text-[13px] text-muted">Continuez à partager vos réalisations pour rester visible.</p>
            @endif
        </section>

        @if ($suggestedPeople->isNotEmpty())
            <section aria-labelledby="suggestions-titre-droite" class="{{ $card }} p-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="suggestions-titre-droite" class="text-[17px] font-extrabold">Suggestions pour vous</h2>
                    <a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Voir plus</a>
                </div>
                <ul class="mt-1 flex flex-col">
                    @foreach ($suggestedPeople as $person)
                        <li wire:key="suggestion-{{ $person->id }}" class="flex min-w-0 items-center gap-3 border-t border-line-soft py-3 first:border-t-0">
                            <x-avatar :profile="$person" :size="44" />
                            <div class="min-w-0 flex-1"><p class="break-words text-[14px] font-extrabold">{{ $person->user->name }}</p><p class="break-words text-[12px] text-muted">{{ $person->headline ?: $person->offeredSkills->take(2)->pluck('name')->implode(' · ') }}@if ($person->city) · {{ $person->city->label() }}@endif</p></div>
                            <x-button :href="route('personnes.show', $person->public_id)" variant="secondary" class="shrink-0 !px-3 !text-[12px]">Voir le profil</x-button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="conseils" class="{{ $card }} p-4">
            <h2 id="conseils" class="text-[17px] font-extrabold">Conseils et ressources</h2>
            <div class="mt-2 empty:hidden"><x-profile-next-step :user="$user" /></div>
            <ul class="mt-2 flex flex-col gap-3">
                @foreach ([['user', 'Indiquez vos capacités pour être mieux trouvé.'], ['project', 'Partagez vos réalisations avec le réseau.'], ['chat', 'Échangez et collaborez facilement.']] as [$icon, $tip])
                    <li class="flex items-start gap-3 text-[14px] leading-snug"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-type-learning-bg text-type-learning-fg"><x-icon :name="$icon" :size="17" /></span><span class="pt-1">{{ $tip }}</span></li>
                @endforeach
            </ul>
        </section>
    </aside>
</div>
