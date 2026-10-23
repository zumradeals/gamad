@php
    $steps = [
        ['user', 'bg-type-learning-bg text-type-learning-fg', 'Partagez vos capacités', 'Dites ce que vous pouvez apporter et ce que vous voulez apprendre.'],
        ['community', 'bg-type-knowledge-bg text-type-knowledge-fg', 'Trouvez votre ZUMRA', 'Rejoignez une communauté qui apprend, travaille et agit ensemble.'],
        ['project', 'bg-type-call-bg text-type-call-fg', 'Faites avancer un projet', 'Un but, des étapes et une équipe pour agir concrètement.'],
        ['idea', 'bg-type-need-bg text-type-need-fg', 'Passez à l’action', 'Dans le Fil, répondez par un geste : aider, apprendre ou transmettre.'],
    ];
    $directories = [
        ['user', 'bg-type-learning-bg text-type-learning-fg', 'Personnes', 'Des capacités à rencontrer', 'Découvrir les personnes', 'personnes.index'],
        ['community', 'bg-type-knowledge-bg text-type-knowledge-fg', 'ZUMRA', 'Des communautés pour agir ensemble', 'Explorer les ZUMRA', 'zumras.index'],
        ['project', 'bg-type-call-bg text-type-call-fg', 'Projets', 'Des actions qui prennent forme', 'Voir les projets', 'projets.index'],
    ];
    if (\App\Support\Experience::initial()) {
        $steps = [
            ['user', 'bg-type-learning-bg text-type-learning-fg', 'Partagez vos capacités', 'Dites ce que vous pouvez apporter et ce que vous voulez apprendre.'],
            ['group', 'bg-type-knowledge-bg text-type-knowledge-fg', 'Rencontrez des personnes', 'Trouvez quelqu’un par ses capacités, sa ville ou son domaine.'],
            ['idea', 'bg-type-need-bg text-type-need-fg', 'Exprimez un besoin', 'Dans le Fil, dites ce qui vous manque ou partagez un savoir.'],
            ['heart', 'bg-type-call-bg text-type-call-fg', 'Entraidez-vous', 'Proposez votre aide. Une réponse acceptée ouvre un échange privé.'],
        ];
        $directories = [
            ['user', 'bg-type-learning-bg text-type-learning-fg', 'Personnes', 'Des capacités à rencontrer', 'Découvrir les personnes', 'personnes.index'],
            ['feed', 'bg-type-knowledge-bg text-type-knowledge-fg', 'Fil', 'Des savoirs, des besoins et des gestes d’entraide', 'Rejoindre le Fil', 'register'],
        ];
    }
@endphp

<x-layouts.landing>
    <section class="landing-container landing-hero" aria-labelledby="accroche">
        <div class="flex min-w-0 flex-col items-start gap-5">
            <span class="inline-flex items-center gap-2 rounded-chip border border-ink px-3 py-1.5 text-[11px] font-extrabold tracking-wide"><x-icon name="group" :size="18" />Le Réseau GAMAD</span>
            <h1 id="accroche" class="landing-headline"><span class="block">Des personnes.</span> <span class="block">Des actions.</span> <span class="landing-highlight">Un impact réel.</span></h1>
            <p class="max-w-lg text-[16px] leading-relaxed md:text-[17px]">Apportez ce que vous savez faire, apprenez ce qui vous manque, agissez avec d’autres.</p>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <x-button :href="route('register')" variant="accent" class="gap-5">Créer un compte<x-icon name="arrow-right" :size="20" /></x-button>
                <x-button :href="route('login')" variant="secondary">Se connecter</x-button>
            </div>
            <p class="text-[14px] leading-relaxed text-muted">Un compte GAMAD ne vaut pas adhésion au Mouvement.</p>
        </div>

        <figure class="landing-hero-visual">
            <div class="landing-photo">
                <img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="Illustration de quatre adultes échangeant et apprenant ensemble autour d’un ordinateur." width="510" height="280" fetchpriority="high" decoding="async" class="block h-auto w-full rounded-[20px]">
            </div>
            <figcaption class="landing-photo-caption">
                <p class="mb-2 text-[11px] font-extrabold">Des échanges qui deviennent des actions</p>
                <div class="flex flex-wrap gap-2 text-[11px] font-bold">
                    <span class="inline-flex items-center gap-2 rounded-button bg-type-need-bg px-3 py-2 text-type-need-fg"><x-icon name="group" :size="19" />Je peux aider</span>
                    <span class="inline-flex items-center gap-2 rounded-button bg-type-learning-bg px-3 py-2 text-type-learning-fg"><x-icon name="book" :size="19" />Je veux apprendre</span>
                </div>
            </figcaption>
        </figure>
    </section>

    <div class="bg-card">
        <section id="comment" aria-labelledby="comment-titre" class="landing-container landing-section">
            <h2 id="comment-titre" class="landing-section-title">Comment ça marche</h2>
            <p class="mb-5 mt-1 text-[15px] text-muted">De vos capacités aux actions concrètes.</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as [$icon, $colors, $title, $description])
                    <article class="landing-card flex flex-col gap-3 p-5">
                        <div class="mb-1 flex items-start justify-between gap-3"><span class="flex size-12 items-center justify-center rounded-card {{ $colors }}"><x-icon :name="$icon" :size="27" /></span><span class="text-[11px] font-semibold text-muted">{{ sprintf('%02d', $loop->iteration) }}</span></div>
                        <h3 class="text-[16px] font-extrabold leading-snug">{{ $title }}</h3>
                        <p class="text-[14px] leading-relaxed text-muted">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="explorer" aria-labelledby="explorer-titre" class="landing-container landing-section !pt-2">
            <h2 id="explorer-titre" class="landing-section-title">Découvrez le réseau</h2>
            <p class="mb-5 mt-1 text-[15px] text-muted">{{ \App\Support\Experience::initial() ? 'Des personnes à rencontrer, des savoirs à partager, des besoins auxquels répondre.' : 'Des personnes, des communautés, des projets à explorer.' }}</p>
            <div @class(['grid gap-4', 'md:grid-cols-2' => \App\Support\Experience::initial(), 'md:grid-cols-3' => ! \App\Support\Experience::initial()])>
                @foreach ($directories as [$icon, $colors, $title, $description, $label, $route])
                    <a href="{{ route($route) }}" class="landing-card group flex min-w-0 items-start gap-4 p-5 text-ink no-underline transition hover:border-ink hover:text-ink">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-card {{ $colors }}"><x-icon :name="$icon" :size="27" /></span>
                        <span class="flex min-w-0 flex-col"><span class="text-[16px] font-extrabold">{{ $title }}</span><span class="mt-1 text-[14px] leading-relaxed text-muted">{{ $description }}</span><span class="mt-4 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link group-hover:underline">{{ $label }}<x-icon name="arrow-right" :size="17" class="shrink-0" /></span></span>
                    </a>
                @endforeach
            </div>

            @if ($figures !== [] || $recentZumras->isNotEmpty() || $recentProjects->isNotEmpty())
                <section aria-labelledby="reel" class="mt-6 flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 id="reel" class="text-[17px] font-extrabold">Ce qui se passe en ce moment</h3>
                        @if ($figures !== [])
                            <ul class="flex flex-wrap gap-x-4 gap-y-2" aria-label="GAMAD en chiffres">
                                @foreach ($figures as $label => $n)
                                    <li class="text-[14px] text-muted"><strong class="font-extrabold text-ink">{{ $n }}</strong> {{ $label === 'personnes' && $n === 1 ? 'personne' : ($label === 'projets' && $n === 1 ? 'projet' : $label) }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @if ($recentZumras->isNotEmpty())
                        <div>
                            <h4 class="mb-3 text-[15px] font-extrabold">{{ $recentZumras->count() === 1 ? 'Une communauté à découvrir' : 'Des communautés à découvrir' }}</h4>
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ($recentZumras as $zumra)
                                    <div class="landing-card flex min-w-0 flex-wrap items-center gap-3 p-4">
                                        <x-zumra-initials :zumra="$zumra" :size="48" />
                                        <div class="min-w-0 flex-1 basis-40"><a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:text-link">{{ $zumra->name }}</a><p class="text-[14px] leading-relaxed text-muted">{{ collect([$zumra->domain->name, $zumra->city?->label()])->filter()->implode(' · ') }}</p></div>
                                        <x-button :href="route('zumras.show', $zumra)" variant="secondary" class="!px-4 !text-[13px]">Découvrir</x-button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($recentProjects->isNotEmpty())
                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach ($recentProjects as $project)
                                <a href="{{ route('projets.show', $project) }}" class="landing-card flex min-w-0 flex-col gap-2 p-4 text-ink no-underline hover:border-ink hover:text-ink">
                                    <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $project->state->badgeClasses() }}">{{ $project->state->label() }}</span>
                                    <span class="break-words text-[15px] font-extrabold">{{ $project->name }}</span><span class="break-words text-[14px] text-muted">Projet de {{ $project->zumra->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </section>

        <section aria-labelledby="promesses" class="landing-container pb-10">
            <div class="rounded-card bg-ink px-5 py-7 text-white md:p-8">
                <h2 id="promesses" class="text-[22px] font-extrabold leading-tight md:text-center md:text-[25px]">Un réseau fondé sur l’action et le respect</h2>
                <div class="mt-6 grid gap-6 md:grid-cols-3 md:gap-0 md:divide-x md:divide-white/20">
                    <div class="flex items-start gap-3 md:pr-5"><x-icon name="group" :size="27" class="shrink-0 text-accent" /><div><h3 class="text-[14px] font-extrabold">Des engagements, pas des « j’aime »</h3><p class="mt-1 text-[13px] leading-relaxed text-white/90">Aucune note ni classement de personnes.</p></div></div>
                    <div class="flex items-start gap-3 md:px-5"><x-icon name="lock" :size="27" class="shrink-0 text-accent" /><div><h3 class="text-[14px] font-extrabold">Votre vie privée respectée</h3><p class="mt-1 text-[13px] leading-relaxed text-white/90">Votre e-mail et votre téléphone restent privés.</p></div></div>
                    <div class="flex items-start gap-3 md:pl-5"><x-icon name="community" :size="27" class="shrink-0 text-accent" /><div><h3 class="text-[14px] font-extrabold">Un réseau ouvert à tous</h3><p class="mt-1 text-[13px] leading-relaxed text-white/90">Créer un compte ne signifie pas adhérer au Mouvement GAMAD.</p></div></div>
                </div>
            </div>
        </section>
    </div>

    <section class="landing-container flex flex-col items-center gap-3 py-10 text-center" aria-label="Rejoindre GAMAD">
        <h2 class="text-[24px] font-extrabold leading-tight md:text-[28px]">Et vous, que voulez-vous faire avancer ?</h2>
        <p class="text-[14px] leading-relaxed text-muted">Un savoir à partager, une envie d’apprendre, un besoin à exprimer.</p>
        <x-button :href="route('register')" variant="accent" class="mt-2 gap-5">Créer un compte<x-icon name="arrow-right" :size="19" /></x-button>
    </section>
</x-layouts.landing>
