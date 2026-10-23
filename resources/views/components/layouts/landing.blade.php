<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E2A3F">
    <meta name="description" content="Apportez ce que vous savez faire, apprenez ce qui vous manque, agissez avec d’autres sur le réseau GAMAD.">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body id="accueil" class="landing-page bg-ground text-ink antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-button focus:bg-card focus:p-3">Aller au contenu</a>
    <header class="sticky top-0 z-20 border-b border-line-soft bg-card">
        <div class="landing-container relative flex min-h-[76px] items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 shrink-0 flex-col justify-center no-underline" aria-label="GAMAD — accueil">
                <span class="flex items-center gap-1.5"><x-logo :tagline="false" /><span class="size-3 rounded-[2px] bg-accent" aria-hidden="true"></span></span>
                <span class="mt-0.5 block text-[12px] font-medium text-muted">Formation — Travail — Adoration</span>
            </a>
            <nav aria-label="Navigation de l’accueil" class="hidden items-center gap-6 lg:flex">
                <a href="#accueil" aria-current="page" class="landing-nav-link border-accent font-bold">Accueil</a>
                <a href="#comment" class="landing-nav-link border-transparent">Comment ça marche</a>
                <a href="#explorer" class="landing-nav-link border-transparent">Explorer</a>
            </nav>
            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="hidden min-h-11 items-center text-[13px] font-bold text-ink no-underline hover:text-link sm:inline-flex">Se connecter</a>
                <span class="hidden sm:contents"><x-button :href="route('register')" variant="accent" class="!px-4 !text-[13px]">Créer un compte</x-button></span>
                <details class="landing-mobile-menu relative lg:hidden">
                    <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-button border border-line" aria-label="Menu de l’accueil"><x-icon name="menu" :size="22" /></summary>
                    <nav aria-label="Navigation mobile de l’accueil" class="absolute right-0 top-full mt-2 flex w-64 max-w-[calc(100vw-32px)] flex-col gap-1 rounded-card border border-line bg-card p-3 shadow-lg">
                        <a href="#accueil" class="space-menu-link">Accueil</a>
                        <a href="#comment" class="space-menu-link">Comment ça marche</a>
                        <a href="#explorer" class="space-menu-link">Explorer</a>
                        <a href="{{ route('login') }}" class="space-menu-link">Se connecter</a>
                        <x-button :href="route('register')" variant="accent" class="!text-[13px]">Créer un compte</x-button>
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="contenu">{{ $slot }}</main>

    <div class="border-t border-line-soft bg-card">
        <div class="landing-container py-6">
            <footer class="flex flex-wrap items-center justify-between gap-3" aria-label="GAMAD">
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center gap-1.5 no-underline" aria-label="GAMAD — accueil"><x-logo :tagline="false" /><span class="size-3 rounded-[2px] bg-accent" aria-hidden="true"></span></a>
                <p class="text-[13px] font-medium text-muted">Formation — Travail — Adoration</p>
            </footer>
            @include('partials.legal-links')
        </div>
    </div>
</body>
</html>
