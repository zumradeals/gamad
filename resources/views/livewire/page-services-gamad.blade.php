<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Mon espace</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Services GAMAD</h1>
        <p class="text-muted">Les services numériques de GAMAD qui vous sont ouverts. Seuls les services disponibles apparaissent ici.</p>
    </div>

    <ul class="grid gap-3 md:grid-cols-2">
        @foreach ($services as $key => $service)
            <li wire:key="service-{{ $key }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link" aria-hidden="true"><x-icon :name="$service['icon']" :size="24" /></span>
                    <h2 class="text-[18px] font-extrabold">{{ $service['label'] }}</h2>
                </div>
                <p class="text-[14px] leading-relaxed text-muted">{{ $service['description'] }}</p>
                <x-button :href="$service['url']" class="self-start">Ouvrir</x-button>
            </li>
        @endforeach
    </ul>
</div>
