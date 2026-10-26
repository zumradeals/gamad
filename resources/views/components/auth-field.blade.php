@props(['name', 'label', 'icon', 'type' => 'text', 'autocomplete' => null, 'hint' => null, 'toggle' => false, 'placeholder' => null, 'required' => true])

{{-- Champ de formulaire public (inscription) : même apparence que la connexion, libellé associé, aide et erreur reliées au champ. --}}
<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-[14px] font-bold">{{ $label }}</label>
    <div class="flex min-h-12 items-center gap-2 rounded-button border bg-soft-blue pl-3 focus-within:outline focus-within:outline-[3px] focus-within:outline-link {{ $toggle ? '' : 'pr-3' }} {{ $errors->has($name) ? 'border-danger' : 'border-chip-line' }}">
        <x-icon :name="$icon" :size="20" class="shrink-0 text-muted" />
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($toggle) x-bind:type="show ? 'text' : 'password'" @endif
               @if ($type !== 'password') value="{{ old($name) }}" @endif @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($required) required @endif
               class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted"
               @php($described = collect([$hint ? $name.'-hint' : null, $errors->has($name) ? $name.'-error' : null])->filter()->implode(' ')) @if ($described !== '') aria-describedby="{{ $described }}" @endif
               @if ($errors->has($name)) aria-invalid="true" @endif>
        @if ($toggle)
            <button type="button" x-on:click="show = ! show" x-bind:aria-pressed="show.toString()" x-bind:aria-label="show ? 'Masquer les mots de passe' : 'Afficher les mots de passe'" class="flex size-11 shrink-0 items-center justify-center rounded-button text-muted hover:text-ink"><x-icon name="eye" :size="20" x-show="! show" /><x-icon name="eye-off" :size="20" x-show="show" x-cloak /></button>
        @endif
    </div>
    @if ($hint)<p id="{{ $name }}-hint" class="text-[13px] text-muted">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
</div>
