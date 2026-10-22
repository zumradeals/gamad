<x-layouts.guest title="Mot de passe oublié" heading="Mot de passe oublié" intro="Indiquez votre adresse e-mail : nous vous envoyons un lien pour choisir un nouveau mot de passe.">
    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <x-field name="email" label="Adresse e-mail" type="email" autocomplete="email" required autofocus />

        <x-button x-bind:disabled="busy"><span x-show="!busy">Envoyer le lien</span><span x-show="busy" x-cloak>Envoi…</span></x-button>
    </form>

    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">Retour à la connexion</a>
</x-layouts.guest>
