@php
    $user = $profile->user;
    $since = $user->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('F Y');
    $empty = $profile->offeredSkills->isEmpty() && $profile->wantedSkills->isEmpty();
    $place = collect([$profile->city?->label(), $profile->country?->name])->filter()->implode(', ');
@endphp

<x-layouts.app :title="$user->name">
    <div class="flex flex-col gap-3.5">
        <header class="-mx-4 -mt-4 flex flex-col gap-3.5 border-b border-line bg-card px-4 pb-5 pt-3 md:mx-0 md:mt-0 md:rounded-card md:border md:p-6">
            <a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink">
                <x-icon name="back" :size="18" />Carrefour Personnes
            </a>

            <div class="flex items-center gap-3.5">
                <x-avatar :profile="$profile" :size="72" ring />
                <div class="flex min-w-0 flex-col gap-1">
                    <h1 class="break-words text-[24px] font-extrabold leading-tight tracking-[-0.3px]">{{ $user->name }}</h1>
                    @if ($place !== '')
                        <span class="flex items-center gap-1 text-[13px] text-muted"><x-icon name="pin" :size="14" />{{ $place }}</span>
                    @endif
                    <span class="text-[12px] text-muted">Sur GAMAD depuis {{ $since }}</span>
                </div>
            </div>

            @if (filled($profile->bio))
                <p class="whitespace-pre-line break-words text-[15px] leading-relaxed md:max-w-3xl">{{ $profile->bio }}</p>
            @endif

            @foreach ($inviteZumras as $inviteZumra)
                <x-button :href="route('zumras.invite', [$inviteZumra, 'personne' => $profile->public_id])" variant="secondary" class="md:self-start md:px-8">{{ $inviteZumras->count() === 1 ? 'Inviter dans ma ZUMRA' : 'Inviter dans '.$inviteZumra->name }}</x-button>
            @endforeach

            @if ($isOwner)
                <x-button :href="route('espace.profil')" variant="secondary" class="md:self-start md:px-8">Modifier mon profil</x-button>
                @unless ($profile->isPublic())
                    <p class="text-[13px] text-muted">Votre profil n’est visible que des personnes inscrites.</p>
                @endunless
            @endif
        </header>

        <nav aria-label="Onglets du profil" class="-mx-4 flex border-b border-line bg-card px-2 md:mx-0 md:rounded-card md:border">
            <a href="{{ route('personnes.show', $profile->public_id) }}" aria-current="page"
               class="flex min-h-12 items-center border-b-[3px] border-accent px-3 text-[14px] font-extrabold text-ink no-underline hover:text-ink">Apporte · Apprend</a>
        </nav>

        {{-- Ordinateur : les cartes se rangent sur deux colonnes. --}}
        <div class="flex flex-col gap-3.5 lg:block lg:columns-2 lg:gap-6 lg:*:mb-4 lg:*:break-inside-avoid">
        @if ($empty)
            <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                @if ($isOwner)
                    <h2 class="text-[17px] font-extrabold">Votre profil est encore vide.</h2>
                    <p class="text-muted">Dites ce que vous pouvez apporter et ce que vous voulez apprendre : d’autres personnes vous trouveront.</p>
                    <x-button :href="route('espace.profil')">Compléter mon profil</x-button>
                @else
                    <p class="text-muted">{{ $user->name }} n’a pas encore indiqué de capacité à apporter ni à apprendre.</p>
                @endif
            </section>
        @else
            <section aria-labelledby="apporte" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-[10px] bg-type-knowledge-bg text-type-knowledge-fg"><x-icon name="plus" :size="18" /></span>
                    <h2 id="apporte" class="text-[17px] font-extrabold">Je peux apporter</h2>
                </div>
                @if ($profile->offeredSkills->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($profile->offeredSkills as $skill)
                            <li><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}"
                                   class="inline-flex min-h-11 items-center rounded-chip bg-type-knowledge-bg px-3.5 text-[14px] font-bold text-type-knowledge-fg no-underline hover:text-type-knowledge-fg hover:underline">{{ $skill->name }}</a></li>
                        @endforeach
                    </ul>
                    <p class="text-[13px] text-muted">Touchez une capacité pour trouver d’autres personnes qui l’apportent.</p>
                @else
                    <p class="text-[14px] text-muted">Rien d’indiqué pour le moment.</p>
                @endif
            </section>

            <section aria-labelledby="apprend" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-[10px] bg-type-learning-bg text-type-learning-fg"><x-icon name="book" :size="18" /></span>
                    <h2 id="apprend" class="text-[17px] font-extrabold">Je veux apprendre</h2>
                </div>
                @if ($profile->wantedSkills->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($profile->wantedSkills as $skill)
                            <li><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'wanted']) }}"
                                   class="inline-flex min-h-11 items-center rounded-chip bg-type-learning-bg px-3.5 text-[14px] font-bold text-type-learning-fg no-underline hover:text-type-learning-fg hover:underline">{{ $skill->name }}</a></li>
                        @endforeach
                    </ul>
                    <p class="text-[13px] text-muted">Touchez une capacité pour trouver d’autres personnes qui veulent l’apprendre.</p>
                @else
                    <p class="text-[14px] text-muted">Rien d’indiqué pour le moment.</p>
                @endif
            </section>
        @endif

        <livewire:profile-gratitudes :profile="$profile" />

        @if ($profile->domains->isNotEmpty())
            <section aria-labelledby="domaines" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <h2 id="domaines" class="text-[15px] font-extrabold">Domaines qui l’intéressent</h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($profile->domains as $domain)
                        <li class="rounded-chip border border-line bg-ground px-3 py-1.5 text-[13px] font-semibold">{{ $domain->name }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
        </div>
    </div>
</x-layouts.app>
