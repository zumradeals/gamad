<x-layouts.landing>
    <section class="landing-container landing-hero" aria-labelledby="accroche">
        <div class="flex min-w-0 flex-col items-start gap-5">
            <span class="inline-flex items-center gap-2 rounded-chip border border-line bg-card px-3 py-2 text-[12px] font-bold"><x-icon name="group" :size="18" />Le Réseau GAMAD</span>
            <h1 id="accroche" class="landing-headline">Votre savoir-faire <span class="landing-highlight">mérite d’être connu.</span></h1>
            <p class="max-w-lg text-[16px] leading-relaxed md:text-[18px]">Rencontrez des personnes, partagez vos réalisations et trouvez des occasions d’apprendre, d’aider ou de travailler.</p>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <x-button :href="route('register')" variant="accent" class="gap-3">Créer un compte<x-icon name="arrow-right" :size="18" /></x-button>
                <x-button :href="route('personnes.index')" variant="secondary">Découvrir les personnes</x-button>
            </div>
            <p class="text-[13px] text-muted">Artisan, salarié, indépendant, étudiant ou autodidacte : chacun a quelque chose à apporter.</p>
        </div>
        <figure class="landing-hero-visual">
            <div class="landing-photo"><img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="Illustration de quatre adultes échangeant et apprenant ensemble autour d’un ordinateur." width="510" height="280" fetchpriority="high" decoding="async" class="block h-auto w-full rounded-[20px]"></div>
            <figcaption class="landing-photo-caption"><p class="text-[14px] font-extrabold">Une rencontre peut ouvrir de nouvelles possibilités.</p><p class="mt-2 text-[12px] text-muted">Un savoir partagé, un besoin exprimé, une relation qui commence.</p></figcaption>
        </figure>
    </section>

    <section class="border-y border-line bg-card" aria-label="Rechercher une personne">
        <form action="{{ route('personnes.index') }}" method="GET" role="search" class="landing-container flex flex-col gap-3 py-6 md:flex-row md:items-center md:gap-6">
            <label for="public-search" class="shrink-0 text-[17px] font-extrabold">Qui cherchez-vous ?</label>
            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-button border border-chip-line bg-ground p-1.5"><x-icon name="search" :size="20" class="ml-2 shrink-0" /><input id="public-search" type="search" name="q" maxlength="100" placeholder="Un nom ou un savoir-faire : plomberie, couture…" class="min-h-11 min-w-0 flex-1 bg-transparent px-1 text-[14px] outline-none"><button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-button bg-ink px-3 text-[13px] font-bold text-white sm:px-5">Rechercher</button></div>
        </form>
    </section>

    <section id="comment" class="landing-container landing-section" aria-labelledby="comment-titre">
        <div class="max-w-2xl"><p class="mb-2 text-[12px] font-extrabold uppercase tracking-wider text-muted">Des possibilités au quotidien</p><h2 id="comment-titre" class="landing-section-title">Votre profil ouvre la conversation.</h2><p class="mt-3 text-[15px] leading-relaxed text-muted">Montrez ce que vous savez faire et découvrez ce que vous pouvez construire avec d’autres.</p></div>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach ([['user', 'Montrez votre savoir-faire', 'Présentez vos capacités, vos expériences et vos réalisations, avec ou sans diplôme.'], ['group', 'Rencontrez des personnes', 'Découvrez des profils et entrez en relation autour d’un savoir, d’un besoin ou d’une envie commune.'], ['idea', 'Exprimez un besoin', 'Proposez vos services, partagez un savoir ou cherchez une personne pour avancer.']] as [$icon, $title, $description])
                <article class="landing-card p-6"><span class="mb-4 flex size-11 items-center justify-center rounded-button bg-soft-blue"><x-icon :name="$icon" :size="24" /></span><h3 class="text-[18px] font-extrabold">{{ $title }}</h3><p class="mt-3 text-[14px] leading-relaxed text-muted">{{ $description }}</p></article>
            @endforeach
        </div>
    </section>

    <section id="explorer" class="bg-card" aria-labelledby="explorer-titre">
        <div class="landing-container landing-section">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 id="explorer-titre" class="landing-section-title">Des personnes à découvrir.</h2><p class="mt-3 text-[15px] text-muted">Des parcours différents. Des capacités qui peuvent se rencontrer.</p></div><a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center gap-2 text-[14px] font-bold">Explorer les profils<x-icon name="arrow-right" :size="18" /></a></div>
            @if ($people->isNotEmpty())
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach ($people as $person)
                        <article class="flex min-w-0 flex-col rounded-card border border-line bg-ground p-5"><x-avatar :profile="$person" :size="64" /><h3 class="mt-4 break-words text-[18px] font-extrabold">{{ $person->user->name }}</h3>@if ($person->headline)<p class="mt-1 break-words text-[14px] font-semibold">{{ $person->headline }}</p>@endif
                            @if ($person->city)<p class="mt-2 text-[12px] text-muted">{{ $person->city->label() }}</p>@endif
                            <ul class="mt-3 flex flex-wrap gap-2">@foreach ($person->offeredSkills->take(3) as $skill)<li class="max-w-full break-words rounded-chip bg-card px-3 py-1.5 text-[12px] font-semibold">{{ $skill->name }}</li>@endforeach</ul>
                            <a href="{{ route('personnes.show', $person->public_id) }}" class="mt-auto inline-flex min-h-11 items-center pt-3 text-[13px] font-bold">Découvrir son profil →</a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-card border border-line bg-ground p-6"><p class="text-[15px] text-muted">Les profils publics se découvrent ici. Créez le vôtre pour présenter ce que vous savez faire.</p><a href="{{ route('register') }}" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Créer mon profil →</a></div>
            @endif
            @if ($posts->isNotEmpty())
                <h3 class="mb-4 mt-10 text-[21px] font-extrabold">Des partages et des occasions.</h3>
                <div class="grid items-start gap-4 md:grid-cols-3">
                    @foreach ($posts as $post)
                        <article class="min-w-0 rounded-card border border-line p-5"><span class="rounded-chip px-2.5 py-1.5 text-[12px] font-bold {{ $post->type->badgeClasses() }}">{{ $post->displayLabel() }}</span><p class="mt-3 text-[12px] text-muted">Publié le {{ $post->created_at->format('d/m/Y') }}@if ($post->need_state) · {{ $post->need_state->label() }}@endif @if (($post->matching['enabled'] ?? false) && ! ($post->matching['open'] ?? true)) · Clôturée @endif</p><p class="mt-4 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ Str::limit($post->body, 220) }}</p><x-publication-media :publication="$post" /><a href="{{ route('personnes.show', $post->author->profile->public_id) }}" class="mt-2 inline-flex min-h-11 items-center break-words text-[13px] font-bold">{{ $post->author->name }}</a><a href="{{ route('publications.show', $post) }}" class="flex min-h-11 items-center text-[13px] font-bold">Découvrir la publication →</a></article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="confiance" class="landing-container landing-section" aria-labelledby="confiance-titre">
        <div class="grid gap-6 rounded-card bg-ink p-6 text-white md:grid-cols-[1fr_1.3fr] md:p-10">
            <div><x-icon name="shield" :size="32" class="mb-4 text-accent" /><h2 id="confiance-titre" class="text-[27px] font-extrabold leading-tight">Des relations qui se construisent avec confiance.</h2><p class="mt-4 text-[14px] leading-relaxed text-white/85">Vous choisissez ce que vous partagez et les personnes avec qui vous échangez.</p></div>
            <div class="flex flex-col gap-5">
                @foreach ([['Votre parcours compte', 'Une expérience en atelier, une formation ou une réalisation personnelle peut montrer votre savoir-faire.'], ['Des repères pour faire connaissance', 'GAMAD peut vérifier une identité après un contrôle humain. Une expérience peut être confirmée par une personne identifiée. Chaque mention précise sa portée.'], ['Le contact commence par un accord', 'Une demande de relation peut être acceptée ou déclinée. Votre téléphone et votre e-mail restent privés.']] as [$title, $description])<div class="border-l-2 border-accent pl-4"><h3 class="text-[15px] font-extrabold">{{ $title }}</h3><p class="mt-1 text-[13px] leading-relaxed text-white/85">{{ $description }}</p></div>@endforeach
            </div>
        </div>
    </section>
    <section class="landing-container flex flex-col items-center gap-4 pb-14 pt-4 text-center" aria-label="Rejoindre GAMAD"><h2 class="landing-section-title">Faites votre place sur GAMAD.</h2><p class="max-w-xl text-[15px] leading-relaxed text-muted">Un savoir-faire à montrer, une personne à rencontrer, une occasion à saisir.</p><x-button :href="route('register')" variant="accent">Créer mon compte</x-button><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold">Déjà inscrit ? Se connecter</a></section>
</x-layouts.landing>
