@php
    $user = $profile->user;
    $since = $user->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('F Y');
    $empty = $profile->offeredSkills->isEmpty() && $profile->wantedSkills->isEmpty();
    $place = collect([$profile->city?->label(), $profile->country?->name])->filter()->implode(', ');
    $tabs = ['savoir-faire' => 'Savoir-faire', 'realisations' => 'Réalisations', 'parcours' => 'Parcours', 'publications' => 'Publications'];
@endphp

<x-layouts.app :title="$user->name" extra-wide>
    <div class="flex min-w-0 flex-col gap-5">
        <a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink">
            <x-icon name="back" :size="18" />Retour aux personnes
        </a>

        <header class="overflow-hidden rounded-card border border-line bg-card">
            @if ($cover)
                <a href="{{ route('publications.show', $cover) }}" class="block" aria-label="Découvrir la publication de couverture">
                    <img src="{{ route('publications.media', $cover) }}" alt="Couverture choisie par {{ $user->name }}" fetchpriority="high" class="h-40 w-full object-cover sm:h-52 lg:h-60">
                </a>
            @else
                <div aria-hidden="true" class="relative h-40 overflow-hidden bg-gradient-to-r from-ink via-[#123a57] to-[#1c5a82] sm:h-52 lg:h-60">
                    <div class="absolute -right-8 -top-24 size-96 rounded-full border-[36px] border-white/10"></div>
                    <div class="absolute bottom-0 right-24 h-2 w-32 rounded-t-button bg-accent"></div>
                    <div class="absolute -bottom-32 right-72 size-72 rounded-full border border-white/10"></div>
                    <div class="absolute -left-16 -top-20 size-64 rounded-full border border-white/10"></div>
                </div>
            @endif
            <div class="relative px-5 md:px-8">
                <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:gap-8">
                    <div class="-mt-16 inline-flex shrink-0 self-start rounded-full border-4 border-card bg-card lg:-mt-20">
                        <x-avatar :profile="$profile" :size="128" />
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2 pb-2 lg:pt-4">
                        <h1 class="flex flex-wrap items-center gap-x-3 break-words text-[28px] font-extrabold leading-tight tracking-[-0.5px] md:text-[32px]">{{ $user->name }}
                            @if ($verifiedIdentity ?? null)<span class="inline-flex size-6 items-center justify-center rounded-full bg-link text-white" title="Identité personnelle vérifiée par GAMAD"><x-icon name="check" :size="15" :stroke="3" /><span class="sr-only">Identité personnelle vérifiée par GAMAD</span></span>@endif
                        </h1>
                        <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[14px] text-muted">
                            @if ($place !== '')<span class="inline-flex items-center gap-1.5"><x-icon name="pin" :size="16" class="shrink-0" />{{ $place }}</span>@endif
                            <span class="inline-flex items-center gap-1.5"><x-icon name="tasks" :size="16" class="shrink-0" />Membre depuis {{ $since }}</span>
                        </p>
                        @if ($verifiedIdentity ?? null)
                            <details class="self-start"><summary class="inline-flex min-h-11 cursor-pointer items-center text-[12px] font-bold text-link">Identité vérifiée : en savoir plus</summary><p class="max-w-lg text-[12px] text-muted">Identité contrôlée le {{ $verifiedIdentity->verified_at->format('d/m/Y') }}, valable jusqu’au {{ $verifiedIdentity->expires_at->format('d/m/Y') }}. Cette vérification individuelle facultative ne confirme aucune expérience auprès d'une entreprise. Les expériences d'entreprise ont leur propre confirmation.</p></details>
                        @endif
                        @if ($profile->headline)<p class="break-words text-[17px] font-bold leading-snug md:text-[19px]">{{ $profile->headline }}</p>@endif
                        @if (filled($profile->bio))<p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $profile->bio }}</p>@endif
                        @if ($profile->offeredSkills->isNotEmpty())
                            <ul aria-label="Aperçu des capacités" class="mt-1 flex flex-wrap gap-2">
                                @foreach ($profile->offeredSkills->take(3) as $skill)<li class="max-w-full break-words rounded-chip bg-soft-blue px-3 py-1.5 text-[12px] font-semibold text-link">{{ $skill->name }}</li>@endforeach
                                @if ($profile->offeredSkills->count() > 3)<li class="rounded-chip bg-ground px-3 py-1.5 text-[12px] font-semibold text-muted">+{{ $profile->offeredSkills->count() - 3 }}</li>@endif
                            </ul>
                        @endif
                        @if ($contributorBadge)<span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-call-bg px-3 py-1 text-[12px] font-bold text-type-call-fg" data-testid="badge-contributeur"><x-icon name="check" :size="14" :stroke="3" />Contributeur</span>@endif
                    </div>
                    <div id="contact" class="flex min-w-0 scroll-mt-44 flex-col gap-4 pb-2 lg:w-[400px] lg:shrink-0 lg:pt-5">
                        @if ($isOwner)
                            <div class="flex flex-wrap items-center gap-2">
                                <x-button :href="route('espace.profil')" variant="accent"><x-icon name="edit" :size="18" class="mr-2" />Modifier mon profil</x-button>
                                <x-profile-share :profile="$profile" label="Partager mon profil" />
                                <x-button :href="route('espace')" variant="secondary">Mon espace</x-button>
                            </div>
                            @unless ($profile->isPublic())<p class="text-[13px] text-muted">Votre profil n’est visible que des personnes inscrites.</p>@endunless
                        @else
                            <livewire:person-contact :profile="$profile" />
                        @endif
                        @if ($counts !== [])
                            <dl class="grid divide-x divide-line-soft text-center" style="grid-template-columns: repeat({{ count($counts) }}, minmax(0, 1fr))">
                                @foreach ($counts as $label => $total)
                                    <div class="px-2"><dd class="text-[22px] font-extrabold leading-none">{{ $total }}</dd><dt class="mt-1 text-[13px] text-muted">{{ $label }}</dt></div>
                                @endforeach
                            </dl>
                        @endif
                        @if ($isOwner)<a href="{{ route('espace.attestations') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Mes attestations individuelles (ancien dispositif) →</a>@endif
                        @foreach ($inviteZumras as $inviteZumra)<x-button :href="route('zumras.invite', [$inviteZumra, 'personne' => $profile->public_id])" variant="secondary">{{ $inviteZumras->count() === 1 ? 'Inviter dans ma ZUMRA' : 'Inviter dans '.$inviteZumra->name }}</x-button>@endforeach
                    </div>
                </div>
                <nav aria-label="Onglets du profil" class="mt-3 grid grid-cols-4 border-t border-line text-center"
                    x-data="{ active: 'savoir-faire' }"
                    x-init="const obs = new IntersectionObserver((entries) => { entries.forEach((entry) => { if (entry.isIntersecting) { active = entry.target.id; } }); }, { rootMargin: '-25% 0px -65% 0px' });
                        ['savoir-faire', 'realisations', 'parcours', 'publications'].forEach((id) => { const el = document.getElementById(id); if (el) { obs.observe(el); } });">
                    @foreach ($tabs as $anchor => $label)
                        <a href="#{{ $anchor }}" x-on:click="active = '{{ $anchor }}'" x-bind:aria-current="active === '{{ $anchor }}' ? 'true' : null"
                           x-bind:class="active === '{{ $anchor }}' ? 'border-link text-link' : 'border-transparent text-ink'"
                           class="flex min-h-14 items-center justify-center border-b-[3px] px-1 text-[11px] font-bold no-underline hover:text-link sm:px-3 sm:text-[14px] {{ $loop->first ? 'border-link text-link' : 'border-transparent text-ink' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <x-contribution-entry kind="person" :model="$user" :viewer="auth()->user()" />

        <div class="grid min-w-0 items-start gap-5 lg:grid-cols-2">
            <div class="flex min-w-0 flex-col gap-5">
                @include('pages.partials-person-showcase')
            </div>
            <div class="flex min-w-0 flex-col gap-5">
                @include('pages.partials-person-side')
                @include('pages.partials-person-publications')
                @include('pages.partials-person-interests')
            </div>
        </div>
    </div>
</x-layouts.app>
