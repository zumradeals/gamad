<x-layouts.landing title="Connexion" :login="true">
    <div class="login-stage">
        <div class="landing-container grid grid-cols-[minmax(0,1fr)] gap-0 pb-8 pt-0 lg:grid-cols-12 lg:items-stretch lg:gap-0 lg:py-10">
            {{-- Portrait : bandeau compact sur téléphone (le formulaire reste tout de suite accessible), grande image fondue sur ordinateur. --}}
            <figure class="relative m-0 mb-16 lg:col-span-4 lg:col-start-5 lg:row-start-1 lg:mb-0 lg:min-h-[540px]">
                <img src="{{ asset('images/gamad-connexion-portrait.webp') }}" alt="" width="388" height="532" fetchpriority="high" decoding="async" class="login-photo h-64 w-full rounded-b-card object-cover object-[50%_30%] lg:h-full lg:rounded-none lg:object-[50%_0]">
                <figcaption class="absolute inset-x-3 -bottom-14 flex items-center gap-3 rounded-card bg-card p-3 shadow-lg lg:-left-10 lg:bottom-8 lg:right-auto lg:w-[320px] lg:p-4">
                    <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-type-call-bg text-type-call-fg"><x-icon name="idea" :size="24" /></span>
                    <span><strong class="block text-[14px] leading-snug lg:text-[15px]">Les mêmes personnes.<br>De nouvelles possibilités.</strong><span class="block text-[12px] leading-snug text-muted lg:text-[13px]">Tout ce que vous avez commencé vous attend.</span></span>
                </figcaption>
            </figure>

            <section aria-labelledby="accueil-humain" class="relative z-10 hidden min-w-0 flex-col gap-6 lg:col-span-5 lg:col-start-1 lg:row-start-1 lg:flex lg:pr-6 lg:pt-2">
                <span class="inline-flex items-center gap-2 self-start rounded-chip bg-soft-blue px-3 py-2 text-[12px] font-bold text-link"><x-icon name="group" :size="18" />Le réseau social d’action</span>
                <h2 id="accueil-humain" class="landing-headline">Retrouvez votre espace <span class="block text-[#a35f00]">GAMAD.</span></h2>
                <p class="max-w-md text-[17px] leading-relaxed">Reconnectez-vous pour continuer à échanger, à découvrir, à apprendre et à agir selon vos envies.</p>
                <ul class="m-0 flex list-none flex-col gap-5 p-0">
                    @foreach ([['group', 'Vos relations', 'Retrouvez les personnes et organisations que vous suivez.', 'bg-soft-blue text-link'], ['bell', 'Vos activités', 'Consultez vos notifications et vos nouveaux messages.', 'bg-type-call-bg text-type-call-fg'], ['learn', 'Vos opportunités', 'Découvrez de nouvelles perspectives et poursuivez votre parcours.', 'bg-type-knowledge-bg text-type-knowledge-fg']] as [$icon, $title, $text, $tone])
                        <li class="flex items-start gap-4"><span class="flex size-14 shrink-0 items-center justify-center rounded-button {{ $tone }}"><x-icon :name="$icon" :size="28" /></span><span class="max-w-[230px]"><strong class="block text-[17px]">{{ $title }}</strong><span class="text-[14px] leading-snug text-muted">{{ $text }}</span></span></li>
                    @endforeach
                </ul>
            </section>

        <section aria-labelledby="titre-connexion" class="relative z-10 rounded-card border border-line bg-card p-6 shadow-lg md:p-8 lg:col-span-4 lg:col-start-9 lg:row-start-1 lg:mt-0 lg:self-center">
                <h1 id="titre-connexion" class="text-[28px] font-extrabold leading-tight tracking-[-0.5px]">Se connecter</h1>
                <p class="mt-1 text-[14px] text-muted">Accédez à votre espace GAMAD.</p>

                @if (session('status'))
                    <div class="mt-4"><x-status>{{ session('status') }}</x-status></div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-5 flex flex-col gap-4" x-data="{ busy: false, show: false }" x-on:submit="if (busy) { $event.preventDefault() } else { busy = true }">
                    @csrf
                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-[14px] font-bold">Adresse e-mail</label>
                        <div class="flex min-h-12 items-center gap-2 rounded-button border bg-soft-blue px-3 focus-within:outline focus-within:outline-[3px] focus-within:outline-link {{ $errors->has('email') ? 'border-danger' : 'border-chip-line' }}">
                            <x-icon name="mail" :size="20" class="shrink-0 text-muted" />
                            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="votre@email.com" class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none placeholder:text-muted" @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                        </div>
                        @error('email')<p id="email-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-[14px] font-bold">Mot de passe</label>
                        <div class="flex min-h-12 items-center gap-2 rounded-button border border-chip-line bg-soft-blue pl-3 focus-within:outline focus-within:outline-[3px] focus-within:outline-link">
                            <x-icon name="lock" :size="20" class="shrink-0 text-muted" />
                            <input id="password" x-bind:type="show ? 'text' : 'password'" type="password" name="password" autocomplete="current-password" required class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] outline-none">
                            <button type="button" x-on:click="show = ! show" x-bind:aria-pressed="show.toString()" x-bind:aria-label="show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" class="flex size-11 shrink-0 items-center justify-center rounded-button text-muted hover:text-ink"><x-icon name="eye" :size="20" x-show="! show" /><x-icon name="eye-off" :size="20" x-show="show" x-cloak /></button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-x-4">
                        <label class="flex min-h-11 items-center gap-3 text-[14px]"><input type="checkbox" name="remember" value="1" class="size-5 rounded border-chip-line accent-ink">Rester connecté</label>
                        <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">Mot de passe oublié ?</a>
                    </div>

                    <x-button x-bind:disabled="busy" class="min-h-12 w-full"><span x-show="! busy">Se connecter</span><span x-show="busy" x-cloak>Connexion…</span></x-button>
                    <p class="text-[12px] text-muted">Pour protéger les accès, GAMAD conserve des traces techniques de connexion (IP et navigateur chiffrés, compte visé ou authentifié), réservées à l’investigation de sécurité : {{ config('core_console.security.detail_days') }} jours pour les détails et {{ config('core_console.security.aggregate_days') }} jours pour les comptes horaires sans identifiant.</p>
                </form>

                <div class="mt-5 flex flex-col items-center gap-1 text-center text-[14px]">
                    <p class="text-muted">Vous n’avez pas encore de compte ?</p>
                    <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center gap-2 font-bold">Créer un compte<x-icon name="arrow-right" :size="16" /></a>
                    <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center font-semibold">Découvrir GAMAD sans compte</a>
                </div>
            </section>
        </div>
    </div>

    <section class="landing-container hidden pb-10 sm:block" aria-label="Ce que GAMAD garantit">
        <ul class="m-0 grid list-none gap-4 rounded-card bg-soft-blue p-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['shield', 'Un espace sécurisé', 'Vos informations sont protégées et vous gardez le contrôle.'], ['group', 'Votre réseau', 'Retrouvez vos contacts et vos communautés.'], ['chat', 'Vos échanges', 'Retrouvez vos conversations et vos messages.'], ['learn', 'Votre développement', 'Poursuivez votre parcours et découvrez de nouvelles opportunités.']] as [$icon, $title, $text])
                <li class="flex items-start gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-button bg-card text-link"><x-icon :name="$icon" :size="24" /></span><span><strong class="block text-[15px]">{{ $title }}</strong><span class="text-[13px] leading-snug text-muted">{{ $text }}</span></span></li>
            @endforeach
        </ul>
    </section>
</x-layouts.landing>
