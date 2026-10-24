@props(['publication'])
@if ($publication->matching['enabled'] ?? false)
    @php($conditions = $publication->matching)
    <div class="flex min-w-0 flex-col gap-2 rounded-button border border-line bg-ground p-3 text-[13px]">
        <div class="flex flex-wrap gap-2"><span class="rounded-chip bg-card px-3 py-1 font-bold">{{ ($conditions['activity'] ?? '') === 'training' ? 'Transmission / formation' : 'Prestation / aide' }}</span><span class="rounded-chip bg-card px-3 py-1 font-bold">{{ \App\Services\MatchingConditions::TERMS[$conditions['terms']] }}</span></div>
        @if ($conditions['budget'] ?? '')<p class="font-semibold [overflow-wrap:anywhere]">Budget ou prix indicatif : {{ $conditions['budget'] }}</p>@endif
        @if (($conditions['terms'] ?? '') === 'paid' && empty($conditions['budget']))<p class="font-semibold">Budget ou prix à discuter ensemble.</p>@endif
        @if ($conditions['remote'] ?? false)<p>Possible à distance</p>@endif
        @if (($conditions['from'] ?? '') || ($conditions['until'] ?? ''))<p>Période déclarée : {{ $conditions['from'] ?: 'début à convenir' }} → {{ $conditions['until'] ?: 'fin à convenir' }}</p>@endif
        @if ($conditions['days'] ?? [])<p>Jours possibles : {{ collect($conditions['days'])->map(fn ($day) => \App\Services\MatchingConditions::DAYS[$day])->implode(', ') }}</p>@endif
        @if ($conditions['note'] ?? '')<p class="whitespace-pre-line [overflow-wrap:anywhere]">{{ $conditions['note'] }}</p>@endif
        @unless ($conditions['open'] ?? false)<p class="font-semibold">Fermée au matching</p>@endunless
        <p class="text-[12px] text-muted">Conditions à confirmer ensemble. Aucun engagement ni paiement créé par la publication.</p>
    </div>
@endif
