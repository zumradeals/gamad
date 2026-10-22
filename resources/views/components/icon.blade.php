@props(['name', 'size' => 22, 'stroke' => 2])

@php
    $paths = [
        'feed' => '<path d="M4 6h16M4 12h16M4 18h10"></path>',
        'compass' => '<circle cx="12" cy="12" r="9"></circle><path d="M15.5 8.5l-2 5-5 2 2-5z"></path>',
        'plus' => '<path d="M12 5v14M5 12h14"></path>',
        'group' => '<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"></path><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.8 3.5 6"></path>',
        'user' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path>',
        'search' => '<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>',
        'pin' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"></path><circle cx="12" cy="10" r="2.5"></circle>',
        'back' => '<path d="M15 6l-6 6 6 6"></path>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"></path>',
        'book' => '<path d="M4 19.5V6a2 2 0 0 1 2-2h12v14H6a2 2 0 0 0-2 1.5zM6 18h12v3H6a2 2 0 0 1 0-3z"></path>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path>',
    ];
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $paths[$name] ?? '' !!}</svg>
