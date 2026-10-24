<section id="savoir-faire" aria-labelledby="apporte" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 id="apporte" class="text-[20px] font-extrabold">Ce que je peux faire pour vous</h2>
        <span class="text-[12px] text-muted">Je peux apporter</span>
    </div>
    @if ($profile->offeredSkills->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($profile->offeredSkills as $skill)
                @php $detail = collect($profile->skill_details ?? [])->firstWhere('skill', $skill->name); @endphp
                <article class="flex min-w-0 flex-col rounded-button border border-line p-4">
                    <span class="mb-3 flex size-10 items-center justify-center rounded-button bg-type-knowledge-bg text-type-knowledge-fg"><x-icon name="tasks" :size="22" /></span>
                    <h3 class="break-words text-[15px] font-extrabold">{{ $skill->name }}</h3>
                    @if ($detail)<p class="mt-2 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $detail['description'] }}</p>@endif
                    @if ($post = $visibleReferences->get($detail['publication_id'] ?? ''))<a class="mt-2 inline-flex min-h-11 items-center text-[13px] font-bold" href="{{ route('publications.show', $post) }}">Voir un exemple concret →</a>@endif
                </article>
            @endforeach
        </div>
        <details class="mt-3">
            <summary class="min-h-11 cursor-pointer content-center text-[13px] font-semibold text-muted">Découvrir d’autres personnes avec ces capacités</summary>
            <p class="text-[12px] text-muted">Touchez une capacité pour trouver d’autres personnes qui l’apportent.</p>
            <ul class="mt-2 flex flex-wrap gap-2">@foreach ($profile->offeredSkills as $skill)<li class="max-w-full"><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}" class="inline-flex min-h-11 items-center break-words rounded-chip bg-type-knowledge-bg px-3 py-2 text-[13px] font-semibold text-type-knowledge-fg no-underline">{{ $skill->name }}</a></li>@endforeach</ul>
        </details>
    @elseif ($isOwner)
        <p class="text-[15px] font-bold">{{ $empty && ! $profile->introduction && ! count($profile->experiences ?? []) && $personalPosts->isEmpty() ? 'Votre profil est encore vide.' : 'Qu’aimeriez-vous apporter ou apprendre ?' }}</p>
        <p class="mt-2 text-[14px] text-muted">Un métier, un savoir appris en atelier, une expérience de vie : dites ce que vous pouvez apporter. Aucun diplôme n’est nécessaire.</p>
        <x-button :href="route('espace.profil')" variant="secondary" class="mt-3">Compléter mon profil</x-button>
    @else
        <p class="text-[14px] text-muted">{{ $empty ? $user->name.' n’a pas encore indiqué de capacité à apporter ni à apprendre.' : $user->name.' n’a pas encore indiqué de capacité à apporter.' }}</p>
    @endif
</section>

<section id="realisations" aria-labelledby="realisations-title" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
    <h2 id="realisations-title" class="text-[20px] font-extrabold">{{ $showcasePosts->isNotEmpty() && $showcasePosts->contains(fn ($post) => $post->type !== \App\Enums\PublicationType::PersonalWork) ? 'À la une' : 'Mes réalisations' }}</h2>
    @if ($showcasePosts->isNotEmpty())
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($showcasePosts as $post)
                <article class="flex min-w-0 flex-col overflow-hidden rounded-button border border-line">
                    <div class="px-3 [&_img]:max-h-64 [&_video]:max-h-64"><x-publication-media :publication="$post" /></div>
                    <div class="flex flex-1 flex-col px-4 pb-3 pt-3">
                        <p class="text-[12px] font-semibold text-muted">{{ $post->displayLabel() }} · {{ $post->created_at->format('d/m/Y') }}</p>
                        <p class="mt-2 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $post->body }}</p>
                        <a href="{{ route('publications.show', $post) }}" class="mt-auto inline-flex min-h-11 items-center pt-2 text-[13px] font-bold">{{ $post->type === \App\Enums\PublicationType::PersonalWork ? 'Voir la réalisation →' : 'Découvrir la publication →' }}</a>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mt-3 text-[12px] text-muted">Publications et réalisations déclarées par {{ $user->name }}.</p>
    @else
        <p class="mt-3 text-[14px] text-muted">Aucune réalisation à afficher pour le moment.</p>
        @if ($isOwner)<p class="mt-2 text-[14px] text-muted">Montrez ce que vous avez fait, même un premier travail ou un apprentissage.</p><x-button :href="route('agir.publier', 'ma-realisation')" variant="secondary" class="mt-3">Partager une réalisation</x-button>@endif
    @endif
</section>

<section id="parcours" aria-labelledby="parcours-title" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
    <h2 id="parcours-title" class="text-[20px] font-extrabold">Mon parcours et mes expériences</h2>
    @if (count($profile->experiences ?? []))
        <ol class="mt-5 ml-2 border-l-2 border-line">
            @foreach ($profile->experiences as $experience)
                <li class="relative pb-6 pl-6 last:pb-0">
                    <span aria-hidden="true" class="absolute -left-[7px] top-1.5 size-3 rounded-full bg-accent"></span>
                    <h3 class="break-words text-[16px] font-extrabold">{{ $experience['title'] }}</h3>
                    <p class="mt-1 break-words text-[12px] font-semibold text-muted">{{ ['personal' => 'Personnelle', 'professional' => 'Professionnelle', 'volunteer' => 'Associative ou bénévole', 'learning' => 'Apprentissage'][$experience['kind']] }}@if ($experience['period'] ?? '') · {{ $experience['period'] }}@endif</p>
                    @if ($experience['description'] ?? '')<p class="mt-2 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $experience['description'] }}</p>@endif
                </li>
            @endforeach
        </ol>
        <p class="mt-4 text-[12px] text-muted">Expériences déclarées par {{ $user->name }}.</p>
    @else
        <p class="mt-3 text-[14px] text-muted">Aucune expérience renseignée pour le moment.</p>
        @if ($isOwner)<a href="{{ route('espace.profil') }}#profile-experiences" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Raconter mon parcours →</a>@endif
    @endif
</section>

@if ($profile->introduction)
    <section class="rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">Ce qui me fait avancer</h2><p class="mt-3 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $profile->introduction }}</p></section>
@endif

@if (($confirmedWorks ?? collect())->isNotEmpty())
    <section class="flex flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">Expériences confirmées</h2>@foreach ($confirmedWorks as $reference)<x-work-reference :reference="$reference" />@endforeach</section>
@endif
