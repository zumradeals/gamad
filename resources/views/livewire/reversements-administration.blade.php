@php
    $format = fn (int $n) => number_format($n, 0, ',', ' ');
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y à H:i');
@endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.paiements') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paiements</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Reversements</h1>
        <p class="text-muted">Réservé au super administrateur. Aucun compte n’est nommé : vous voyez la somme, l’opérateur, le titulaire indiqué et le numéro masqué. Lisez le numéro entier seulement pour verser la somme ; chaque lecture est inscrite au journal.</p>
    </div>

    @if ($mode === 'sandbox')
        <p class="rounded-button border border-line bg-type-call-bg px-3 py-2 text-[13px] font-semibold text-type-call-fg">Mode d’essai : ces reversements sont simulés, aucun versement réel n’est à faire. Marquez-les comme versés pour tester le parcours.</p>
    @endif

    @if ($status)<x-status>{{ $status }}</x-status>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
    @endif

    <section aria-label="Sommes" class="grid grid-cols-2 gap-3 rounded-card border border-line bg-card p-4">
        <div><span class="block text-[12px] text-muted">À verser</span><span class="text-[20px] font-extrabold" data-testid="total-a-verser">{{ $format($totals['requested']) }} ZAHAB</span><span class="block text-[12px] text-muted">{{ $totals['requested_count'] }} demande(s)</span></div>
        <div><span class="block text-[12px] text-muted">Déjà versés</span><span class="text-[20px] font-extrabold">{{ $format($totals['paid']) }} ZAHAB</span><span class="block text-[12px] text-muted">{{ $totals['paid_count'] }} reversement(s)</span></div>
    </section>

    <nav aria-label="Reversements" class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('a-verser')" @class(['min-h-11 rounded-chip px-4 text-[13px] font-bold', 'bg-ink text-white' => $tab === 'a-verser', 'border border-line bg-card' => $tab !== 'a-verser'])>À verser ({{ $openCount }})</button>
        <button type="button" wire:click="setTab('traites')" @class(['min-h-11 rounded-chip px-4 text-[13px] font-bold', 'bg-ink text-white' => $tab === 'traites', 'border border-line bg-card' => $tab !== 'traites'])>Traités</button>
    </nav>

    @forelse ($payouts as $payout)
        <section wire:key="payout-{{ $payout->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
            <div class="flex items-baseline justify-between gap-2">
                <strong class="text-[18px]">{{ $format($payout->amount) }} ZAHAB</strong>
                <span class="text-[12px] font-bold text-muted">{{ $payout->status->label() }} · {{ $payout->wallet?->isPerson() ? 'personne' : 'ZUMRA' }}{{ $payout->environment === 'sandbox' ? ' · essai' : '' }}</span>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[14px]">
                <dt class="text-muted">Opérateur</dt><dd>{{ $operators[$payout->operator] ?? $payout->operator }}</dd>
                <dt class="text-muted">Titulaire</dt><dd class="break-words">{{ $payout->holder_name }}</dd>
                <dt class="text-muted">Numéro</dt><dd>{{ $revealed[$payout->id] ?? $payout->maskedPhone() }}</dd>
                <dt class="text-muted">Demandé</dt><dd>{{ $date($payout->created_at) }}</dd>
                @if ($payout->provider_reference)<dt class="text-muted">Référence</dt><dd class="break-words">{{ $payout->provider_reference }}</dd>@endif
                @if ($payout->decline_reason)<dt class="text-muted">Motif</dt><dd>{{ $payout->decline_reason }}</dd>@endif
            </dl>
            @if ($payout->isOpen())
                @unless (isset($revealed[$payout->id]))
                    <div><x-button type="button" variant="secondary" wire:click="reveal('{{ $payout->id }}')">Lire le numéro entier</x-button></div>
                @endunless
                <label for="ref-{{ $payout->id }}" class="text-[13px] font-bold">Référence du versement (facultative)</label>
                <input id="ref-{{ $payout->id }}" type="text" wire:model="references.{{ $payout->id }}" maxlength="100" autocomplete="off" class="min-h-12 rounded-button border border-chip-line px-3 text-[15px]">
                <x-button type="button" wire:click="markPaid('{{ $payout->id }}')" wire:loading.attr="disabled" wire:target="markPaid">J’ai versé la somme</x-button>
                <label for="motif-{{ $payout->id }}" class="text-[13px] font-bold">Motif d’un refus</label>
                <textarea id="motif-{{ $payout->id }}" wire:model="reasons.{{ $payout->id }}" rows="2" maxlength="280" class="rounded-button border border-chip-line px-3 py-2 text-[15px]"></textarea>
                <x-button type="button" variant="secondary" wire:click="decline('{{ $payout->id }}')" wire:loading.attr="disabled" wire:target="decline">Refuser et rendre la somme</x-button>
            @endif
        </section>
    @empty
        <p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Aucun reversement dans cette liste.</p>
    @endforelse
</div>
