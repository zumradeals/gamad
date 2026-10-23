@props(['zumra', 'size' => 52])

@php
    // Une couleur par ZUMRA, stable (jetons de DESIGN.md §1), jamais liée à un mérite.
    $palettes = ['bg-ink text-white', 'bg-type-knowledge-fg text-white', 'bg-type-need-fg text-white', 'bg-type-learning-fg text-white', 'bg-type-project-fg text-white'];
    $palette = $palettes[crc32($zumra->id) % count($palettes)];
    $words = preg_split('/\s+/u', trim($zumra->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = implode('', array_map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2)));
@endphp

<span aria-hidden="true" {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-[14px] font-extrabold', $palette]) }}
      style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ (int) round($size * 0.34) }}px">{{ $initials }}</span>
