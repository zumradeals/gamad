<x-layouts.landing title="Créer un compte" :register="true">
    <div class="login-stage">
        <div class="landing-container grid grid-cols-[minmax(0,1fr)] gap-0 pb-8 pt-0 lg:grid-cols-12 lg:items-stretch lg:gap-0 lg:py-10">
            {{-- Même composition que la connexion : portrait compact sur téléphone, le formulaire reste tout de suite accessible. --}}
            <figure class="relative m-0 mb-16 lg:col-span-4 lg:col-start-5 lg:row-start-1 lg:mb-0 lg:min-h-[600px]">
                <img src="{{ asset('images/gamad-connexion-portrait.webp') }}" alt="" width="388" height="532" fetchpriority="high" decoding="async" class="login-photo h-64 w-full rounded-b-card object-cover object-[50%_30%] lg:h-full lg:rounded-none lg:object-[50%_0]">
                <figcaption class="absolute inset-x-3 -bottom-14 flex items-center gap-3 rounded-card bg-card p-3 shadow-lg lg:-left-10 lg:bottom-8 lg:right-auto lg:w-[320px] lg:p-4">
                    <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-type-call-bg text-type-call-fg"><x-icon name="idea" :size="24" /></span>
                    <span><strong class="block text-[14px] leading-snug lg:text-[15px]">Commencez par découvrir.</strong><span class="block text-[12px] leading-snug text-muted lg:text-[13px]">La suite viendra naturellement.</span></span>
                </figcaption>
            </figure>

            <section aria-labelledby="bienvenue" class="relative z-10 hidden min-w-0 flex-col gap-6 lg:col-span-5 lg:col-start-1 lg:row-start-1 lg:flex lg:pr-6 lg:pt-2">
                <span class="inline-flex items-center gap-2 self-start rounded-chip bg-soft-blue px-3 py-2 text-[12px] font-bold text-link"><x-icon name="group" :size="18" />Le réseau social d’action</span>
                <h2 id="bienvenue" class="landing-headline">Bienvenue sur <span class="block text-[#a35f00]">GAMAD.</span></h2>
                <p class="max-w-md text-[17px] leading-relaxed">Créez votre compte pour découvrir des personnes, suivre ce qui vous inspire et échanger quand vous le souhaitez.</p>
                <ul class="m-0 flex list-none flex-col gap-5 p-0">
                    @foreach ([['user', 'Un simple compte', 'Un nom, une adresse e-mail, un mot de passe : le reste vient plus tard.', 'bg-soft-blue text-link'], ['lock', 'Vous gardez le contrôle', 'Votre adresse e-mail reste visible par vous seul.', 'bg-type-call-bg text-type-call-fg'], ['compass', 'Rien d’obligatoire', 'Vous pouvez simplement regarder, lire et revenir quand vous voulez.', 'bg-type-knowledge-bg text-type-knowledge-fg']] as [$icon, $title, $text, $tone])
                        <li class="flex items-start gap-4"><span class="flex size-14 shrink-0 items-center justify-center rounded-button {{ $tone }}"><x-icon :name="$icon" :size="28" /></span><span class="max-w-[260px]"><strong class="block text-[17px]">{{ $title }}</strong><span class="text-[14px] leading-snug text-muted">{{ $text }}</span></span></li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="titre-inscription" class="relative z-10 rounded-card border border-line bg-card p-6 shadow-lg md:p-8 lg:col-span-4 lg:col-start-9 lg:row-start-1 lg:self-center">
                <h1 id="titre-inscription" class="text-[28px] font-extrabold leading-tight tracking-[-0.5px]">Créer un compte</h1>
                <p class="mt-1 text-[14px] text-muted">Présentez votre savoir-faire, partagez vos réalisations et rencontrez d’autres personnes.</p>

                <form method="POST" action="{{ route('register.store') }}" class="mt-5 flex flex-col gap-4" x-data="{ busy: false, show: false }" x-on:submit="if (busy) { $event.preventDefault() } else { busy = true }">
                    @csrf
                    <x-auth-field name="name" label="Nom affiché" icon="user" autocomplete="name" placeholder="Votre prénom et votre nom" />
                    <x-auth-field name="email" label="Adresse e-mail" icon="mail" type="email" autocomplete="email" placeholder="votre@email.com" hint="Elle reste visible par vous seul." />
                    <x-auth-field name="password" label="Mot de passe" icon="lock" type="password" autocomplete="new-password" :toggle="true" hint="8 caractères au minimum." />
                    <x-auth-field name="password_confirmation" label="Confirmer le mot de passe" icon="lock" type="password" autocomplete="new-password" />

                    @if (app(\App\Services\LegalTexts::class)->charterAdopted())
                        <div class="flex flex-col gap-1.5">
                            <label for="charter" class="flex min-h-11 cursor-pointer items-start gap-3 text-[14px] leading-snug">
                                <input id="charter" type="checkbox" name="charter" value="1" required @checked(old('charter')) class="mt-0.5 size-5 shrink-0 accent-ink" @error('charter') aria-invalid="true" aria-describedby="charter-error" @enderror>
                                <span>J’ai lu et j’accepte la <a href="{{ route('charte') }}" class="font-bold underline" target="_blank" rel="noopener">charte de conduite</a>.</span>
                            </label>
                            @error('charter')<p id="charter-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <x-button x-bind:disabled="busy" class="min-h-12 w-full"><span x-show="! busy">Créer mon compte</span><span x-show="busy" x-cloak>Création…</span></x-button>
                </form>

                <div class="mt-5 flex flex-col items-center gap-1 text-center text-[14px]">
                    <p class="text-muted">Vous avez déjà un compte ?</p>
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center gap-2 font-bold">Se connecter<x-icon name="arrow-right" :size="16" /></a>
                    <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center font-semibold">Découvrir GAMAD sans compte</a>
                </div>
            </section>
        </div>
    </div>
</x-layouts.landing>
