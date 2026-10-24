@props(['title' => null, 'heading' => null, 'intro' => null, 'wide' => false, 'bare' => false])

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
<body class="bg-ground text-ink antialiased">
    <div @class(['mx-auto flex min-h-screen w-full flex-col gap-6 px-4 py-8', 'max-w-[440px]' => ! $wide, 'max-w-[1100px] md:px-6' => $wide])>
        <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center self-start no-underline" aria-label="GAMAD — accueil">
            <x-logo />
        </a>

        <main @class(['flex flex-col gap-4', 'rounded-card border border-line bg-card p-5 md:p-6' => ! $bare, 'md:p-0' => $bare, 'flex-1' => $wide])>
            @if ($heading)
                <h1 class="text-[24px] font-extrabold leading-tight">{{ $heading }}</h1>
            @endif
            @if ($intro)
                <p class="text-muted">{{ $intro }}</p>
            @endif

            {{ $slot }}
        </main>

        @include('partials.legal-links')
    </div>
    @livewireScripts
</body>
</html>
