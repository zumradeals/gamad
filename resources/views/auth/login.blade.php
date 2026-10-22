<x-layouts.guest title="Connexion" heading="Se connecter">
    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <x-field name="email" label="Adresse e-mail" type="email" autocomplete="email" required autofocus />
        <x-field name="password" label="Mot de passe" type="password" autocomplete="current-password" required />

        <label class="flex min-h-11 items-center gap-3 text-[14px]">
            <input type="checkbox" name="remember" value="1" class="size-5 rounded border-chip-line accent-ink">
            Rester connecté
        </label>

        <x-button x-bind:disabled="busy"><span x-show="!busy">Se connecter</span><span x-show="busy" x-cloak>Connexion…</span></x-button>
    </form>

    <div class="flex flex-col gap-1 text-[14px] font-semibold">
        <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center">Mot de passe oublié ?</a>
        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center">Créer un compte</a>
    </div>
</x-layouts.guest>
