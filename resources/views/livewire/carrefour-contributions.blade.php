@php
    $chip = 'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-[14px] font-semibold';
    $field = 'w-full min-h-11 rounded-button border border-line bg-card px-3 text-[14px]';
    $tabIcons = ['tous' => 'grid', 'besoins' => 'heart', 'contributions' => 'check-circle', 'personnes' => 'group', 'zumra' => 'community', 'projets' => 'project', 'entreprises' => 'project'];
    $forms = [
        [\App\Enums\HelpKind::Financial, 'Soutenir avec une somme d’argent'],
        [\App\Enums\HelpKind::Skills, 'Apporter votre savoir-faire'],
        [\App\Enums\HelpKind::Time, 'Donner du temps ou participer'],
        [\App\Enums\HelpKind::Material, 'Fournir des équipements ou des ressources'],
    ];
@endphp

<div class="flex flex-col gap-4" x-data="{ filters: {{ $hasFilters ? 'true' : 'false' }} }">
    <nav aria-label="Fil d’Ariane" class="text-[13px] text-muted">
        <ol class="m-0 flex list-none flex-wrap items-center gap-1.5 p-0">
            <li><a href="{{ route('fil') }}" class="inline-flex min-h-11 items-center">Accueil</a></li>
            <li aria-hidden="true">›</li>
            <li><a href="{{ route('agir') }}" class="inline-flex min-h-11 items-center">Agir</a></li>
            <li aria-hidden="true">›</li>
            <li aria-current="page" class="font-semibold text-ink">Contributions</li>
        </ol>
    </nav>

    <header class="contrib-hero grid gap-5 rounded-card p-5 text-white md:p-8 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-3">
            <span class="inline-flex self-start rounded-chip bg-accent px-3 py-1 text-[12px] font-extrabold uppercase tracking-wide text-ink">Contributions</span>
            <h1 class="text-[clamp(28px,4vw,40px)] font-extrabold leading-tight tracking-[-0.8px]">Agir ensemble pour un impact réel</h1>
            <p class="max-w-xl text-[16px] leading-relaxed text-white/90">Soutenez des personnes, des ZUMRA, des projets et des entreprises de la communauté GAMAD. Contribuez avec vos compétences, votre temps, du matériel ou une aide financière. Toute contribution est volontaire.</p>
            <ul class="m-0 mt-2 grid list-none grid-cols-2 gap-3 p-0 md:grid-cols-4">
                @foreach ($forms as [$kind, $pitch])
                    <li class="flex items-start gap-2.5">
                        <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-ink"><x-icon :name="$kind->icon()" :size="20" /></span>
                        <span class="flex flex-col text-[13px] leading-snug"><strong class="text-[14px]">{{ $kind->label() }}</strong><span class="text-white/85">{{ $pitch }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="flex flex-col justify-center gap-3">
            <a href="{{ route('contributions.propose') }}" class="flex min-h-16 items-center gap-3 rounded-card bg-accent p-4 text-ink no-underline hover:text-ink"><x-icon name="plus" :size="26" /><span class="flex-1 text-[17px] font-extrabold leading-tight">Proposer une contribution</span><x-icon name="next" :size="18" /></a>
            <a href="{{ route('contributions.need') }}" class="flex min-h-16 items-center gap-3 rounded-card bg-card p-4 text-ink no-underline hover:text-ink"><x-icon name="document" :size="26" /><span class="flex-1"><strong class="block text-[16px]">Publier un besoin</strong><span class="block text-[13px] leading-snug text-muted">Exprimez un besoin pour mobiliser la communauté</span></span><x-icon name="next" :size="18" /></a>
        </div>
    </header>

    <div class="-mx-1 flex gap-1 overflow-x-auto rounded-card border border-line bg-card px-2" role="tablist" aria-label="Voir">
        @foreach ($tabs as $value => $label)
            <button type="button" role="tab" wire:click="showTab('{{ $value }}')" aria-selected="{{ $tab === $value ? 'true' : 'false' }}" @class([$chip, 'border-ink font-extrabold text-ink' => $tab === $value, 'border-transparent text-muted' => $tab !== $value])><x-icon :name="$tabIcons[$value]" :size="18" />{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)_280px]">
        <aside class="flex min-w-0 flex-col gap-3" aria-label="Filtres">
            <button type="button" x-on:click="filters = ! filters" x-bind:aria-expanded="filters.toString()" aria-controls="filtres-contributions" class="inline-flex min-h-11 items-center justify-between gap-2 rounded-button border border-line bg-card px-4 text-[14px] font-extrabold lg:hidden"><span class="inline-flex items-center gap-2"><x-icon name="filter" :size="18" />Filtres</span><x-icon name="chevron-down" :size="18" /></button>
            <div id="filtres-contributions" x-show="filters" x-cloak class="max-lg:hidden flex flex-col gap-4 rounded-card border border-line bg-card p-4" x-bind:class="filters && 'max-lg:!flex'">
                <h2 class="hidden text-[16px] font-extrabold lg:block">Filtres</h2>
                <div class="flex items-center gap-2 rounded-button border border-line px-3">
                    <x-icon name="search" :size="18" class="shrink-0" />
                    <label for="recherche-contributions" class="sr-only">Rechercher un besoin ou une contribution</label>
                    <input id="recherche-contributions" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Rechercher un besoin ou une contribution…" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[14px]">
                </div>
                <fieldset class="flex flex-col"><legend class="mb-1 text-[14px] font-extrabold">Type de contribution</legend>
                    @foreach ($helpKinds as $kind)
                        <label class="flex min-h-11 items-center gap-3 text-[14px]"><input type="checkbox" wire:model.live="kinds" value="{{ $kind->value }}" class="size-5 shrink-0">{{ $kind->label() }}</label>
                    @endforeach
                </fieldset>
                <fieldset class="flex flex-col"><legend class="mb-1 text-[14px] font-extrabold">Bénéficiaire</legend>
                    @foreach ($beneficiaryKinds as $kind)
                        <label class="flex min-h-11 items-center gap-3 text-[14px]"><input type="checkbox" wire:model.live="beneficiaries" value="{{ $kind->value }}" class="size-5 shrink-0">{{ $kind->label() }}</label>
                    @endforeach
                </fieldset>
                <div class="flex flex-col gap-1"><label for="filtre-domaine-c" class="text-[14px] font-extrabold">Domaine</label>
                    <select id="filtre-domaine-c" wire:model.live="domain" class="{{ $field }}"><option value="">Tous les domaines</option>@foreach ($domains as $domaine)<option value="{{ $domaine->id }}">{{ $domaine->name }}</option>@endforeach</select></div>
                <div class="flex flex-col gap-1"><label for="filtre-ville-c" class="text-[14px] font-extrabold">Localité</label>
                    <select id="filtre-ville-c" wire:model.live="city" class="{{ $field }}"><option value="">Toutes les localités</option>@foreach ($cities as $ville)<option value="{{ $ville->id }}">{{ $ville->label() }}</option>@endforeach</select></div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="min-h-11 self-start text-[14px] font-semibold text-link underline">Réinitialiser</button>@endif
            </div>
        </aside>

        <main class="flex min-w-0 flex-col gap-6" wire:loading.class="opacity-60">
            @if ($showNeeds)
                <section aria-labelledby="besoins-recents" class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="besoins-recents" class="text-[20px] font-extrabold">Besoins récents</h2>
                        @if ($tab === 'tous' && $needs->count() >= \App\Livewire\CarrefourContributions::PREVIEW)<button type="button" wire:click="showTab('besoins')" class="min-h-11 text-[14px] font-semibold text-link underline">Voir tout</button>@endif
                    </div>
                    @if ($needs->isEmpty())
                        <section class="flex flex-col gap-2 rounded-card border border-line bg-card p-5">
                            <p class="text-[16px] font-extrabold">{{ $hasFilters ? 'Aucun besoin ne correspond à cette recherche.' : 'Aucun besoin publié pour l’instant.' }}</p>
                            <p class="text-muted">{{ $hasFilters ? 'Essayez un autre mot ou élargissez les filtres.' : 'Dites simplement ce dont vous avez besoin : la communauté pourra y répondre.' }}</p>
                            @if ($hasFilters)<x-button type="button" variant="secondary" wire:click="clearFilters" class="self-start">Effacer les filtres</x-button>
                            @else<x-button :href="route('contributions.need')" variant="accent" class="self-start">Publier un besoin</x-button>@endif
                        </section>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach ($needs as $need)@include('livewire.partials.contribution-need-card', ['need' => $need, 'viewer' => $viewer])@endforeach</div>
                        @if ($paginate && $needs->hasPages())<div>{{ $needs->links() }}</div>@endif
                    @endif
                </section>
            @endif

            @if ($showProposals)
                <section aria-labelledby="contributions-recentes" class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="contributions-recentes" class="text-[20px] font-extrabold">Contributions récentes</h2>
                        @if ($tab === 'tous' && $proposals->count() >= \App\Livewire\CarrefourContributions::PREVIEW)<button type="button" wire:click="showTab('contributions')" class="min-h-11 text-[14px] font-semibold text-link underline">Voir tout</button>@endif
                    </div>
                    @if ($proposals->isEmpty())
                        <section class="flex flex-col gap-2 rounded-card border border-line bg-card p-5">
                            <p class="text-[16px] font-extrabold">{{ $hasFilters ? 'Aucune contribution ne correspond à cette recherche.' : 'Aucune contribution publique pour l’instant.' }}</p>
                            <p class="text-muted">Une contribution n’apparaît ici que si la personne choisit de la rendre publique.</p>
                            <x-button :href="route('contributions.propose')" variant="accent" class="self-start">Proposer une contribution</x-button>
                        </section>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach ($proposals as $proposal)@include('livewire.partials.contribution-proposal-card', ['proposal' => $proposal, 'viewer' => $viewer])@endforeach</div>
                        @if ($paginate && $proposals->hasPages())<div>{{ $proposals->links() }}</div>@endif
                    @endif
                </section>
            @endif
        </main>

        <aside class="flex min-w-0 flex-col gap-4" aria-label="Mon engagement et impact">
            <section aria-labelledby="mon-engagement" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <h2 id="mon-engagement" class="flex items-center gap-2 text-[17px] font-extrabold"><x-icon name="tasks" :size="20" />Mon engagement</h2>
                <dl class="m-0 grid grid-cols-[auto_1fr] items-baseline gap-x-3 gap-y-1.5 text-[14px]">
                    <dt class="text-right text-[20px] font-extrabold">{{ $mine['proposed'] }}</dt><dd class="m-0 text-muted">{{ $mine['proposed'] > 1 ? 'Contributions proposées' : 'Contribution proposée' }}</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ $mine['needs'] }}</dt><dd class="m-0 text-muted">{{ $mine['needs'] > 1 ? 'Besoins publiés' : 'Besoin publié' }}</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ $mine['in_progress'] }}</dt><dd class="m-0 text-muted">{{ $mine['in_progress'] > 1 ? 'Actions en cours' : 'Action en cours' }}</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ $mine['realized'] }}</dt><dd class="m-0 text-muted">{{ $mine['realized'] > 1 ? 'Réalisations confirmées' : 'Réalisation confirmée' }}</dd>
                </dl>
                @if ($mine['to_handle'] > 0)<p class="rounded-button bg-soft-blue px-3 py-2 text-[13px] font-semibold">{{ $mine['to_handle'] }} {{ $mine['to_handle'] > 1 ? 'propositions attendent' : 'proposition attend' }} votre réponse.</p>@endif
                <x-button :href="route('espace.mes-contributions')" variant="secondary" class="gap-2">Voir mes contributions<x-icon name="next" :size="16" /></x-button>
            </section>

            <section aria-labelledby="impact" class="flex flex-col gap-3 rounded-card bg-type-call-bg p-4">
                <h2 id="impact" class="flex items-center gap-2 text-[17px] font-extrabold"><x-icon name="heart" :size="20" />Impact de la communauté</h2>
                <dl class="m-0 grid grid-cols-[auto_1fr] items-baseline gap-x-3 gap-y-1.5 text-[14px]">
                    <dt class="text-right text-[20px] font-extrabold">{{ $community['needs'] }}</dt><dd class="m-0">Besoins publiés</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ \App\Services\ContributionStats::anonymised($community['proposals']) }}</dt><dd class="m-0">Propositions reçues</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ \App\Services\ContributionStats::anonymised($community['accepted']) }}</dt><dd class="m-0">Contributions acceptées</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ \App\Services\ContributionStats::anonymised($community['realized']) }}</dt><dd class="m-0">Réalisations confirmées</dd>
                    <dt class="text-right text-[20px] font-extrabold">{{ $community['satisfied'] }}</dt><dd class="m-0">Besoins satisfaits</dd>
                </dl>
                <p class="text-[12px] leading-snug text-muted">Des faits enregistrés dans GAMAD. Une intention n’est ni un engagement accepté, ni une réalisation confirmée. Aucun montant n’est collecté ici.</p>
            </section>

            <section aria-labelledby="solidarite" class="flex flex-col gap-1.5 rounded-card bg-soft-blue p-4">
                <h2 id="solidarite" class="flex items-center gap-2 text-[16px] font-extrabold"><x-icon name="shield" :size="20" />Une solidarité maîtrisée</h2>
                <p class="text-[13px] leading-relaxed">Chaque contribution est volontaire. Elle respecte les règles de confidentialité et les choix du bénéficiaire, et ne donne aucun droit de propriété, de direction ou de contrôle. Aucune contribution n’est obligatoire.</p>
            </section>
        </aside>
    </div>
</div>
