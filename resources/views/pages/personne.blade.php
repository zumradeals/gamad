@php
    $user = $profile->user;
    $since = $user->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('F Y');
    $empty = $profile->offeredSkills->isEmpty() && $profile->wantedSkills->isEmpty();
    $place = collect([$profile->city?->label(), $profile->country?->name])->filter()->implode(', ');
@endphp

<x-layouts.app :title="$user->name">
    <div class="flex min-w-0 flex-col gap-5">
        <a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink">
            <x-icon name="back" :size="18" />Carrefour Personnes
        </a>

        <header class="overflow-hidden rounded-card border border-line bg-card">
            @if ($cover)
                <a href="{{ route('publications.show', $cover) }}" class="block" aria-label="Découvrir la publication de couverture">
                    <img src="{{ route('publications.media', $cover) }}" alt="Couverture choisie par {{ $user->name }}" fetchpriority="high" class="h-36 w-full object-cover sm:h-48 lg:h-56">
                </a>
            @else
                <div aria-hidden="true" class="relative h-28 overflow-hidden bg-ink sm:h-36 lg:h-40">
                    <div class="absolute -right-8 -top-24 size-80 rounded-full border-[32px] border-white/10"></div>
                    <div class="absolute bottom-0 right-24 h-2 w-28 rounded-t-button bg-accent"></div>
                    <div class="absolute -bottom-28 right-60 size-60 rounded-full border border-white/10"></div>
                </div>
            @endif
            <div class="relative px-5 pb-2 md:px-7">
                <div class="-mt-12 mb-4 inline-flex rounded-full border-4 border-card bg-card">
                    <x-avatar :profile="$profile" :size="104" />
                </div>
                <div class="flex min-w-0 flex-col gap-5 lg:flex-row lg:items-start lg:justify-between lg:gap-7">
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <h1 class="break-words text-[28px] font-extrabold leading-tight tracking-[-0.5px] md:text-[32px]">{{ $user->name }}</h1>
                        @if ($profile->headline)<p class="break-words text-[17px] font-bold leading-snug md:text-[19px]">{{ $profile->headline }}</p>@endif
                        @if ($place !== '')<p class="flex items-start gap-1.5 text-[13px] text-muted"><x-icon name="pin" :size="16" class="mt-0.5 shrink-0" /><span>{{ $place }}</span></p>@endif
                        @if (filled($profile->bio))<p class="mt-1 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $profile->bio }}</p>@endif
                        @if ($profile->offeredSkills->isNotEmpty())
                            <ul aria-label="Aperçu des capacités" class="mt-1 flex flex-wrap gap-2">
                                @foreach ($profile->offeredSkills->take(3) as $skill)<li class="max-w-full break-words rounded-chip bg-ground px-3 py-1.5 text-[12px] font-semibold">{{ $skill->name }}</li>@endforeach
                            </ul>
                        @endif
                        <p class="text-[12px] text-muted">Sur GAMAD depuis {{ $since }}</p>
                        @if ($contributorBadge)<span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-call-bg px-3 py-1 text-[12px] font-bold text-type-call-fg" data-testid="badge-contributeur"><x-icon name="check" :size="14" :stroke="3" />Contributeur</span>@endif
                    </div>
                    <div id="contact" class="flex min-w-0 scroll-mt-44 flex-col gap-3 lg:w-[340px] lg:shrink-0">
                        @if ($isOwner)
                            <x-button :href="route('espace.profil')" variant="secondary"><x-icon name="edit" :size="18" />Modifier mon profil</x-button>
                            @unless ($profile->isPublic())<p class="text-[13px] text-muted">Votre profil n’est visible que des personnes inscrites.</p>@endunless
                        @else
                            <livewire:person-contact :profile="$profile" />
                        @endif
                        <details class="rounded-button border border-line px-3">
                            <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-semibold"><x-icon name="external" :size="16" />Partager ce profil</summary>
                            <label for="profile-link" class="block text-[12px] text-muted">Copiez ce lien pour partager le profil.</label>
                            <input id="profile-link" type="url" readonly value="{{ route('personnes.show', $profile->public_id) }}" class="my-2 min-h-11 w-full min-w-0 rounded-button border border-chip-line bg-ground px-3 text-[13px]" onclick="this.select()">
                            @unless ($profile->isPublic())<p class="pb-3 text-[12px] text-muted">L’accès reste réservé aux personnes inscrites.</p>@endunless
                        </details>
                        @foreach ($inviteZumras as $inviteZumra)<x-button :href="route('zumras.invite', [$inviteZumra, 'personne' => $profile->public_id])" variant="secondary">{{ $inviteZumras->count() === 1 ? 'Inviter dans ma ZUMRA' : 'Inviter dans '.$inviteZumra->name }}</x-button>@endforeach
                    </div>
                </div>
                <nav aria-label="Onglets du profil" class="mt-5 grid grid-cols-4 border-t border-line text-center">
                    @foreach (['savoir-faire' => 'Savoir-faire', 'realisations' => 'Réalisations', 'parcours' => 'Parcours', 'publications' => 'Publications'] as $anchor => $label)
                        <a href="#{{ $anchor }}" class="flex min-h-14 items-center justify-center border-b-[3px] px-1 text-[11px] font-bold text-ink no-underline hover:border-accent hover:text-ink sm:px-3 sm:text-[14px] {{ $loop->first ? 'border-accent' : 'border-transparent' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <div class="grid min-w-0 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="flex min-w-0 flex-col gap-5">
                @include('pages.partials-person-showcase')
            </div>
            <aside aria-label="Faire connaissance" class="flex min-w-0 flex-col gap-5">
                @unless ($isOwner)
                    <section class="rounded-card border border-line bg-card p-5">
                        <h2 class="flex items-center gap-2 text-[18px] font-extrabold"><x-icon name="chat" :size="22" />Faisons connaissance</h2>
                        <p class="mt-3 text-[14px] leading-relaxed">Une idée, un besoin ou un savoir à partager ? Présentez votre envie à {{ $user->name }}.</p>
                        <a href="#contact" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[14px] font-bold">Voir les possibilités de contact<x-icon name="arrow-right" :size="16" /></a>
                        <p class="mt-1 text-[12px] text-muted">Une nouvelle relation commence par un accord mutuel.</p>
                    </section>
                @endunless
                @if ($currentOffer)
                    <section class="rounded-card border border-line bg-card p-5">
                        <h2 class="text-[18px] font-extrabold">En ce moment</h2>
                        <x-publication-media :publication="$currentOffer" />
                        <p class="mt-3 text-[13px] font-bold text-type-offer-fg">{{ $currentOffer->displayLabel() }}</p>
                        <p class="mt-2 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ Str::limit($currentOffer->body, 220) }}</p>
                        <a href="{{ route('publications.show', $currentOffer) }}" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-bold">Découvrir l’offre →</a>
                    </section>
                @endif
                @if ($profile->wantedSkills->isNotEmpty())
                    <section aria-labelledby="apprend" class="rounded-card border border-line bg-card p-5">
                        <h2 id="apprend" class="flex items-center gap-2 text-[18px] font-extrabold"><x-icon name="learn" :size="22" />Je veux apprendre</h2>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($profile->wantedSkills as $skill)<li class="max-w-full"><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'wanted']) }}" class="inline-flex min-h-11 items-center break-words rounded-button bg-type-learning-bg px-3 py-2 text-[13px] font-semibold text-type-learning-fg no-underline">{{ $skill->name }}</a></li>@endforeach
                        </ul>
                        <p class="mt-3 text-[12px] text-muted">Touchez une capacité pour trouver d’autres personnes qui veulent l’apprendre.</p>
                    </section>
                @endif
                @if ($profile->domains->isNotEmpty())
                    <section aria-labelledby="domaines" class="rounded-card border border-line bg-card p-5">
                        <h2 id="domaines" class="text-[16px] font-extrabold">Domaines qui l’intéressent</h2>
                        <ul class="mt-3 flex flex-wrap gap-2">@foreach ($profile->domains as $domain)<li class="max-w-full break-words rounded-chip border border-line bg-ground px-3 py-1.5 text-[13px] font-semibold">{{ $domain->name }}</li>@endforeach</ul>
                    </section>
                @endif
                <livewire:profile-gratitudes :profile="$profile" />
            </aside>
        </div>
    </div>
</x-layouts.app>
