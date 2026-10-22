<x-layouts.guest title="Nouveau mot de passe" heading="Nouveau mot de passe">
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" label="Adresse e-mail" type="email" autocomplete="email" required :value="$request->email" />
        <x-field name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" required autofocus
                 hint="8 caractères au minimum." />
        <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required />

        <x-button x-bind:disabled="busy"><span x-show="!busy">Enregistrer</span><span x-show="busy" x-cloak>Enregistrement…</span></x-button>
    </form>
</x-layouts.guest>
