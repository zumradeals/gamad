@props(['profile', 'size' => 52, 'ring' => false])

@php
    // Une couleur douce par personne, stable (jetons de design : DESIGN.md §1), jamais liée à un mérite.
    $palettes = [
        'bg-type-learning-bg text-type-learning-fg',
        'bg-type-need-bg text-type-need-fg',
        'bg-type-project-bg text-type-project-fg',
        'bg-type-knowledge-bg text-type-knowledge-fg',
        'bg-type-call-bg text-type-call-fg',
    ];
    $palette = $palettes[crc32($profile->public_id) % count($palettes)];
    $url = $profile->photoUrl();
@endphp

@if ($url)
    <img src="{{ $url }}" alt="" width="{{ $size }}" height="{{ $size }}" loading="lazy"
         {{ $attributes->class(['shrink-0 rounded-full object-cover', 'border-[3px] border-accent' => $ring]) }}
         style="width: {{ $size }}px; height: {{ $size }}px">
@else
    <span aria-hidden="true"
          {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-full font-extrabold', $palette, 'border-[3px] border-accent' => $ring]) }}
          style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ (int) round($size * 0.34) }}px">{{ $profile->user->initials() }}</span>
@endif
