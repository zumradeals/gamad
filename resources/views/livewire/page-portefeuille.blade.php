@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y à H:i');
    $format = fn (int $n) => number_format($n, 0, ',', ' ');
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ $zumra ? route('zumras.show', $zumra) : route('espace') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← {{ $zumra ? $zumra->name : 'Mon espace' }}</a>
        <h1 class="text-[24px] font-extrabold leading-tight">{{ $zumra ? 'Portefeuille de la ZUMRA' : 'Mon portefeuille' }}</h1>
        <p class="text-muted flex items-center gap-2"><x-icon name="lock" :size="15" />Privé : {{ $zumra ? 'seuls le fondateur et les responsables le voient' : 'vous seul le voyez' }}.</p>
    </div>

    <section aria-labelledby="solde" class="flex flex-col gap-1 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="solde" class="text-[13px] font-semibold text-muted">Solde</h2>
        <p class="text-[32px] font-extrabold leading-tight" data-testid="solde">{{ $format($balance) }} ZAHAB</p>
        <p class="text-[13px] text-muted">1 ZAHAB = 1 FCFA. Les ZAHAB se dépensent dans le réseau ; il n’y a ni transfert entre personnes ni retrait pour l’instant.</p>
    </section>

    <section aria-labelledby="mouvements" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="mouvements" class="text-[18px] font-extrabold">Mouvements</h2>
        <div wire:loading.delay wire:target="gotoPage,nextPage,previousPage" class="text-[14px] text-muted">Chargement…</div>
        @forelse ($entries as $entry)
            <div wire:key="entry-{{ $entry->id }}" class="flex items-center justify-between gap-3 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                <div class="flex min-w-0 flex-col">
                    <span class="text-[14px] font-extrabold">{{ $entry->kind->label() }}</span>
                    <span class="text-[12px] text-muted">{{ $date($entry->created_at) }}</span>
                </div>
                <div class="flex shrink-0 flex-col items-end">
                    <span class="text-[15px] font-extrabold">{{ $entry->amount > 0 ? '+' : '−' }}{{ $format(abs($entry->amount)) }}</span>
                    <span class="text-[12px] text-muted">Solde {{ $format($entry->balance_after) }}</span>
                </div>
            </div>
        @empty
            <p class="text-[14px] text-muted">Aucun mouvement pour l’instant.</p>
        @endforelse
        {{ $entries->links() }}
    </section>
</div>
