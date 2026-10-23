{{--
    Écran 13 : « Qui vous a aidé ? » puis le remerciement public. Variables : $accepted (gestes acceptés), $mode ('resolve' ou 'thank').
    Wire : helperId, gratitudeText. « Quelqu'un d'autre ou personne en particulier » = aucun remerciement.
--}}
@php
    $resolve = $mode === 'resolve';
    $idPrefix = $resolve ? 'r' : 't';
@endphp

<div class="flex flex-col gap-3" x-data="{ helper: $wire.entangle('helperId'), text: $wire.entangle('gratitudeText') }">
    @if ($resolve)
        <div class="flex flex-col gap-1">
            <p class="text-[17px] font-extrabold">Votre besoin est résolu ?</p>
            <p class="text-[14px] text-muted">Bravo. Dites merci à la personne qui vous a aidé. Votre remerciement est public.</p>
        </div>
    @endif

    <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-[14px] font-extrabold">Qui vous a aidé ?</legend>
        @foreach ($accepted as $item)
            <label for="{{ $idPrefix }}-helper-{{ $item->user_id }}" class="flex min-h-14 cursor-pointer items-center gap-3 rounded-button border border-chip-line p-3 has-[:checked]:border-ink has-[:checked]:bg-soft-blue">
                <input id="{{ $idPrefix }}-helper-{{ $item->user_id }}" type="radio" name="{{ $idPrefix }}-helper" value="{{ $item->user_id }}" x-model="helper" class="size-5 accent-ink">
                <span class="flex min-w-0 flex-col">
                    <span class="break-words text-[15px] font-extrabold">{{ $item->author->name }}</span>
                    <span class="text-[12px] text-muted">Proposition acceptée</span>
                </span>
            </label>
        @endforeach
        @if ($resolve)
            <label for="{{ $idPrefix }}-helper-none" class="flex min-h-14 cursor-pointer items-center gap-3 rounded-button border border-chip-line p-3 has-[:checked]:border-ink has-[:checked]:bg-soft-blue">
                <input id="{{ $idPrefix }}-helper-none" type="radio" name="{{ $idPrefix }}-helper" value="" x-model="helper" class="size-5 accent-ink">
                <span class="text-[15px] font-semibold">Quelqu’un d’autre ou personne en particulier</span>
            </label>
        @endif
        @error('helper')
            <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
        @enderror
    </fieldset>

    <div x-show="helper !== ''" x-cloak class="flex flex-col gap-3">
        <div class="flex flex-col gap-1.5">
            <label for="{{ $idPrefix }}-gratitude" class="text-[14px] font-extrabold">Votre remerciement</label>
            <textarea id="{{ $idPrefix }}-gratitude" x-model="text" rows="3" maxlength="{{ \App\Models\Gratitude::BODY_MAX }}"
                      @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('gratitudeText') ? 'border-danger' : 'border-chip-line'])
                      @if ($errors->has('gratitudeText')) aria-invalid="true" aria-describedby="{{ $idPrefix }}-gratitude-error" @endif></textarea>
            <div class="flex justify-between gap-2">
                @error('gratitudeText')
                    <p id="{{ $idPrefix }}-gratitude-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @else
                    <span></span>
                @enderror
                <span class="text-[12px] text-muted"><span x-text="text.length"></span> / {{ \App\Models\Gratitude::BODY_MAX }}</span>
            </div>
        </div>

        <div x-show="text.trim() !== ''" x-cloak class="flex flex-col gap-1 rounded-button border border-line bg-ground p-3">
            <span class="text-[11px] font-extrabold tracking-wide text-muted">APERÇU SUR SON PROFIL</span>
            <p class="whitespace-pre-line break-words text-[14px] italic leading-normal" x-text="'« ' + text.trim() + ' »'"></p>
            <span class="text-[12px] font-semibold text-muted">— {{ auth()->user()->name }}</span>
        </div>

        <p class="text-[12px] leading-normal text-muted">Cette personne pourra masquer ce remerciement de son profil si elle le souhaite. Aucun compteur, aucune note.</p>

        @if ($resolve)
            <x-button type="button" variant="accent" wire:click="resolveAndThank" wire:loading.attr="disabled" wire:target="resolveAndThank">Marquer résolu et remercier</x-button>
        @else
            <x-button type="button" variant="accent" wire:click="thank" wire:loading.attr="disabled" wire:target="thank">Remercier</x-button>
        @endif
    </div>

    @if ($resolve)
        <x-button type="button" variant="secondary" x-show="helper === ''" wire:click="resolve" wire:loading.attr="disabled" wire:target="resolve">Marquer résolu sans remerciement</x-button>
        <p x-show="helper !== ''" x-cloak class="text-center">
            <button type="button" wire:click="resolve" wire:loading.attr="disabled" wire:target="resolve" class="inline-flex min-h-11 items-center text-[14px] font-semibold text-link underline">Marquer résolu sans remerciement</button>
        </p>
    @endif
</div>
