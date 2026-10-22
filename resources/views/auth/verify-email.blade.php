<x-layouts.guest title="Vérification de l’e-mail" heading="Vérifiez votre adresse e-mail">
    <p>Nous avons envoyé un lien à <strong>{{ auth()->user()->email }}</strong>. Ouvrez-le pour activer votre compte.</p>

    @if (session('status') === 'verification-link-sent')
        <x-status>Un nouveau lien vient d’être envoyé.</x-status>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" x-data="{ busy: false }" x-on:submit="busy = true" class="flex flex-col">
        @csrf
        <x-button x-bind:disabled="busy"><span x-show="!busy">Renvoyer le lien</span><span x-show="busy" x-cloak>Envoi…</span></x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="flex flex-col">
        @csrf
        <x-button variant="secondary">Se déconnecter</x-button>
    </form>
</x-layouts.guest>
