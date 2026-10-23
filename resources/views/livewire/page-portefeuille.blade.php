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
        <x-button :href="$zumra ? route('zumras.contribution', $zumra) : route('contribution')" variant="secondary" class="mt-2 md:self-start">Contribuer</x-button>
    </section>

    @if ($returnedPayment && $returnedPayment->status === \App\Enums\PaymentStatus::Completed && $returnedPayment->credit_entry_id)
        <x-status>Recharge confirmée : {{ $format($returnedPayment->amount) }} ZAHAB ajoutés.</x-status>
    @elseif ($returnedPayment && in_array($returnedPayment->status, [\App\Enums\PaymentStatus::Failed, \App\Enums\PaymentStatus::Cancelled, \App\Enums\PaymentStatus::Expired], true))
        <x-status tone="error">Le paiement n’a pas abouti : rien n’a été débité et aucun ZAHAB n’a été ajouté.</x-status>
    @endif

    @if ($pending->isNotEmpty())
        <section wire:poll.10s="refresh" aria-labelledby="attente" class="flex flex-col gap-1 rounded-card border border-line bg-type-call-bg p-4 md:p-[18px]">
            <h2 id="attente" class="text-[16px] font-extrabold text-type-call-fg">Paiement en attente de confirmation</h2>
            <p class="text-[14px]">{{ $pending->count() > 1 ? $pending->count().' recharges attendent' : 'Une recharge attend' }} la confirmation du prestataire ({{ $format($pending->sum('amount')) }} ZAHAB). Vos ZAHAB arrivent dès qu’il la confirme ; cette page se met à jour toute seule.</p>
        </section>
    @endif

    @if ($canRecharge)
        <form wire:submit="recharge" aria-labelledby="recharger" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="recharger" class="text-[18px] font-extrabold">Recharger</h2>
            @if ($sandbox)
                <p class="rounded-button border border-line bg-type-call-bg px-3 py-2 text-[13px] font-semibold text-type-call-fg" data-testid="mode-essai">Mode d’essai : le paiement est simulé, aucun argent réel n’est débité.</p>
            @endif
            <x-wire-field name="amount" label="Montant en ZAHAB" inputmode="numeric" autocomplete="off"
                          :hint="'Entre '.$format($limits[0]).' et '.$format($limits[1]).' ZAHAB. 1 ZAHAB = 1 FCFA. Vous payez sur la page sécurisée de GeniusPay (Wave, Orange Money, MTN, Moov ou carte).'" />
            <x-button wire:loading.attr="disabled" wire:target="recharge">
                <span wire:loading.remove wire:target="recharge">Recharger</span>
                <span wire:loading wire:target="recharge">Ouverture du paiement…</span>
            </x-button>
        </form>
    @endif

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
