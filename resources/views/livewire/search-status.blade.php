<div>
    {{-- L'état réel d'une recherche : jamais « terminée » avant la fin, jamais « aucune piste » après un échec. --}}
    @if ($status)
        <p role="status" data-testid="search-status" data-status="{{ $status }}" @if ($poll) wire:poll.{{ $poll }}.visible @endif class="rounded-button border border-line bg-card px-3 py-2 text-[13px] leading-relaxed">
            @switch($status)
                @case('queued') <strong>Recherche en attente.</strong> Elle se fait en arrière-plan ; l’état se met à jour ici, sans délai garanti. @break
                @case('running') <strong>Recherche en cours…</strong> Les pistes peuvent déjà apparaître. @break
                @case('retrying') <strong>Recherche à reprendre.</strong> Un incident momentané l’a interrompue ; elle reprendra automatiquement. @break
                @case('unavailable')
                    <strong>Recherche momentanément indisponible.</strong> Les pistes déjà trouvées restent visibles ; aucune conclusion ne peut être tirée de cette absence de résultat.
                    <button type="button" wire:click="retry" wire:loading.attr="disabled" class="ml-1 inline-flex min-h-11 items-center font-bold underline">Relancer la recherche</button>
                    @break
                @default
                    <strong>Recherche terminée.</strong>
                    @if ($count !== null) {{ $count > 0 ? $count.' piste'.($count > 1 ? 's' : '').' à explorer.' : 'Aucune piste pour le moment : de nouvelles personnes peuvent encore la rejoindre, vous serez prévenu(e) si vous l’avez choisi.' }} @endif
            @endswitch
        </p>
    @endif
    @if ($notice)<p role="status" class="mt-2 text-[13px] font-semibold">{{ $notice }}</p>@endif
</div>
