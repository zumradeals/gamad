@props(['title' => null, 'login' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E2A3F">
    <meta name="description" content="Découvrez des personnes, partagez votre savoir-faire et trouvez des occasions d’apprendre, d’aider ou de travailler sur GAMAD.">
    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-icons />
</head>
<body id="accueil" class="landing-page bg-ground text-ink antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-button focus:bg-card focus:p-3">Aller au contenu</a>
    <header class="sticky top-0 z-20 border-b border-line-soft bg-card">
        <div class="landing-container relative flex min-h-[76px] items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 shrink-0 flex-col justify-center no-underline" aria-label="GAMAD — accueil">
                <span class="flex items-center gap-1.5"><x-logo :tagline="false" /></span>
            </a>
            <nav aria-label="Navigation de l’accueil" class="hidden items-center gap-6 lg:flex">
                <a href="{{ route('home') }}" @if (! $login) aria-current="page" @endif class="landing-nav-link {{ $login ? 'border-transparent' : 'border-accent font-bold' }}">Accueil</a>
                <a href="{{ route('personnes.index') }}" class="landing-nav-link border-transparent">Découvrir</a>
                <a href="{{ route('home') }}#comment" class="landing-nav-link border-transparent">Comment ça marche</a>
                <a href="{{ route('home') }}#confiance" class="landing-nav-link border-transparent">À propos</a>
            </nav>
            <div class="flex items-center gap-3">
                <a href="{{ route('personnes.index') }}" aria-label="Rechercher une personne" class="inline-flex size-11 items-center justify-center rounded-button text-ink hover:bg-soft-blue"><x-icon name="search" :size="22" /></a>
                @unless ($login)<span class="hidden sm:contents"><x-button :href="route('login')" variant="secondary" class="!px-4 !text-[13px]">Se connecter</x-button></span>@endunless
                <span class="hidden sm:contents"><x-button :href="route('register')" variant="accent" class="!px-4 !text-[13px]">Créer un compte</x-button></span>
                <details class="landing-mobile-menu relative lg:hidden">
                    <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-button border border-line" aria-label="Menu de l’accueil"><x-icon name="menu" :size="22" /></summary>
                    <nav aria-label="Navigation mobile de l’accueil" class="absolute right-0 top-full mt-2 flex w-64 max-w-[calc(100vw-32px)] flex-col gap-1 rounded-card border border-line bg-card p-3 shadow-lg">
                        <a href="{{ route('home') }}" class="space-menu-link">Accueil</a>
                        <a href="{{ route('personnes.index') }}" class="space-menu-link">Découvrir</a>
                        <a href="{{ route('home') }}#comment" class="space-menu-link">Comment ça marche</a>
                        <a href="{{ route('home') }}#confiance" class="space-menu-link">À propos</a>
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
            <footer class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3" aria-label="GAMAD">
                <div class="flex min-w-0 flex-col gap-1"><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center gap-1.5 no-underline" aria-label="GAMAD — accueil"><x-logo :tagline="false" /></a><p class="max-w-xs text-[12px] leading-snug text-muted">Le réseau social d’action pour les personnes et leurs communautés.</p></div>
                <nav aria-label="Pied de page" class="flex flex-wrap gap-x-5">
                    <a href="{{ route('home') }}#confiance" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline hover:text-ink">À propos</a>
                    <a href="{{ route('personnes.index') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline hover:text-ink">Découvrir les personnes</a>
                    @php $textes = app(\App\Services\LegalTexts::class); @endphp
                    @if ($textes->charterAdopted())<a href="{{ route('charte') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline hover:text-ink">Charte de conduite</a>@endif
                    @if ($textes->legalAdopted())<a href="{{ route('mentions') }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline hover:text-ink">Mentions légales</a>@endif
                </nav>
            </footer>
        </div>
    </div>
    @if ($login)@livewireScripts @endif
</body>
</html>
