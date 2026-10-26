{{-- En-tête commun de GAMAD Publicité : fil d'Ariane, titre, onglets (seuls les parcours livrés apparaissent). $current : 'creer' | 'campagnes' | 'resultats' --}}
@php
    $tabs = array_filter([
        'creer' => ['Créer une campagne', 'publicite.creer'],
        'campagnes' => ['Mes campagnes', 'publicite.campagnes'],
        'resultats' => ['Résultats', 'publicite.resultats'],
    ], fn ($tab) => \Illuminate\Support\Facades\Route::has($tab[1]));
@endphp
<div class="flex flex-col gap-1">
    <p class="flex flex-wrap items-center gap-1.5 text-[12px] text-muted"><a href="{{ route('espace') }}" class="font-semibold">Mon espace</a> <span aria-hidden="true">›</span> <a href="{{ route('espace.services') }}" class="font-semibold">Services GAMAD</a> <span aria-hidden="true">›</span> <strong class="text-ink">GAMAD Publicité</strong></p>
    <h1 class="text-[26px] font-extrabold leading-tight tracking-[-0.4px]">GAMAD Publicité</h1>
</div>
<nav aria-label="GAMAD Publicité" class="-mx-4 mb-1 mt-3 flex gap-1 overflow-x-auto border-b border-line px-4 md:mx-0 md:px-0">
    @foreach ($tabs as $key => [$label, $route])
        <a href="{{ route($route) }}" @if ($current === $key) aria-current="page" @endif @class(['inline-flex min-h-11 shrink-0 items-center border-b-[3px] px-3.5 text-[14px] font-bold no-underline', 'border-accent text-ink' => $current === $key, 'border-transparent text-muted' => $current !== $key])>{{ $label }}</a>
    @endforeach
</nav>
