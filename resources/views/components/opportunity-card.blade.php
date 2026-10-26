@props(['opportunity', 'saved' => false, 'canAct' => false, 'compact' => false])

@php
    $company = $opportunity->company;
    $deadline = $opportunity->deadline?->locale('fr')->translatedFormat('j M Y');
    $place = $opportunity->city?->label();
@endphp
<article {{ $attributes->class(['opportunity-card relative min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 gap-3 sm:gap-4">
        <x-company-logo :company="$company" :size="64" class="max-sm:!size-14" />
        <div class="min-w-0 flex-1 space-y-1">
            <h3 class="min-w-0 break-words text-[17px] font-extrabold leading-tight sm:text-[18px]"><a href="{{ route('opportunites.show', $opportunity) }}" class="-my-2 inline-flex min-h-11 items-center text-ink no-underline hover:underline max-sm:after:absolute max-sm:after:inset-0 max-sm:after:content-['']">{{ $opportunity->title }}</a></h3>
            <p class="-mt-1 flex min-w-0 flex-wrap items-center gap-x-1.5 text-[14px] font-semibold"><a href="{{ route('entreprises.show', $company) }}" class="relative z-[1] -my-2 inline-flex min-h-11 items-center break-words text-ink no-underline hover:underline">{{ $company->name }}</a>
                @if ($company->verified())<span class="inline-flex size-[16px] items-center justify-center rounded-full bg-link text-white" title="Immatriculation vérifiée par GAMAD"><x-icon name="check" :size="11" /><span class="sr-only">Immatriculation vérifiée par GAMAD</span></span>@endif</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[13px] text-muted">
                @if ($place)<span class="inline-flex items-center gap-1"><x-icon name="pin" :size="15" />{{ $place }}</span>@endif
                <span>{{ $opportunity->published_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</span>
                @if ($deadline)<span>· avant le {{ $deadline }}</span>@endif
            </p>
            @unless ($compact)<p class="line-clamp-2 break-words text-[14px] leading-snug text-muted">{{ $opportunity->firstLine(220) }}</p>@endunless
            <span class="inline-flex rounded-chip px-3 py-1 text-[12px] font-bold sm:hidden {{ $opportunity->kind->badgeClasses() }}">{{ $opportunity->kind->label() }}</span>
        </div>
        <div class="hidden shrink-0 flex-col items-end justify-between gap-3 sm:flex sm:w-[170px]">
            <span class="rounded-chip px-3 py-1 text-[12px] font-bold {{ $opportunity->kind->badgeClasses() }}">{{ $opportunity->kind->label() }}</span>
            <div class="flex flex-col items-end gap-1">
                <x-button :href="route('opportunites.show', $opportunity)" variant="secondary" class="!min-h-11 !border-link !px-4 !text-[13px] !text-link">Voir l’opportunité <x-icon name="next" :size="16" class="ml-1.5" /></x-button>
                @if ($canAct)
                    <button type="button" wire:click="toggleSave('{{ $opportunity->id }}')" aria-pressed="{{ $saved ? 'true' : 'false' }}" class="inline-flex min-h-11 items-center gap-1.5 px-2 text-[12px] font-bold text-link"><x-icon name="book" :size="15" />{{ $saved ? 'Sauvegardée' : 'Sauvegarder' }}</button>
                @endif
            </div>
        </div>
    </div>
    @if ($canAct)
        <button type="button" wire:click="toggleSave('{{ $opportunity->id }}')" aria-pressed="{{ $saved ? 'true' : 'false' }}" class="relative z-[1] -mb-2 mt-1 inline-flex min-h-11 items-center gap-1.5 px-1 text-[13px] font-bold text-link sm:hidden"><x-icon name="book" :size="15" />{{ $saved ? 'Sauvegardée' : 'Sauvegarder' }}</button>
    @endif
</article>
