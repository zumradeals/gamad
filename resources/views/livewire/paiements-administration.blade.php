@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y à H:i');
    $format = fn (int $n) => number_format($n, 0, ',', ' ');
    $owner = fn ($p) => $p->wallet->isPerson() ? 'Personne' : 'ZUMRA';
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres.paiement') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paiement</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Paiements</h1>
        <p class="text-muted"><a href="{{ route('espace.reversements') }}" class="font-semibold underline">Reversements</a> · Réservé au super administrateur. Aucun nom n’apparaît : seul le type de portefeuille est indiqué.</p>
    </div>

    @if ($message)
        <x-status :tone="$messageIsError ? 'error' : 'info'">{{ $message }}</x-status>
    @endif

    <section aria-labelledby="rapprochement" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="rapprochement" class="text-[18px] font-extrabold">Rapprochement — mode {{ $mode === 'live' ? 'réel' : 'essai' }}</h2>
        <div class="flex flex-col gap-1 text-[14px]">
            <p class="flex justify-between gap-3"><span class="text-muted">Recharges confirmées</span><strong data-testid="confirmed">{{ $format($report['confirmed']) }}</strong></p>
            <p class="flex justify-between gap-3"><span class="text-muted">Créditées au registre</span><strong data-testid="ledger">{{ $format($report['ledger']) }}</strong></p>
            <p class="flex justify-between gap-3"><span class="text-muted">Remboursées</span><strong>{{ $format($report['refunded']) }}</strong></p>
            <p class="flex justify-between gap-3"><span class="text-muted">Débitées au registre</span><strong>{{ $format($report['ledger_refunds']) }}</strong></p>
            <p class="flex justify-between gap-3"><span class="text-muted">En attente</span><strong>{{ $report['pending'] }}</strong></p>
            <p class="flex justify-between gap-3"><span class="text-muted">À examiner</span><strong>{{ $report['to_review'] }}</strong></p>
        </div>
        @if ($report['balanced'] && $report['confirmed'] === $report['credited'])
            <p class="text-[14px] font-bold text-type-knowledge-fg" data-testid="balanced">Le registre correspond aux paiements confirmés.</p>
        @elseif ($report['balanced'])
            <p class="text-[14px] font-bold text-type-call-fg" data-testid="balanced">Des paiements confirmés ne sont pas encore crédités : voir « À examiner ».</p>
        @else
            <p class="text-[14px] font-bold text-danger" data-testid="balanced">Écart entre le registre et les paiements : à vérifier avant tout autre mouvement.</p>
        @endif
        <x-button type="button" variant="secondary" wire:click="reconcile" wire:loading.attr="disabled" wire:target="reconcile">
            <span wire:loading.remove wire:target="reconcile">Rapprocher maintenant</span>
            <span wire:loading wire:target="reconcile">Rapprochement…</span>
        </x-button>
    </section>

    @if ($toReview->isNotEmpty())
        <section aria-labelledby="examiner" class="flex flex-col gap-3 rounded-card border-2 border-danger bg-card p-4 md:p-[18px]">
            <h2 id="examiner" class="text-[18px] font-extrabold">À examiner</h2>
            @foreach ($toReview as $p)
                <div wire:key="review-{{ $p->id }}" class="flex flex-col gap-0.5 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                    <span class="text-[14px] font-extrabold">{{ $format($p->amount) }} ZAHAB — {{ $owner($p) }} — {{ $p->status->label() }}</span>
                    <span class="text-[13px]">{{ $p->note }}</span>
                    <span class="break-all text-[12px] text-muted">{{ $p->provider_reference ?? 'sans référence' }} · {{ $date($p->created_at) }}</span>
                </div>
            @endforeach
        </section>
    @endif

    @if ($canEndSandbox)
        <section aria-labelledby="fin-essais" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]" x-data="{ confirming: false }">
            <h2 id="fin-essais" class="text-[18px] font-extrabold">Terminer les essais</h2>
            <p class="text-[13px] text-muted">Avant le mode réel, retire des portefeuilles tous les ZAHAB d’essai (un mouvement par portefeuille, au journal d’administration). Le mode réel est refusé tant qu’il en reste. Aujourd’hui : {{ $format($residue) }} ZAHAB d’essai.</p>
            <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true">Terminer les essais</x-button>
            <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                <span class="text-[13px] font-semibold">Remettre à zéro les portefeuilles d’essai ?</span>
                <x-button type="button" wire:click="endSandbox" wire:loading.attr="disabled" wire:target="endSandbox" class="!px-4 !text-[14px]">Oui</x-button>
                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
            </div>
        </section>
    @endif

    <section aria-labelledby="liste" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="liste" class="text-[18px] font-extrabold">Recharges</h2>
        <div class="flex flex-col gap-1.5">
            <label for="statut" class="text-[13px] font-semibold text-ink">État</label>
            <select id="statut" wire:model.live="status" class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3 text-[15px] text-ink">
                <option value="">Tous les états</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div wire:loading.delay wire:target="status,gotoPage,nextPage,previousPage" class="text-[14px] text-muted">Chargement…</div>
        @forelse ($payments as $p)
            <div wire:key="payment-{{ $p->id }}" class="flex items-center justify-between gap-3 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                <div class="flex min-w-0 flex-col">
                    <span class="text-[14px] font-extrabold">{{ $p->status->label() }} — {{ $owner($p) }}</span>
                    <span class="text-[12px] text-muted">{{ $date($p->created_at) }} · {{ $p->environment === 'live' ? 'réel' : 'essai' }}</span>
                </div>
                <span class="shrink-0 text-[15px] font-extrabold">{{ $format($p->amount) }}</span>
            </div>
        @empty
            <p class="text-[14px] text-muted">Aucune recharge pour l’instant.</p>
        @endforelse
        {{ $payments->links() }}
    </section>
</div>
