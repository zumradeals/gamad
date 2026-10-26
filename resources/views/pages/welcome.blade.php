<x-layouts.landing>
    <section class="landing-container landing-hero" aria-labelledby="accroche">
        <div class="flex min-w-0 flex-col items-start gap-5">
            <span class="inline-flex items-center gap-2 rounded-chip bg-soft-blue px-3 py-2 text-[12px] font-bold text-link"><x-icon name="group" :size="18" />Le réseau social d’action</span>
            <h1 id="accroche" class="landing-headline">Des personnes qui inspirent. <span class="block text-[#a35f00]">De nouvelles possibilités.</span></h1>
            <p class="max-w-lg text-[16px] leading-relaxed md:text-[18px]">Sur GAMAD, découvrez des personnes, apprenez de leurs expériences, créez des liens et avancez à votre rythme.</p>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <x-button :href="route('personnes.index')" variant="accent" class="gap-3">Découvrir les personnes<x-icon name="arrow-right" :size="18" /></x-button>
                <x-button :href="route('register')" variant="secondary">Créer un compte</x-button>
            </div>
            @if ($people->isNotEmpty())
                <div class="flex items-center gap-3">
                    <span class="flex -space-x-2">@foreach ($people as $person)<x-avatar :profile="$person" :size="36" class="border-2 border-card" />@endforeach</span>
                    <p class="text-[12px] leading-snug text-muted">Une communauté de personnes<br>de tous horizons.</p>
                </div>
            @endif
        </div>
        <figure class="landing-hero-visual">
            <div class="landing-photo"><img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="Quatre personnes échangeant autour d’un ordinateur lors d’une rencontre." width="510" height="280" fetchpriority="high" decoding="async" class="block h-auto w-full rounded-[20px]"></div>
            <figcaption class="landing-photo-caption flex items-center gap-3"><span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-type-call-bg text-type-call-fg"><x-icon name="idea" :size="22" /></span><span><span class="block text-[14px] font-extrabold">Une rencontre peut ouvrir de nouvelles possibilités.</span><span class="mt-1 block text-[12px] text-muted">Un savoir partagé, un besoin exprimé, une relation qui commence.</span></span></figcaption>
        </figure>
    </section>

    <section class="border-y border-line bg-card" aria-labelledby="recherche-titre">
        <div class="landing-container flex flex-col gap-4 py-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-6">
                <h2 id="recherche-titre" class="shrink-0 text-[17px] font-extrabold">Que souhaitez-vous découvrir ?</h2>
                <form action="{{ route('personnes.index') }}" method="GET" role="search" class="flex min-w-0 flex-1 items-center gap-2 rounded-button border border-chip-line bg-ground p-1.5">
                    <x-icon name="search" :size="20" class="ml-2 shrink-0" /><label for="public-search" class="sr-only">Rechercher une personne, un métier, un savoir-faire</label>
                    <input id="public-search" type="search" name="q" maxlength="100" placeholder="Une personne, un métier, un savoir-faire, une organisation…" class="min-h-11 min-w-0 flex-1 bg-transparent px-1 text-[14px] outline-none">
                    <button type="submit" aria-label="Rechercher" class="inline-flex size-11 shrink-0 items-center justify-center rounded-button bg-accent text-ink"><x-icon name="search" :size="20" /></button>
                </form>
            </div>
            <ul class="m-0 flex list-none flex-wrap gap-2 p-0 md:pl-[250px]" aria-label="Domaines">
                @foreach ($domains as $domain)<li><a href="{{ route('personnes.index', ['domaine' => $domain->id]) }}" class="inline-flex min-h-11 items-center rounded-chip border border-line bg-ground px-4 text-[13px] font-semibold text-ink no-underline hover:text-ink">{{ $domain->name }}</a></li>@endforeach
                <li><a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center rounded-chip border border-line bg-ground px-4 text-[13px] font-semibold text-ink no-underline hover:text-ink">Tous les domaines</a></li>
            </ul>
        </div>
    </section>

    <section id="comment" class="landing-container landing-section" aria-labelledby="comment-titre">
        <h2 id="comment-titre" class="landing-section-title">Découvrez, connectez-vous, grandissez</h2>
        <p class="mt-2 text-[15px] text-muted">Un parcours simple, sans obligation. Chacun avance à son rythme.</p>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach ([['compass', 'Explorer', 'Découvrez des personnes, des idées et des expériences inspirantes.', 'bg-soft-blue', 'text-link'], ['group', 'Se connecter', 'Suivez les profils qui vous intéressent et échangez en toute confiance.', 'bg-type-call-bg', 'text-type-call-fg'], ['learn', 'Grandir', 'Apprenez, partagez et agissez quand vous le souhaitez.', 'bg-type-knowledge-bg', 'text-type-knowledge-fg']] as [$icon, $title, $description, $bg, $fg])
                <article class="flex flex-col items-center gap-3 rounded-card p-6 text-center {{ $bg }}"><span class="flex size-14 items-center justify-center rounded-full bg-card {{ $fg }}"><x-icon :name="$icon" :size="28" /></span><h3 class="text-[20px] font-extrabold">{{ $title }}</h3><p class="text-[14px] leading-relaxed text-ink">{{ $description }}</p></article>
            @endforeach
        </div>
    </section>

    <section id="explorer" class="bg-card" aria-labelledby="explorer-titre">
        <div class="landing-container landing-section">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 id="explorer-titre" class="landing-section-title">Des personnes à découvrir</h2><p class="mt-2 text-[15px] text-muted">Des parcours différents. Des compétences et des expériences qui peuvent vous inspirer.</p></div><a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center gap-2 text-[14px] font-bold">Explorer les profils<x-icon name="arrow-right" :size="18" /></a></div>
            @if ($people->isNotEmpty())
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($people as $person)
                        <article class="flex min-w-0 flex-col overflow-hidden rounded-card border border-line bg-card">
                            @if ($person->photoUrl())<img src="{{ $person->photoUrl() }}" alt="" width="300" height="200" loading="lazy" decoding="async" class="h-44 w-full object-cover">@else<div class="flex h-44 items-center justify-center bg-soft-blue"><x-avatar :profile="$person" :size="88" /></div>@endif
                            <div class="flex flex-1 flex-col gap-2 p-4"><h3 class="break-words text-[17px] font-extrabold">{{ $person->user->name }}</h3>@if ($person->city)<p class="text-[13px] text-muted">{{ $person->city->label() }}</p>@endif
                                <ul class="m-0 flex list-none flex-wrap gap-1.5 p-0">@foreach ($person->offeredSkills->take(2) as $skill)<li class="max-w-full break-words rounded-chip bg-soft-blue px-2.5 py-1 text-[12px] font-semibold text-link">{{ $skill->name }}</li>@endforeach</ul>
                                <a href="{{ route('personnes.show', $person->public_id) }}" class="mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-button border border-line text-[13px] font-bold text-ink no-underline hover:text-ink">Voir le profil<x-icon name="arrow-right" :size="16" /></a></div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-card border border-line bg-ground p-6"><p class="text-[15px] text-muted">Les profils publics se découvrent ici. Créez le vôtre pour présenter ce que vous savez faire.</p><a href="{{ route('register') }}" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Créer mon profil →</a></div>
            @endif
        </div>
    </section>

    <section id="partages" class="landing-container landing-section" aria-labelledby="partages-titre">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 id="partages-titre" class="landing-section-title">Des partages et des perspectives</h2><p class="mt-2 text-[15px] text-muted">Découvrez des idées, des expériences et des initiatives partagées par la communauté.</p></div></div>
        @if ($posts->isNotEmpty())
            <div class="mt-6 grid items-start gap-4 md:grid-cols-3">
                @foreach ($posts as $post)
                    @php $lines = preg_split('/\R/', trim($post->body)); $title = Str::limit($lines[0] ?? '', 80); $rest = trim(implode(' ', array_slice($lines, 1))); @endphp
                    <article class="flex min-w-0 flex-col gap-2 overflow-hidden rounded-card border border-line bg-card p-5">
                        <x-publication-media :publication="$post" />
                        <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $post->type->badgeClasses() }}">{{ $post->displayLabel() }}</span>
                        <h3 class="break-words text-[17px] font-extrabold leading-snug"><a href="{{ route('publications.show', $post) }}" class="text-ink no-underline hover:underline">{{ $title }}</a></h3>
                        @if ($rest !== '')<p class="break-words text-[14px] leading-relaxed text-muted">{{ Str::limit($rest, 140) }}</p>@endif
                        <p class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2 text-[12px] text-muted"><a href="{{ route('personnes.show', $post->author->profile->public_id) }}" class="inline-flex min-h-11 items-center break-words font-semibold">{{ $post->author->name }}</a><time datetime="{{ $post->created_at->toDateString() }}">{{ $post->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->isoFormat('D MMM YYYY') }}</time></p>
                    </article>
                @endforeach
            </div>
        @else
            <div class="mt-6 rounded-card border border-line bg-card p-6"><p class="text-[15px] text-muted">Les partages publics de la communauté apparaîtront ici dès qu’ils seront publiés.</p></div>
        @endif
    </section>

    <section id="confiance" class="landing-container landing-section" aria-labelledby="confiance-titre">
        <div class="grid gap-6 rounded-card bg-ink p-6 text-white md:grid-cols-[1fr_1.3fr] md:p-10">
            <div><x-icon name="shield" :size="40" class="mb-4 text-accent" /><h2 id="confiance-titre" class="text-[27px] font-extrabold leading-tight">Des relations qui se construisent avec confiance.</h2><p class="mt-4 text-[14px] leading-relaxed text-white/85">Vous choisissez ce que vous partagez, qui vous suivez et les personnes avec qui vous échangez.</p></div>
            <div class="flex flex-col gap-5">
                @foreach ([['user', 'Votre parcours vous appartient', 'Vos expériences et vos compétences méritent d’être découvertes.'], ['lock', 'Vos informations sont protégées', 'Vous contrôlez ce que vous partagez et qui peut y accéder. Votre téléphone et votre e-mail restent privés.'], ['community', 'L’action reste un choix', 'Participez à une communauté, créez un projet ou soutenez une initiative quand vous le souhaitez.']] as [$icon, $title, $description])<div class="flex items-start gap-4"><x-icon :name="$icon" :size="28" class="mt-0.5 shrink-0 text-accent" /><div><h3 class="text-[15px] font-extrabold">{{ $title }}</h3><p class="mt-1 text-[13px] leading-relaxed text-white/85">{{ $description }}</p></div></div>@endforeach
            </div>
        </div>
    </section>

    <section class="landing-container flex flex-col items-center gap-4 pb-14 pt-4 text-center" aria-labelledby="rejoindre-titre"><h2 id="rejoindre-titre" class="landing-section-title">Votre place existe déjà sur GAMAD.</h2><p class="max-w-xl text-[15px] leading-relaxed text-muted">Commencez par découvrir. La suite viendra naturellement.</p><x-button :href="route('register')" variant="accent" class="gap-3">Créer un compte<x-icon name="arrow-right" :size="18" /></x-button><a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold">Découvrir les personnes</a></section>
</x-layouts.landing>
