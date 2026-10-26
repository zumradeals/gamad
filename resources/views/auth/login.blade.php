<x-layouts.landing title="Connexion" :login="true">
    <div class="landing-container grid grid-cols-[minmax(0,1fr)] gap-8 py-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,440px)] lg:items-start lg:gap-12 lg:py-12">
        {{-- Le formulaire passe en premier sur téléphone : rien ne retarde la saisie. --}}
        <section aria-labelledby="titre-connexion" class="order-1 rounded-card border border-line bg-card p-6 shadow-sm md:p-8 lg:order-2">
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
            </form>

            <div class="mt-5 flex flex-col items-center gap-1 text-center text-[14px]">
                <p class="text-muted">Vous n’avez pas encore de compte ?</p>
                <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center gap-2 font-bold">Créer un compte<x-icon name="arrow-right" :size="16" /></a>
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center font-semibold">Découvrir GAMAD sans compte</a>
            </div>
        </section>

        <section aria-labelledby="accueil-humain" class="order-2 flex min-w-0 flex-col gap-6 lg:order-1 lg:pt-4">
            <div class="flex flex-col items-start gap-4">
                <span class="inline-flex items-center gap-2 rounded-chip bg-soft-blue px-3 py-2 text-[12px] font-bold text-link"><x-icon name="group" :size="18" />Le réseau social d’action</span>
                <h2 id="accueil-humain" class="landing-headline">Heureux de <span class="block text-[#a35f00]">vous retrouver.</span></h2>
                <p class="max-w-lg text-[16px] leading-relaxed md:text-[18px]">Retrouvez les personnes que vous suivez, découvrez de nouvelles expériences et poursuivez votre parcours à votre rythme.</p>
            </div>
            <ul class="m-0 hidden list-none flex-col gap-4 p-0 sm:flex">
                @foreach ([['group', 'Vos relations', 'Retrouvez les personnes que vous suivez.', 'bg-soft-blue text-link'], ['bell', 'Vos activités', 'Consultez vos notifications et vos nouveaux messages.', 'bg-type-call-bg text-type-call-fg'], ['learn', 'Vos opportunités', 'Découvrez de nouvelles perspectives, à votre rythme.', 'bg-type-knowledge-bg text-type-knowledge-fg']] as [$icon, $title, $text, $tone])
                    <li class="flex items-start gap-4"><span class="flex size-12 shrink-0 items-center justify-center rounded-button {{ $tone }}"><x-icon :name="$icon" :size="24" /></span><span><strong class="block text-[16px]">{{ $title }}</strong><span class="text-[14px] leading-snug text-muted">{{ $text }}</span></span></li>
                @endforeach
            </ul>
            <figure class="m-0 hidden min-w-0 lg:block">
                <img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="" width="510" height="280" decoding="async" class="block h-auto w-full rounded-[20px]">
                <figcaption class="mt-3 text-[13px] text-muted">Les mêmes personnes. De nouvelles possibilités.</figcaption>
            </figure>
        </section>
    </div>

    <section class="landing-container pb-10" aria-label="Ce que GAMAD garantit">
        <ul class="m-0 grid list-none gap-4 rounded-card bg-soft-blue p-5 sm:grid-cols-3">
            @foreach ([['shield', 'Un espace sécurisé', 'Vous choisissez ce que vous partagez et qui le voit.'], ['group', 'Votre réseau', 'Retrouvez vos contacts et les personnes que vous suivez.'], ['learn', 'Votre développement', 'Poursuivez votre parcours et découvrez de nouvelles opportunités.']] as [$icon, $title, $text])
                <li class="flex items-start gap-3"><x-icon :name="$icon" :size="26" class="mt-0.5 shrink-0 text-link" /><span><strong class="block text-[15px]">{{ $title }}</strong><span class="text-[13px] leading-snug text-muted">{{ $text }}</span></span></li>
            @endforeach
        </ul>
    </section>
</x-layouts.landing>
