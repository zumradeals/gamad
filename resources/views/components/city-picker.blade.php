@props(['id', 'model', 'value' => '', 'country' => '', 'live' => false, 'placeholder' => 'Rechercher un lieu', 'class' => 'min-h-12 w-full rounded-button border border-chip-line bg-card px-3 text-[15px]', 'disabled' => false])
@php
    $selected = filled($value) && \Illuminate\Support\Str::isUuid($value) ? \App\Models\City::query()->with('country:id,name')->find($value) : null;
@endphp
{{-- Choix d'un lieu par recherche (D-217) : le lieu choisi s'écrit dans la propriété Livewire « {{ $model }} ». --}}
<div class="relative" data-country="{{ $country }}" x-on:click.outside="open = false" x-on:keydown.escape="open = false"
     x-data="gamadCityPicker($wire, { model: @js($model), live: @js((bool) $live), url: @js(route('lieux.search')), selectedId: @js((string) $value), selectedLabel: @js($selected?->fullLabel() ?? '') })"
     x-init="$watch('$wire.{{ $model }}', value => { if (! value) { selectedId = ''; selectedLabel = ''; } })">
    <div x-show="selectedId" @unless ($selected) x-cloak @endunless class="{{ $class }} flex items-center justify-between gap-2">
        <span class="min-w-0 break-words" x-text="selectedLabel">{{ $selected?->fullLabel() }}</span>
        <button type="button" x-on:click="clear()" @disabled($disabled) class="inline-flex size-11 shrink-0 items-center justify-center text-[18px] font-bold" aria-label="Retirer ce lieu">×</button>
    </div>
    <div x-show="! selectedId" @if ($selected) x-cloak @endif>
        <input id="{{ $id }}" type="text" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-liste" x-bind:aria-expanded="open ? 'true' : 'false'"
               placeholder="{{ $placeholder }}" x-model="q" x-on:focus="openList()" x-on:input="typing()" x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="pick()"
               @disabled($disabled) class="{{ $class }} disabled:opacity-60">
        <ul id="{{ $id }}-liste" role="listbox" x-show="open" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-button border border-line bg-card shadow-lg">
            <template x-for="(item, index) in items" :key="item.id">
                <li role="option" x-on:mousedown.prevent="choose(item)" x-bind:aria-selected="index === active ? 'true' : 'false'" x-bind:class="index === active ? 'bg-soft-blue' : ''" class="flex min-h-11 cursor-pointer items-center px-3 text-[14px]" x-text="item.label"></li>
            </template>
            <li x-show="! items.length" class="flex min-h-11 items-center px-3 text-[13px] text-muted" x-text="message"></li>
        </ul>
    </div>
</div>
