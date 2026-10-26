@props(['opportunity', 'saved' => false, 'canAct' => false, 'compact' => false])

@php
    $company = $opportunity->company;
    $deadline = $opportunity->deadline?->locale('fr')->translatedFormat('j M Y');
    $place = $opportunity->city?->label();
@endphp
<article {{ $attributes->class(['opportunity-card min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 flex-wrap gap-3 sm:flex-nowrap">
        <x-company-logo :company="$company" :size="60" />
        <div class="min-w-0 flex-1 basis-48 space-y-1">
            <h3 class="min-w-0 break-words text-[17px] font-extrabold leading-tight"><a href="{{ route('opportunites.show', $opportunity) }}" class="-my-2 inline-flex min-h-11 items-center text-ink no-underline hover:underline">{{ $opportunity->title }}</a></h3>
            <p class="flex min-w-0 flex-wrap items-center gap-x-2 text-[14px] font-semibold"><a href="{{ route('entreprises.show', $company) }}" class="-my-2 inline-flex min-h-11 items-center break-words text-ink no-underline hover:underline">{{ $company->name }}</a>
                @if ($company->verified())<span class="inline-flex size-[18px] items-center justify-center rounded-full bg-link text-white" title="Immatriculation vérifiée par GAMAD"><x-icon name="check" :size="11" /><span class="sr-only">Immatriculation vérifiée par GAMAD</span></span>@endif</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-muted">
                @if ($place)<span class="inline-flex items-center gap-1"><x-icon name="pin" :size="15" />{{ $place }}</span>@endif
                <span>{{ $opportunity->published_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</span>
                @if ($deadline)<span>· avant le {{ $deadline }}</span>@endif
            </p>
            @unless ($compact)<p class="line-clamp-2 break-words text-[14px] leading-snug text-muted">{{ $opportunity->firstLine(220) }}</p>@endunless
        </div>
        <div class="flex w-full shrink-0 items-center justify-between gap-3 sm:w-44 sm:flex-col sm:items-end">
            <span class="rounded-chip px-3 py-1 text-[12px] font-bold {{ $opportunity->kind->badgeClasses() }}">{{ $opportunity->kind->label() }}</span>
            <div class="flex flex-col items-end gap-1">
                <x-button :href="route('opportunites.show', $opportunity)" variant="secondary" class="!min-h-11 !border-link !text-[13px] !text-link">Voir l’opportunité <x-icon name="next" :size="16" class="ml-1.5" /></x-button>
                @if ($canAct)
                    <button type="button" wire:click="toggleSave('{{ $opportunity->id }}')" aria-pressed="{{ $saved ? 'true' : 'false' }}" class="inline-flex min-h-11 items-center gap-1.5 px-2 text-[12px] font-bold text-link"><x-icon name="book" :size="15" />{{ $saved ? 'Sauvegardée' : 'Sauvegarder' }}</button>
                @endif
            </div>
        </div>
    </div>
</article>
