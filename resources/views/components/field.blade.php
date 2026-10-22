@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'hint' => null, 'model' => null])

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-[13px] font-semibold text-ink">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
        {{ $attributes->except('value')->class(['min-h-11 w-full rounded-button border bg-card px-3.5 text-[15px] text-ink placeholder:text-muted', $errors->has($name) ? 'border-danger' : 'border-chip-line']) }}
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    >
    @if ($hint)
        <p class="text-[13px] text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
    @enderror
</div>
