<x-layouts.guest title="Compte suspendu" heading="Votre compte est suspendu">
    <p>La modération de GAMAD a suspendu votre compte le <strong>{{ $user->suspended_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}</strong>.</p>

    <div class="rounded-button bg-ground p-3.5">
        <p class="text-[12px] font-extrabold text-muted">MOTIF</p>
        <p class="mt-1 whitespace-pre-line break-words leading-normal">{{ $user->suspension_reason }}</p>
    </div>

    <p class="leading-normal">Tant que la suspension dure, vous ne pouvez plus publier, écrire ni répondre, et les autres personnes ne voient plus votre profil ni vos publications.</p>

    @if ($contact)
        <p class="leading-normal">Pour demander un réexamen, écrivez à : <strong class="break-words">{{ $contact }}</strong>.</p>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="flex flex-col">
        @csrf
        <x-button variant="secondary">Se déconnecter</x-button>
    </form>
</x-layouts.guest>
