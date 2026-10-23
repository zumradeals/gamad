<x-layouts.guest title="Créer un compte" heading="Créer un compte" intro="Un compte vous permet d’agir sur GAMAD. Il ne fait pas de vous un membre du Mouvement.">
    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <x-field name="name" label="Nom affiché" autocomplete="name" required autofocus />
        <x-field name="email" label="Adresse e-mail" type="email" autocomplete="email" required
                 hint="Elle reste visible par vous seul." />
        <x-field name="password" label="Mot de passe" type="password" autocomplete="new-password" required
                 hint="8 caractères au minimum." />
        <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required />

        @if (app(\App\Services\LegalTexts::class)->charterAdopted())
            <div class="flex flex-col gap-1.5">
                <label for="charter" class="flex min-h-11 cursor-pointer items-start gap-3 text-[14px] leading-snug">
                    <input id="charter" type="checkbox" name="charter" value="1" required @checked(old('charter')) class="mt-0.5 size-5 shrink-0 accent-ink"
                           @error('charter') aria-invalid="true" aria-describedby="charter-error" @enderror>
                    <span>J’ai lu et j’accepte la <a href="{{ route('charte') }}" class="font-bold underline" target="_blank" rel="noopener">charte de conduite</a>.</span>
                </label>
                @error('charter')
                    <p id="charter-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <x-button x-bind:disabled="busy"><span x-show="!busy">Créer mon compte</span><span x-show="busy" x-cloak>Création…</span></x-button>
    </form>

    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">J’ai déjà un compte</a>
</x-layouts.guest>
