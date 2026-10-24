@php($schedule = $schedule ?? [])
<div class="grid min-w-0 gap-3 sm:grid-cols-2">
    <div><label for="{{ $prefix }}-from" class="text-[13px] font-semibold">À partir du (facultatif)</label><input id="{{ $prefix }}-from" type="date" wire:model="{{ $prefix }}.from" class="mt-1 min-h-12 w-full min-w-0 rounded-button border border-chip-line bg-card px-3"></div>
    <div><label for="{{ $prefix }}-until" class="text-[13px] font-semibold">Jusqu’au (facultatif)</label><input id="{{ $prefix }}-until" type="date" wire:model="{{ $prefix }}.until" class="mt-1 min-h-12 w-full min-w-0 rounded-button border border-chip-line bg-card px-3"></div>
</div>
<fieldset><legend class="text-[13px] font-semibold">Jours possibles (facultatif)</legend><div class="mt-1 flex flex-wrap gap-2">@foreach (\App\Services\MatchingConditions::DAYS as $day => $label)<label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-button border border-line px-3 text-[13px]"><input type="checkbox" wire:model="{{ $prefix }}.days" value="{{ $day }}" class="size-5 accent-ink">{{ $label }}</label>@endforeach</div></fieldset>
<p class="text-[12px] text-muted">Sans période ou jours renseignés, les disponibilités resteront à préciser ensemble.</p>
<div><label for="{{ $prefix }}-note" class="text-[13px] font-semibold">Précisions de disponibilité (facultatif)</label><textarea id="{{ $prefix }}-note" wire:model="{{ $prefix }}.note" maxlength="140" rows="2" class="mt-1 w-full rounded-button border border-chip-line bg-card p-3"></textarea></div>
