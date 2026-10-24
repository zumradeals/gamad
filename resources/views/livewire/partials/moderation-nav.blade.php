{{-- Barre commune des écrans de modération. Variable : $current ('file', 'personnes' ou 'journal'). --}}
<nav aria-label="Outils de modération" class="flex flex-wrap gap-2">
    @foreach (['file' => ['Publications', 'moderation'], 'personnes' => ['Personnes', 'moderation.personnes'], 'journal' => ['Journal', 'moderation.journal']] as $key => [$label, $route])
        <a href="{{ route($route) }}" @if ($current === $key) aria-current="page" @endif
           @class(['inline-flex min-h-11 items-center rounded-chip px-4 text-[13px] no-underline', 'bg-ink font-bold text-white hover:text-white' => $current === $key, 'border border-chip-line bg-card font-semibold text-ink hover:text-ink' => $current !== $key])>{{ $label }}</a>
    @endforeach
    @can('super-admin')<a href="{{ route('moderation.confiance') }}" @if ($current === 'confiance') aria-current="page" @endif class="inline-flex min-h-11 items-center rounded-chip border border-chip-line bg-card px-4 text-[13px] font-semibold no-underline">Confiance</a>@endcan
</nav>
