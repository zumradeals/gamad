@props(['title' => null])

@php
    $enabled = fn (array $items) => array_values(array_filter($items, fn ($item) => $item['enabled'] ?? false));
    $phoneItems = $enabled(config('navigation.phone'));
    $desktopItems = $enabled(config('navigation.desktop'));
    $isActive = fn (array $item) => $item['route'] && request()->routeIs($item['active'] ?? [$item['route'], $item['route'].'.*']);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E2A3F">
    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex flex-col bg-ground text-ink antialiased">
    <header class="border-b border-line bg-card">
        <div class="mx-auto flex w-full max-w-[1240px] items-center gap-6 px-4 py-3.5 md:px-6 md:py-2.5">
            <a href="{{ auth()->check() ? route('fil') : url('/') }}" class="no-underline" aria-label="GAMAD — accueil">
                <x-logo />
            </a>

            @auth
            <nav aria-label="Navigation principale" class="hidden gap-1 md:flex">
                @foreach ($desktopItems as $item)
                    <a href="{{ route($item['route']) }}"
                       @if ($isActive($item)) aria-current="page" @endif
                       class="flex min-h-11 items-center rounded-[10px] px-3.5 text-[14px] no-underline {{ $isActive($item) ? 'bg-soft-blue font-extrabold text-ink' : 'font-semibold text-muted hover:text-ink' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            @endauth

            <span class="flex-1"></span>

            @auth
                <a href="{{ route('espace') }}"
                   aria-label="Mon espace"
                   class="hidden size-11 items-center justify-center rounded-full bg-ink font-extrabold text-white no-underline hover:text-white md:flex">
                    {{ auth()->user()?->initial() }}
                </a>
            @else
                {{-- Visiteur sans compte (Carrefour Personnes, Monde d'une personne publics) : seulement entrer ou s'inscrire. --}}
                <div class="flex items-center gap-2">
                    <x-button :href="route('login')" variant="secondary" class="!px-3.5">Se connecter</x-button>
                    <x-button :href="route('register')" class="hidden !px-3.5 sm:inline-flex">Créer un compte</x-button>
                </div>
            @endauth
        </div>
    </header>

    <main @class(['mx-auto w-full max-w-[760px] flex-1 px-4 pt-4 md:px-6 md:pb-10 md:pt-6', 'pb-[104px]' => auth()->check(), 'pb-10' => ! auth()->check()])>
        {{ $slot }}
    </main>

    @auth
    <nav aria-label="Navigation principale"
         class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-card md:hidden"
         style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="mx-auto flex h-[76px] max-w-md items-center justify-center gap-6">
            @foreach ($phoneItems as $item)
                @if ($item['prominent'] ?? false)
                    <a href="{{ route($item['route']) }}"
                       class="flex w-24 flex-col items-center gap-[3px] text-[11px] font-bold text-ink no-underline hover:text-ink">
                        <span class="-mt-[18px] flex size-12 items-center justify-center rounded-full border-[3px] border-white bg-accent">
                            <x-icon :name="$item['icon']" :size="24" :stroke="2.5" />
                        </span>
                        {{ $item['label'] }}
                    </a>
                @else
                    <a href="{{ route($item['route']) }}"
                       @if ($isActive($item)) aria-current="page" @endif
                       class="flex min-h-11 w-24 flex-col items-center justify-center gap-[3px] text-[11px] no-underline {{ $isActive($item) ? 'font-bold text-ink hover:text-ink' : 'font-semibold text-muted hover:text-ink' }}">
                        <x-icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
    @endauth

    @livewireScripts
</body>
</html>
