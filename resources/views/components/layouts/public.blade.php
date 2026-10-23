@props(['title' => null])

{{-- Pages publiques de texte (charte, mentions légales) : dans l'application pour une personne connectée, sinon sur le gabarit visiteur. --}}
@auth
    <x-layouts.app :title="$title" narrow>
        {{ $slot }}
    </x-layouts.app>
@else
    <x-layouts.guest :title="$title" wide>
        {{ $slot }}
    </x-layouts.guest>
@endauth
