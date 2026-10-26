@props(['opportunity', 'saved' => false, 'canAct' => false, 'compact' => false])

@php
    $company = $opportunity->company;
    $deadline = $opportunity->deadline?->locale('fr')->translatedFormat('j M Y');
@endphp
<article {{ $attributes->class(['opportunity-card min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 gap-4">
        <x-company-logo :company="$company" :size="64" />
        <div class="min-w-0 flex-1 space-y-1.5">
            <div class="flex min-w-0 flex-wrap items-start justify-between gap-x-3 gap-y-1">
                <h3 class="min-w-0 break-words text-[18px] font-extrabold leading-tight"><a href="{{ route('opportunites.show', $opportunity) }}" class="text-ink no-underline hover:underline">{{ $opportunity->title }}</a></h3>
                <span class="shrink-0 rounded-chip px-3 py-1 text-[12px] font-bold {{ $opportunity->kind->badgeClasses() }}">{{ $opportunity->kind->label() }}</span>
            </div>
            <p class="flex min-w-0 flex-wrap items-center gap-x-1.5 text-[14px] font-semibold"><a href="{{ route('entreprises.show', $company) }}" class="break-words text-ink no-underline hover:underline">{{ $company->name }}</a>
                @if ($company->verified())<span class="inline-flex size-[16px] items-center justify-center rounded-full bg-link text-white" title="Immatriculation vérifiée par GAMAD"><x-icon name="check" :size="11" /><span class="sr-only">Immatriculation vérifiée par GAMAD</span></span>@endif</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[13px] text-muted">
                @if ($opportunity->city)<span class="inline-flex items-center gap-1"><x-icon name="pin" :size="15" />{{ $opportunity->city->label() }}</span>@endif
                <span>Publiée {{ $opportunity->published_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</span>
                @if ($deadline)<span>Avant le {{ $deadline }}</span>@endif
            </p>
            @unless ($compact)<p class="line-clamp-2 break-words text-[14px] leading-snug text-muted">{{ $opportunity->firstLine(220) }}</p>@endunless
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
        @if ($canAct)
            <button type="button" wire:click="toggleSave('{{ $opportunity->id }}')" aria-pressed="{{ $saved ? 'true' : 'false' }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-button px-3 text-[13px] font-bold text-link">
                <x-icon name="book" :size="16" />{{ $saved ? 'Sauvegardée' : 'Sauvegarder' }}
            </button>
        @endif
        <x-button :href="route('opportunites.show', $opportunity)" variant="secondary" class="!min-h-11">Voir l’opportunité <x-icon name="next" :size="16" class="ml-1.5" /></x-button>
    </div>
</article>
