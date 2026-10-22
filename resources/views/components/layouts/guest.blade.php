@props(['title' => null, 'heading' => null, 'intro' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E2A3F">
    <title>{{ $title ? $title.' — GAMAD' : 'GAMAD' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-ground text-ink antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-[440px] flex-col gap-6 px-4 py-8">
        <a href="{{ url('/') }}" class="self-start no-underline" aria-label="GAMAD — accueil">
            <x-logo />
        </a>

        <main class="flex flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6">
            @if ($heading)
                <h1 class="text-[24px] font-extrabold leading-tight">{{ $heading }}</h1>
            @endif
            @if ($intro)
                <p class="text-muted">{{ $intro }}</p>
            @endif

            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
