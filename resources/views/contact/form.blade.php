<div class="mx-auto flex w-full max-w-2xl flex-col gap-5">
    <header class="flex flex-col gap-2">
        <h1 class="text-[28px] font-extrabold leading-tight tracking-[-0.5px] md:text-[34px]">Contact</h1>
        <p class="text-[16px] leading-relaxed text-muted">Une question, un problème, une idée de partenariat ? Écrivez-nous. Regardez d’abord l’<a href="{{ route('aide') }}" class="font-bold underline">Aide</a> : la réponse s’y trouve peut-être.</p>
    </header>

    @if ($sent)
        <x-status><strong>Votre message est bien reçu.</strong> L’équipe de GAMAD le lira et vous répondra à l’adresse indiquée.</x-status>
        <p><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center font-bold underline">Retour à l’accueil</a></p>
    @else
        @if ($errors->any())
            <x-status tone="error">Votre message n’est pas parti : corrigez les champs signalés.</x-status>
        @endif

        <form method="POST" action="{{ route('contact.store') }}" class="flex flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-[14px] font-bold">Votre nom</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" required maxlength="80" autocomplete="name" class="{{ $fieldClass('name') }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')<p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-[14px] font-bold">Votre adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required maxlength="254" autocomplete="email" class="{{ $fieldClass('email') }}" aria-describedby="email-hint{{ $errors->has('email') ? ' email-error' : '' }}" @error('email') aria-invalid="true" @enderror>
                <p id="email-hint" class="text-[13px] text-muted">Elle nous sert uniquement à vous répondre.</p>
                @error('email')<p id="email-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="subject" class="text-[14px] font-bold">Sujet</label>
                <select id="subject" name="subject" required class="{{ $fieldClass('subject') }}" @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                    <option value="" disabled @selected(! old('subject'))>Choisissez un sujet</option>
                    @foreach (\App\Models\ContactMessage::SUBJECTS as $key => $label)
                        <option value="{{ $key }}" @selected(old('subject') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('subject')<p id="subject-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="message" class="text-[14px] font-bold">Votre message</label>
                <textarea id="message" name="message" rows="7" required minlength="10" maxlength="2000" class="{{ $fieldClass('message') }} py-3 leading-relaxed" aria-describedby="message-hint{{ $errors->has('message') ? ' message-error' : '' }}" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                <p id="message-hint" class="text-[13px] text-muted">2 000 caractères au maximum. N’y écrivez pas de mot de passe.</p>
                @error('message')<p id="message-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>

            {{-- Champ piège : invisible pour les personnes, rempli par les robots. --}}
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="website">Ne pas remplir</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <p class="text-[13px] leading-snug text-muted">Votre nom, votre adresse e-mail et votre message sont lus par l’équipe de GAMAD pour vous répondre. Ils ne sont jamais publics et sont effacés au bout de {{ (int) config('gamad.contact.retention_months') }} mois.</p>

            <x-button class="self-start">Envoyer le message</x-button>
        </form>
    @endif
</div>
