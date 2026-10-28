@props(['search' => null, 'count' => 0])

{{-- L'état réel d'une recherche de correspondances : jamais « terminée » tant qu'elle ne l'est pas, jamais « aucune piste » après un échec. --}}
@if ($search)
    @php($status = $search->status())
    @if ($status !== 'cancelled')
        <p role="status" data-testid="search-status" data-status="{{ $status }}" class="rounded-button border border-line bg-card px-3 py-2 text-[13px] leading-relaxed">
            @switch($status)
                @case('queued') <strong>Recherche en attente.</strong> Elle se fait en arrière-plan ; les pistes apparaîtront dans « Mes pistes », sans délai garanti. @break
                @case('running') <strong>Recherche en cours…</strong> Les premières pistes peuvent déjà apparaître. @break
                @case('retrying') <strong>Recherche à reprendre.</strong> Un incident momentané l’a interrompue ; elle reprendra automatiquement. @break
                @case('unavailable') <strong>Recherche momentanément indisponible.</strong> Les pistes déjà trouvées restent visibles ; aucune conclusion ne peut être tirée de cette absence de résultat. {{ $slot }} @break
                @default <strong>Recherche terminée.</strong> {{ $count > 0 ? $count.' piste'.($count > 1 ? 's' : '').' à explorer.' : 'Aucune piste pour le moment : de nouvelles personnes peuvent encore la rejoindre, vous serez prévenu(e) si vous l’avez choisi.' }}
            @endswitch
        </p>
    @endif
@endif
