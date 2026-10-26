{{-- Les trois choix de « Découvrir » (fenêtre du téléphone et page /decouvrir), lus dans config/navigation.php. --}}
<ul class="flex flex-col gap-3">
    @php
    $zumraOpen = \App\Support\ZumraModule::accessibleTo(auth()->user());
    $choices = collect(config(\App\Support\Experience::initial() ? 'navigation.initial.discover' : 'navigation.discover'))
        ->filter(fn ($choice) => ($choice['module'] ?? null) !== 'zumra' || $zumraOpen);
@endphp
    @foreach ($choices as $choice)
        <li>
            <a href="{{ route($choice['route']) }}" class="flex min-h-[84px] items-center gap-3.5 rounded-card border border-line bg-card px-4 py-3.5 text-ink no-underline hover:border-ink hover:text-ink">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-button bg-type-call-bg text-ink" aria-hidden="true"><x-icon :name="$choice['icon']" :size="26" /></span>
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="text-[17px] font-extrabold">{{ $choice['label'] }}</span>
                    <span class="text-[14px] leading-snug text-muted">{{ $choice['description'] }}</span>
                </span>
                <x-icon name="next" :size="18" />
            </a>
        </li>
    @endforeach
</ul>
