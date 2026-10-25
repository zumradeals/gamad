@props(['title' => null, 'wide' => true, 'narrow' => false])

@php
    $enabled = fn (array $items) => array_values(array_filter($items, fn ($item) => $item['enabled'] ?? false));
    $navigationPrefix = \App\Support\Experience::initial() ? 'navigation.initial.' : 'navigation.';
    $phoneItems = $enabled(config($navigationPrefix.'phone'));
    $desktopItems = $enabled(config($navigationPrefix.'desktop'));
    // « Agir » : rond jaune au centre de la barre du bas (téléphone), bouton jaune dans la barre du haut (ordinateur).
    $agir = collect(config($navigationPrefix.'phone'))->firstWhere('key', 'agir');
    $agir = ($agir['enabled'] ?? false) && auth()->check() ? $agir : null;
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
    <x-brand-icons />
</head>
<body class="flex flex-col bg-ground text-ink antialiased">
    <header class="sticky top-0 z-20 border-b border-line bg-card">
        <div class="mx-auto flex w-full max-w-[1240px] flex-wrap items-center gap-3 px-4 py-3 lg:flex-nowrap lg:gap-5 lg:px-6 lg:py-0">
            <a href="{{ auth()->check() ? route('fil') : url('/') }}" class="inline-flex min-h-11 items-center no-underline" aria-label="GAMAD — accueil">
                <span class="flex flex-col">
                    <x-logo />
                </span>
            </a>

            @auth
            <nav aria-label="Navigation principale" class="hidden self-stretch lg:flex">
                @foreach ($desktopItems as $item)
                    <a href="{{ route($item['route']) }}"
                       @if ($isActive($item)) aria-current="page" @endif
                       class="flex min-h-[72px] min-w-11 items-center justify-center gap-2 whitespace-nowrap border-b-[3px] px-2.5 text-[13px] no-underline {{ $isActive($item) ? 'border-ink font-extrabold text-ink' : 'border-transparent font-semibold text-muted hover:border-line hover:text-ink' }}">
                        @if (isset($item['icon']))<x-icon :name="$item['icon']" :size="22" />@endif
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            @endauth

            @if (auth()->check() && \App\Support\Experience::initial())
                <x-header-search />
                <span class="flex-1 lg:hidden"></span>
            @else
                <span class="flex-1"></span>
            @endif

            @auth
                <div class="flex items-center gap-1">
                    <livewire:notification-bell />
                    <a href="{{ route('notifications') }}" class="hidden min-h-11 items-center text-[12px] font-semibold text-muted no-underline hover:text-ink min-[1700px]:flex">Notifications</a>
                </div>
                {{-- Menu utilisateur : visible sur ordinateur, avec le nom de la personne (maquette « Mon espace »). --}}
                <div x-data="{ userOpen: false }" class="relative hidden lg:block" x-on:click.outside="userOpen = false" x-on:keydown.escape.window="userOpen = false">
                    <button type="button" x-on:click="userOpen = !userOpen" x-bind:aria-expanded="userOpen.toString()" aria-controls="user-menu-desktop" aria-haspopup="true" aria-label="Menu de {{ auth()->user()?->name }}" class="flex min-h-11 items-center gap-2 border-l border-line pl-4 text-ink hover:text-ink">
                        @if (auth()->user()?->profile?->photoUrl())
                            <x-avatar :profile="auth()->user()->profile" :size="36" />
                        @else
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-ink text-[12px] font-extrabold text-white">{{ auth()->user()?->initials() }}</span>
                        @endif
                        <span class="hidden max-w-[120px] truncate text-[13px] font-semibold xl:inline">{{ auth()->user()?->name }}</span>
                        <x-icon name="chevron-down" :size="16" />
                    </button>
                    <nav id="user-menu-desktop" x-show="userOpen" x-cloak aria-label="Menu utilisateur" class="absolute right-0 top-full z-40 mt-2 w-60 rounded-card border border-line bg-card p-2 shadow-lg">
                        <a href="{{ route('espace') }}" class="flex min-h-11 items-center rounded-button px-3 text-[14px] font-semibold text-ink no-underline hover:bg-ground hover:text-ink">Mon espace</a>
                        @if (auth()->user()?->profile)
                            <a href="{{ route('personnes.show', auth()->user()->profile->public_id) }}" class="flex min-h-11 items-center rounded-button px-3 text-[14px] font-semibold text-ink no-underline hover:bg-ground hover:text-ink">Mon profil public</a>
                        @endif
                        <a href="{{ route('espace.profil') }}" class="flex min-h-11 items-center rounded-button px-3 text-[14px] font-semibold text-ink no-underline hover:bg-ground hover:text-ink">Gérer mon profil</a>
                        <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line-soft pt-1">
                            @csrf
                            <button type="submit" class="flex min-h-11 w-full items-center rounded-button px-3 text-left text-[14px] font-semibold text-ink hover:bg-ground">Se déconnecter</button>
                        </form>
                    </nav>
                </div>
                @if ($agir)
                    <div x-data="{ agirOpen: false }" class="relative hidden lg:block" x-on:click.outside="agirOpen = false" x-on:keydown.escape.window="agirOpen = false">
                        <button type="button" x-on:click="agirOpen = !agirOpen" x-bind:aria-expanded="agirOpen.toString()" aria-controls="agir-menu-desktop" aria-haspopup="true" class="flex min-h-11 items-center gap-1.5 rounded-button bg-accent px-4 text-[14px] font-extrabold text-ink">
                            <x-icon name="plus" :size="18" :stroke="2.5" />{{ $agir['label'] }}
                        </button>
                        <nav id="agir-menu-desktop" x-show="agirOpen" x-cloak aria-label="Actions" class="absolute right-0 top-full z-40 mt-2 w-64 rounded-card border border-line bg-card p-2 shadow-lg">
                            <a href="{{ route('agir.publier', 'besoin') }}" class="block rounded-button px-3 py-3 hover:bg-ground">Trouver de l’aide</a>
                            <a href="{{ route('agir.publier', 'apprendre') }}" class="block rounded-button px-3 py-3 hover:bg-ground">Apprendre quelque chose</a>
                            <a href="{{ route('agir.publier', 'aide') }}" class="block rounded-button px-3 py-3 hover:bg-ground">Proposer quelque chose</a>
                        </nav>
                    </div>
                @endif
            @else
                {{-- Visiteur sans compte (Carrefour Personnes, Monde d'une personne publics) : seulement entrer ou s'inscrire. --}}
                <div class="flex items-center gap-2">
                    <x-button :href="route('login')" variant="secondary" class="whitespace-nowrap !px-3.5">Se connecter</x-button>
                    <span class="hidden sm:contents">
                        <x-button :href="route('register')" class="whitespace-nowrap !px-3.5">Créer un compte</x-button>
                    </span>
                </div>
            @endauth
        </div>
    </header>

    <main @class(['mx-auto w-full flex-1 px-4 pt-4 md:px-6 lg:pb-10 lg:pt-6', 'max-w-[920px]' => $narrow, 'max-w-[1240px]' => ! $narrow, 'pb-[104px]' => auth()->check(), 'pb-10' => ! auth()->check()])>
        {{ $slot }}

        <div class="mt-6">@include('partials.legal-links')</div>
    </main>

    @auth
    {{-- « Découvrir » (D-160) : un lien vers /decouvrir que le JavaScript transforme en fenêtre à trois choix. --}}
    <div x-data="{ discover: false, agirOpen: false }">
    <nav aria-label="Navigation principale"
         class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-card lg:hidden"
         style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="mx-auto flex h-[76px] max-w-md items-center justify-around px-2">
            @foreach ($phoneItems as $item)
                @if ($item['prominent'] ?? false)
                    <button type="button" x-on:click="agirOpen = true" aria-haspopup="dialog" x-bind:aria-expanded="agirOpen.toString()" aria-controls="agir-menu-mobile"
                       class="flex min-w-0 flex-1 flex-col items-center gap-[3px] text-[11px] font-bold text-ink">
                        <span class="-mt-[18px] flex size-12 items-center justify-center rounded-full border-[3px] border-white bg-accent">
                            <x-icon :name="$item['icon']" :size="24" :stroke="2.5" />
                        </span>
                        {{ $item['label'] }}
                    </button>
                @else
                    <a href="{{ route($item['route']) }}"
                       @if ($isActive($item)) aria-current="page" @endif
                       @if ($item['sheet'] ?? false) x-on:click.prevent="discover = true" aria-haspopup="dialog" x-bind:aria-expanded="discover.toString()" @endif
                       class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-[3px] text-[11px] no-underline {{ $isActive($item) ? 'font-bold text-ink hover:text-ink' : 'font-semibold text-muted hover:text-ink' }}">
                        <x-icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    <div x-show="agirOpen" x-cloak x-trap.noscroll="agirOpen" x-on:keydown.escape.window="agirOpen = false"
         id="agir-menu-mobile" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-labelledby="agir-titre">
        <div class="absolute inset-0 bg-ink/55" x-on:click="agirOpen = false" aria-hidden="true"></div>
        <div class="absolute inset-x-0 bottom-0 rounded-t-[28px] bg-card px-5 pt-4 shadow-lg" style="padding-bottom: calc(24px + env(safe-area-inset-bottom))">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="agir-titre" class="text-[22px] font-extrabold">Que souhaitez-vous faire ?</h2>
                <button type="button" x-on:click="agirOpen = false" aria-label="Fermer" class="flex size-11 items-center justify-center rounded-button border border-line"><x-icon name="close" :size="22" /></button>
            </div>
            <nav aria-label="Actions" class="space-y-2">
                <a href="{{ route('agir.publier', 'besoin') }}" class="block rounded-button border border-line px-4 py-4 font-semibold">Trouver de l’aide</a>
                <a href="{{ route('agir.publier', 'apprendre') }}" class="block rounded-button border border-line px-4 py-4 font-semibold">Apprendre quelque chose</a>
                <a href="{{ route('agir.publier', 'aide') }}" class="block rounded-button border border-line px-4 py-4 font-semibold">Proposer quelque chose</a>
            </nav>
        </div>
    </div>

    @unless (\App\Support\Experience::initial())
    <div x-show="discover" x-cloak x-trap.noscroll="discover" x-on:keydown.escape.window="discover = false"
         class="fixed inset-0 z-30 lg:hidden" role="dialog" aria-modal="true" aria-labelledby="decouvrir-titre">
        <div x-show="discover" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink/55" x-on:click="discover = false" aria-hidden="true"></div>
        <div x-show="discover" x-transition:enter="transition duration-200 ease-out motion-reduce:duration-0" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             class="absolute inset-x-0 bottom-0 max-h-[90vh] overflow-y-auto rounded-t-[28px] bg-card px-5 pt-3 shadow-[0_-8px_30px_rgba(14,42,63,0.25)]"
             style="padding-bottom: calc(24px + env(safe-area-inset-bottom))">
            <span class="mx-auto mb-4 block h-1.5 w-14 rounded-full bg-chip-line" aria-hidden="true"></span>
            <div class="mb-4 flex items-start gap-3">
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <h2 id="decouvrir-titre" class="text-[24px] font-extrabold leading-tight">Que cherchez-vous ?</h2>
                    <p class="text-[15px] leading-snug text-muted">Trouvez une personne, un besoin concret ou un projet à découvrir.</p>
                </div>
                <button type="button" x-on:click="discover = false" aria-label="Fermer" class="flex size-12 shrink-0 items-center justify-center rounded-button border border-line bg-card text-ink hover:border-ink"><x-icon name="close" :size="22" /></button>
            </div>
            @include('partials.discover-cards')
        </div>
    </div>
    @endunless
    </div>
    @endauth

    @livewireScripts
</body>
</html>
