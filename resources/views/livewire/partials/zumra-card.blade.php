{{-- Carte d'une ZUMRA du Carrefour (Z1) : identité, nature, lieu, fonctionnement, description, membres réels. Aucun chiffre inventé. --}}
@php
    $place = collect([$zumra->city?->label(), $zumra->city?->country?->name])->filter()->implode(', ');
    $meta = collect([$place, $zumra->format->label()])->filter()->implode(' · ');
@endphp
<article wire:key="{{ $prefix }}-{{ $zumra->id }}" @class(['zumra-mission-card', '!flex-col' => ! ($row ?? false), '!flex-row' => $row ?? false])>
    <div @class(['zumra-mission-cover', '!min-h-24 !w-full' => ! ($row ?? false), '!w-24' => $row ?? false]) aria-hidden="true"><x-zumra-initials :zumra="$zumra" :size="64" /></div>
    <div class="flex min-w-0 flex-1 flex-col gap-2 p-4">
        <p class="flex flex-wrap items-center gap-x-2 text-[12px] font-extrabold uppercase tracking-wide text-muted">
            @if ($zumra->sphere)<span class="inline-flex items-center gap-1"><x-icon :name="$zumra->sphere->icon()" :size="14" />{{ $zumra->sphere->label() }}</span>@endif
            <span>{{ $zumra->domain->name }}</span>
        </p>
        <h3 class="text-[19px] font-extrabold leading-tight"><a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words text-ink no-underline hover:underline">{{ $zumra->name }}</a></h3>
        @if (in_array($zumra->id, $engagedIds ?? [], true))<span class="inline-flex items-center gap-1 self-start rounded-chip bg-type-call-bg px-2 py-1 text-[12px] font-bold text-type-call-fg"><x-icon name="check" :size="14" />ZUMRA engagée</span>@endif
        @if ($zumra->isRecognized())<span class="inline-flex items-center gap-1 self-start rounded-chip bg-type-knowledge-bg px-2 py-1 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check" :size="14" />Reconnue par GAMAD</span>@endif
        @if ($meta !== '')<p class="flex items-start gap-2 text-[13px] leading-relaxed text-muted"><x-icon name="pin" :size="16" class="mt-0.5 shrink-0" /><span>{{ $meta }}</span></p>@endif
        <p class="whitespace-pre-line break-words text-[14px] leading-relaxed">{{ \Illuminate\Support\Str::limit($zumra->mission, 140) }}</p>
        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
            <span class="inline-flex items-center gap-2 text-[13px] text-muted"><x-icon name="group" :size="18" />{{ $zumra->members_count }} {{ $zumra->members_count > 1 ? 'membres' : 'membre' }}</span>
            <x-button class="gap-2 !px-4" :href="route('zumras.show', $zumra)">Voir la ZUMRA<x-icon name="arrow-right" :size="16" /></x-button>
        </div>
    </div>
</article>
