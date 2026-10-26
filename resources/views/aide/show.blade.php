@php $contenu = view('aide.contenu')->render(); @endphp
@auth
    <x-layouts.app title="Aide" narrow>{!! $contenu !!}</x-layouts.app>
@else
    <x-layouts.landing title="Aide">
        <div class="landing-container py-8 md:py-12"><div class="mx-auto max-w-3xl">{!! $contenu !!}</div></div>
    </x-layouts.landing>
@endauth
