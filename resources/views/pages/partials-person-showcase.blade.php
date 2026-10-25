@php
    $worksTitle = fn ($post) => \Illuminate\Support\Str::limit(trim(strtok(trim($post->body), "\n")), 70);
    $worksText = fn ($post) => \Illuminate\Support\Str::limit(trim(preg_replace('/^[^\n]*\n?/', '', trim($post->body)) ?: trim($post->body)), 110);
    $timeline = collect(($companyExperiences ?? collect())->map(fn ($e) => ['company' => $e])->all())
        ->merge(collect($profile->experiences ?? [])->map(fn ($e) => ['own' => $e]))->values();
@endphp

<section id="savoir-faire" aria-labelledby="apporte" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
    <div class="flex items-start gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-type-knowledge-bg text-type-knowledge-fg"><x-icon name="tasks" :size="26" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="apporte" class="text-[20px] font-extrabold">Ce que je peux faire pour vous</h2>
            @if ($profile->offeredSkills->isNotEmpty())<p class="mt-1 text-[14px] text-muted">Je peux apporter mon savoir-faire dans les domaines suivants :</p>@endif
        </div>
    </div>
    @if ($profile->offeredSkills->isNotEmpty())
        <ul aria-label="Capacités proposées" class="mt-4 flex flex-wrap gap-2">
            @foreach ($profile->offeredSkills as $skill)<li class="max-w-full"><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}" class="inline-flex min-h-11 items-center break-words rounded-chip bg-type-knowledge-bg px-4 py-2 text-[13px] font-semibold text-type-knowledge-fg no-underline hover:underline">{{ $skill->name }}</a></li>@endforeach
        </ul>
        <p class="mt-2 text-[12px] text-muted">Touchez une capacité pour trouver d’autres personnes qui l’apportent.</p>
        @php($detailed = $profile->offeredSkills->filter(fn ($skill) => collect($profile->skill_details ?? [])->firstWhere('skill', $skill->name)))
        @if ($detailed->isNotEmpty())
            <div class="mt-4 space-y-3 border-t border-line-soft pt-4">
                @foreach ($detailed as $skill)
                    @php($detail = collect($profile->skill_details ?? [])->firstWhere('skill', $skill->name))
                    <article class="min-w-0">
                        <h3 class="break-words text-[15px] font-extrabold">{{ $skill->name }}</h3>
                        <p class="mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $detail['description'] }}</p>
                        @if ($post = $visibleReferences->get($detail['publication_id'] ?? ''))<a class="inline-flex min-h-11 items-center text-[13px] font-bold" href="{{ route('publications.show', $post) }}">Voir un exemple concret →</a>@endif
                    </article>
                @endforeach
            </div>
        @endif
    @elseif ($isOwner)
        <p class="mt-4 text-[15px] font-bold">{{ $empty && ! $profile->introduction && ! count($profile->experiences ?? []) && $personalPosts->isEmpty() ? 'Votre profil est encore vide.' : 'Qu’aimeriez-vous apporter ou apprendre ?' }}</p>
        <p class="mt-2 text-[14px] text-muted">Un métier, un savoir appris en atelier, une expérience de vie : dites ce que vous pouvez apporter. Aucun diplôme n’est nécessaire.</p>
        <x-button :href="route('espace.profil')" variant="secondary" class="mt-3">Compléter mon profil</x-button>
    @else
        <p class="mt-4 text-[14px] text-muted">{{ $empty ? $user->name.' n’a pas encore indiqué de capacité à apporter ni à apprendre.' : $user->name.' n’a pas encore indiqué de capacité à apporter.' }}</p>
    @endif
</section>

<section id="realisations" aria-labelledby="realisations-title" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6" x-data="{ all: false }">
    <div class="flex items-start gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-yellow text-ink" style="background-color: #fdf3d4"><x-icon name="project" :size="26" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="realisations-title" class="text-[20px] font-extrabold">{{ $showcasePosts->isNotEmpty() && $showcasePosts->contains(fn ($post) => $post->type !== \App\Enums\PublicationType::PersonalWork) ? 'À la une' : 'Mes réalisations' }}</h2>
            @if ($showcasePosts->isNotEmpty())<p class="mt-1 text-[14px] text-muted">Quelques exemples concrets de ce que j’ai réalisé.</p>@endif
        </div>
    </div>
    @if ($showcasePosts->isNotEmpty())
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($showcasePosts as $post)
                <article class="flex min-w-0 flex-col overflow-hidden rounded-button border border-line">
                    <div class="[&_img]:h-32 [&_img]:w-full [&_img]:rounded-none [&_img]:object-cover [&_video]:max-h-40"><x-publication-media :publication="$post" /></div>
                    <div class="flex flex-1 flex-col gap-1 p-3">
                        <h3 class="break-words text-[14px] font-extrabold leading-snug">{{ $worksTitle($post) }}</h3>
                        <p class="break-words text-[13px] leading-snug text-muted">{{ $worksText($post) }}</p>
                        @if ($post->skills->isNotEmpty())<ul class="mt-1 flex flex-wrap gap-1.5">@foreach ($post->skills->take(2) as $skill)<li class="max-w-full break-words rounded-chip bg-soft-blue px-2.5 py-1 text-[11px] font-semibold text-link">{{ $skill->name }}</li>@endforeach</ul>@endif
                        <a href="{{ route('publications.show', $post) }}" class="mt-auto inline-flex min-h-11 items-center pt-1 text-[13px] font-bold">{{ $post->type === \App\Enums\PublicationType::PersonalWork ? 'Voir la réalisation →' : 'Découvrir la publication →' }}</a>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($moreWorks->isNotEmpty())
            <div x-show="all" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($moreWorks as $post)
                    <article class="flex min-w-0 flex-col overflow-hidden rounded-button border border-line">
                        <div class="[&_img]:h-32 [&_img]:w-full [&_img]:rounded-none [&_img]:object-cover [&_video]:max-h-40"><x-publication-media :publication="$post" /></div>
                        <div class="flex flex-1 flex-col gap-1 p-3">
                            <h3 class="break-words text-[14px] font-extrabold leading-snug">{{ $worksTitle($post) }}</h3>
                            <p class="break-words text-[13px] leading-snug text-muted">{{ $worksText($post) }}</p>
                            <a href="{{ route('publications.show', $post) }}" class="mt-auto inline-flex min-h-11 items-center pt-1 text-[13px] font-bold">Voir la réalisation →</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all.toString()" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link"><span x-text="all ? 'Réduire' : 'Voir toutes les réalisations ({{ $showcasePosts->count() + $moreWorks->count() }})'">Voir toutes les réalisations ({{ $showcasePosts->count() + $moreWorks->count() }})</span></button>
        @endif
        <p class="mt-3 text-[12px] text-muted">Publications et réalisations déclarées par {{ $user->name }}.</p>
    @else
        <p class="mt-4 text-[14px] text-muted">Aucune réalisation à afficher pour le moment.</p>
        @if ($isOwner)<p class="mt-2 text-[14px] text-muted">Montrez ce que vous avez fait, même un premier travail ou un apprentissage.</p><x-button :href="route('agir.publier', 'ma-realisation')" variant="secondary" class="mt-3">Partager une réalisation</x-button>@endif
    @endif
</section>

<section id="parcours" aria-labelledby="parcours-title" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6" x-data="{ all: false }">
    <div class="flex items-start gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="community" :size="26" /></span>
        <h2 id="parcours-title" class="min-w-0 flex-1 text-[20px] font-extrabold">Mon parcours et mes expériences</h2>
    </div>
    @if ($timeline->isNotEmpty())
        <ol class="mt-5 ml-1">
            @foreach ($timeline as $item)
                <li @if ($loop->index >= 3) x-show="all" x-cloak @endif class="relative grid gap-x-4 pb-6 pl-6 last:pb-0 sm:grid-cols-[110px_minmax(0,1fr)]">
                    <span aria-hidden="true" class="absolute left-0 top-1.5 size-3 rounded-full bg-link"></span>
                    @if (! $loop->last)<span aria-hidden="true" class="absolute bottom-0 left-[5px] top-5 w-0.5 bg-line"></span>@endif
                    @if (isset($item['company']))
                        @php($experience = $item['company'])
                        <p class="text-[13px] text-muted">{{ $experience->period }}</p>
                        <div class="min-w-0">
                            <h3 class="break-words text-[15px] font-extrabold">{{ $experience->title }}</h3>
                            <a class="inline-flex min-h-11 items-center gap-1 text-[13px] font-bold" href="{{ route('entreprises.show', $experience->company) }}">{{ $experience->company->name }} →</a>
                            <p class="text-[12px] text-muted">{{ $experience->relationshipLabel() }}</p>
                            @if ($experience->description)<p class="mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed text-muted">{{ $experience->description }}</p>@endif
                            @if ($experience->confirmed())
                                <span class="mt-1 inline-flex items-center gap-1 rounded-chip bg-type-knowledge-bg px-3 py-1.5 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="16"/> Confirmée par l’entreprise</span>
                            @else
                                <span class="mt-1 inline-flex rounded-chip bg-ground px-3 py-1.5 text-[12px] font-bold text-muted">{{ $experience->status === 'pending' ? 'Non encore confirmée par l’entreprise' : 'Non confirmée par l’entreprise' }}</span>
                            @endif
                        </div>
                    @else
                        @php($experience = $item['own'])
                        <p class="text-[13px] text-muted">{{ $experience['period'] ?? '' }}</p>
                        <div class="min-w-0">
                            <h3 class="break-words text-[15px] font-extrabold">{{ $experience['title'] }}</h3>
                            <p class="mt-0.5 break-words text-[12px] font-semibold text-muted">{{ ['personal' => 'Personnelle', 'professional' => 'Professionnelle', 'volunteer' => 'Associative ou bénévole', 'learning' => 'Apprentissage'][$experience['kind']] ?? '' }}</p>
                            @if ($experience['description'] ?? '')<p class="mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed text-muted">{{ $experience['description'] }}</p>@endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
        @if ($timeline->count() > 3)<button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all.toString()" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link"><span x-text="all ? 'Réduire' : 'Voir tout mon parcours ({{ $timeline->count() }})'">Voir tout mon parcours ({{ $timeline->count() }})</span></button>@endif
        @if (count($profile->experiences ?? []))<p class="mt-3 text-[12px] text-muted">Expériences déclarées par {{ $user->name }}.</p>@endif
    @else
        <p class="mt-4 text-[14px] text-muted">Aucune expérience renseignée pour le moment.</p>
        @if ($isOwner)<a href="{{ route('espace.profil') }}#profile-experiences" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Raconter mon parcours →</a>@endif
    @endif
    @if ($isOwner)
        <a href="{{ route('espace.company-experiences') }}" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-extrabold text-link">Gérer mes références professionnelles <x-icon name="arrow-right" :size="16"/></a>
    @endif
</section>

@if ($profile->introduction)
    <section class="rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">Ce qui me fait avancer</h2><p class="mt-3 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $profile->introduction }}</p></section>
@endif

@if (($confirmedWorks ?? collect())->isNotEmpty())
    <section class="flex flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">Expériences confirmées</h2>@foreach ($confirmedWorks as $reference)<x-work-reference :reference="$reference" />@endforeach</section>
@endif
