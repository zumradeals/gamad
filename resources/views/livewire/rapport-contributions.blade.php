@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
    $format = fn (int $n) => number_format($n, 0, ',', ' ');
    $month = fn (string $ym) => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym.'-01')->locale('fr')->translatedFormat('F Y');
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <h1 class="text-[24px] font-extrabold leading-tight">Rapport du fonds de contribution</h1>
        <p class="text-muted">Ce que les contributions ont permis, sans aucun nom de personne ni montant individuel. 1 ZAHAB = 1 FCFA.</p>
    </div>

    @if ($report['sandbox'])
        <x-status data-testid="mode-essai">Mode d’essai : ces chiffres viennent de paiements simulés, aucun argent réel.</x-status>
    @endif

    <section aria-labelledby="chiffres" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="chiffres" class="text-[18px] font-extrabold">En chiffres</h2>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Reçu</span><strong data-testid="recu">{{ $format($report['received']) }} ZAHAB</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Affecté à des projets</span><strong data-testid="affecte">{{ $format($report['allocated']) }} ZAHAB</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Dont déjà versé</span><strong>{{ $format($report['paid']) }} ZAHAB</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Reste à affecter</span><strong>{{ $format($report['available']) }} ZAHAB</strong></p>
        @if ($report['contributors'] !== null)
            <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Contributeurs (personnes et ZUMRA)</span><strong data-testid="contributeurs">{{ $report['contributors'] }}</strong></p>
        @else
            <p class="text-[12px] text-muted">Le nombre de contributeurs n’est publié qu’à partir de {{ \App\Services\Contributions::PUBLIC_COUNT_THRESHOLD }}, pour que personne ne soit reconnaissable.</p>
        @endif
    </section>

    <section aria-labelledby="mois" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="mois" class="text-[18px] font-extrabold">Par mois</h2>
        @forelse ($report['monthly'] as $ym => $total)
            <p wire:key="mois-{{ $ym }}" class="flex justify-between gap-3 text-[14px]"><span class="capitalize">{{ $month($ym) }}</span><strong>{{ $format($total) }} ZAHAB</strong></p>
        @empty
            <p class="text-[14px] text-muted">Aucune contribution pour l’instant.</p>
        @endforelse
    </section>

    <section aria-labelledby="affectations" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="affectations" class="text-[18px] font-extrabold">Affectations</h2>
        @forelse ($report['allocations'] as $a)
            <div class="flex flex-col gap-0.5 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                <span class="text-[14px] font-extrabold">{{ $a->project_name }} — {{ $a->zumra_name }}</span>
                <span class="text-[13px]">{{ $a->purpose }}</span>
                <span class="text-[12px] text-muted">{{ $format($a->amount) }} ZAHAB · décidé le {{ $date($a->created_at) }} · {{ $a->status === 'paid' ? 'versé le '.$date($a->paid_at) : 'à verser' }}</span>
            </div>
        @empty
            <p class="text-[14px] text-muted">Aucune affectation pour l’instant.</p>
        @endforelse
    </section>
</div>
