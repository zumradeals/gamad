@props(['company', 'followed' => false, 'own' => false, 'canAct' => false])

<article {{ $attributes->class(['company-discovery-card relative min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 flex-col gap-4 sm:flex-row">
        <div class="flex min-w-0 flex-1 gap-4">
            <a href="{{ route('entreprises.show', $company) }}" class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-card border border-line bg-soft-blue text-[22px] font-extrabold text-ink no-underline" aria-label="Voir la page de {{ $company->name }}">
                @if ($company->logo_path)
                    <img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="" loading="lazy" class="size-full bg-white object-contain">
                @else
                    {{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}
                @endif
            </a>
            <div class="min-w-0 flex-1 space-y-1.5">
                <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                    <a href="{{ route('entreprises.show', $company) }}" class="-my-2 inline-flex min-h-11 items-center break-words text-[18px] font-extrabold text-ink no-underline hover:underline">{{ $company->name }}</a>
                    {{-- Seules les entreprises vérifiées figurent dans l'annuaire : le badge reflète la vérification administrative réelle. --}}
                    @if ($company->verified())<span class="inline-flex size-[18px] items-center justify-center rounded-full bg-link text-white" title="Immatriculation vérifiée par GAMAD"><x-icon name="check" :size="12"/><span class="sr-only">Immatriculation vérifiée par GAMAD</span></span>@endif
                    @if ($own)<span class="rounded-chip bg-ground px-2 py-0.5 text-[12px] font-bold">Votre entreprise</span>@endif
                    @if ($followed)<span class="rounded-chip bg-soft-blue px-2 py-0.5 text-[12px] font-bold text-link">Suivie</span>@endif
                </div>
                <p class="break-words text-[15px] text-muted">{{ $company->sector }}</p>
                @if ($company->city)<p class="flex items-center gap-1 text-[14px]"><x-icon name="pin" :size="16" /> {{ $company->city }}</p>@endif
                @if ($company->summary)<p class="line-clamp-2 break-words text-[14px] leading-snug text-muted">{{ $company->summary }}</p>@endif
                @if ($company->opportunities->isNotEmpty())
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5 pt-1">
                        <span class="rounded-chip bg-type-learning-bg px-3 py-1 text-[12px] font-bold text-type-learning-fg">Recherche des compétences</span>
                        <span class="max-w-full rounded-chip bg-soft-blue px-3 py-1 text-[12px] font-semibold text-link [overflow-wrap:anywhere]">{{ $company->sector }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap items-start gap-2 sm:mt-8 sm:w-[150px] sm:flex-col sm:items-stretch">
            <x-button :href="route('entreprises.show', $company)" variant="secondary">Voir le profil</x-button>
            @if ($canAct && ! $own)
                <button type="button" wire:click="follow('{{ $company->id }}', {{ $followed ? 'false' : 'true' }})" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-button px-5 text-[15px] font-bold {{ $followed ? 'border border-line bg-card' : 'bg-accent text-ink' }}"><x-icon :name="$followed ? 'check' : 'plus'" :size="17"/>{{ $followed ? 'Suivie' : 'Suivre' }}</button>
                <div x-data="{ open: false }" class="relative sm:absolute sm:right-2 sm:top-2" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Autres actions pour {{ $company->name }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-button border border-line text-[18px] font-extrabold sm:border-0">⋯</button>
                    <div x-show="open" x-cloak role="menu" class="absolute right-0 z-20 mt-1 w-60 rounded-card border border-line bg-card p-1 shadow-lg">
                        <a role="menuitem" href="{{ route('entreprises.contact', $company) }}" class="flex min-h-11 items-center px-3 text-[14px] font-semibold">Contacter l’entreprise</a>
                        <a role="menuitem" href="{{ route('entreprises.reports.create', $company) }}" class="flex min-h-11 items-center px-3 text-[14px] font-semibold">Signaler cette entreprise</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</article>
