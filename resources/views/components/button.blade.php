@props(['variant' => 'primary', 'href' => null, 'type' => 'submit', 'busy' => false])

@php
    $classes = 'inline-flex min-h-11 items-center justify-center rounded-button px-5 text-[15px] font-bold no-underline transition disabled:cursor-not-allowed disabled:opacity-60 '.match ($variant) {
        'secondary' => 'border border-ink bg-card text-ink hover:bg-ground hover:text-ink',
        'accent' => 'bg-accent text-ink hover:text-ink',
        default => 'border border-ink bg-ink text-white hover:bg-[#0a1f2e] hover:text-white',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
