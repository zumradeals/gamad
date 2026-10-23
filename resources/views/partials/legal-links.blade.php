@php
    $textes = app(\App\Services\LegalTexts::class);
    $liens = array_filter([
        $textes->charterAdopted() ? [route('charte'), 'Charte de conduite'] : null,
        $textes->legalAdopted() ? [route('mentions'), 'Mentions légales'] : null,
    ]);
@endphp

@if ($liens !== [])
    <footer class="flex flex-wrap gap-x-5 border-t border-line pt-3" aria-label="Textes de GAMAD">
        @foreach ($liens as [$url, $libelle])
            <a href="{{ $url }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline hover:text-ink">{{ $libelle }}</a>
        @endforeach
    </footer>
@endif
