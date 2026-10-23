@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
    $format = fn (int $n) => number_format($n, 0, ',', ' ');
    $month = fn (string $ym) => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym.'-01')->locale('fr')->translatedFormat('F Y');
    $statusLabels = ['recorded' => 'Enregistrée', 'awaiting_validation' => 'À valider', 'refused' => 'Refusée', 'refunded' => 'Remboursée', 'withdrawn' => 'Retirée'];
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace') }}#administration" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Mon espace</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Contributions</h1>
        <p class="text-muted">Réservé au super administrateur : vous seul lisez les noms des contributeurs, avec l’auteur de chaque contribution (D-170). Mode {{ $mode === 'live' ? 'réel' : 'essai' }}. Chaque validation et chaque affectation est inscrite au journal d’administration. <a href="{{ route('rapport-contributions') }}" class="font-semibold underline">Voir le rapport public</a>.</p>
    </div>

    @if ($message)
        <x-status :tone="$messageIsError ? 'error' : 'info'">{{ $message }}</x-status>
    @endif

    <section aria-labelledby="fonds" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="fonds" class="text-[18px] font-extrabold">Fonds de contribution</h2>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Reçu</span><strong data-testid="recu">{{ $format($fund['received']) }}</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Remboursé (sorti du fonds)</span><strong>{{ $format($fund['refunded']) }}</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Affecté à des projets</span><strong data-testid="affecte">{{ $format($fund['allocated']) }}</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Dont versé</span><strong>{{ $format($fund['paid']) }}</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Reste à affecter</span><strong data-testid="disponible">{{ $format($fund['available']) }}</strong></p>
        <p class="flex justify-between gap-3 text-[14px]"><span class="text-muted">Contributeurs</span><strong>{{ $fund['contributors'] }}</strong></p>
        <p class="text-[12px] text-muted">Contributions directes confirmées par le prestataire : {{ $format($reconciliation['contributions']) }} — enregistrées au fonds : {{ $format($reconciliation['contributions_recorded']) }}.@if ($reconciliation['contributions'] !== $reconciliation['contributions_recorded']) <strong class="text-danger">Écart à vérifier.</strong>@endif</p>
    </section>

    @if ($awaiting->isNotEmpty())
        <section aria-labelledby="valider" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-card p-4 md:p-[18px]">
            <h2 id="valider" class="text-[18px] font-extrabold">Dépenses de ZUMRA à valider</h2>
            <p class="text-[13px] text-muted">Au-delà de {{ $format($threshold) }} ZAHAB, une dépense d’un portefeuille de ZUMRA demande votre validation (D-168). Rien n’est débité avant.</p>
            @foreach ($awaiting as $c)
                <div wire:key="await-{{ $c->id }}" class="flex flex-col gap-2 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                    <span class="text-[14px] font-extrabold">{{ $format($c->amount) }} ZAHAB — {{ $awaitingOwners[$c->wallet->owner_id] ?? 'ZUMRA supprimée' }}</span>
                    <span class="text-[12px] text-muted">Demandée le {{ $date($c->created_at) }}</span>
                    <div class="flex flex-wrap gap-2">
                        <x-button type="button" wire:click="validateContribution('{{ $c->id }}')" wire:loading.attr="disabled" class="!px-4 !text-[14px]">Valider</x-button>
                        <x-button type="button" variant="secondary" wire:click="refuseContribution('{{ $c->id }}')" wire:loading.attr="disabled" class="!px-4 !text-[14px]">Refuser</x-button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section aria-labelledby="affecter" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="affecter" class="text-[18px] font-extrabold">Affecter le fonds</h2>
        <p class="text-[13px] text-muted">Seuls les projets de ZUMRA à la fois <strong>engagées</strong> et <strong>reconnues</strong> sont finançables (D-167). Le versement se fait hors de la plateforme : inscrivez-le ensuite.</p>
        @if ($orientations !== [])
            <div class="flex flex-col gap-1" data-testid="orientations">
                <span class="text-[13px] font-semibold">Orientations reçues (sans nom de personne)</span>
                @foreach ($orientations as $o)
                    <span class="flex justify-between gap-3 text-[13px]"><span>{{ $o['type'] === 'zumra' ? 'ZUMRA' : 'Projet' }} : {{ $o['name'] }}</span><strong>{{ $format($o['total']) }}</strong></span>
                @endforeach
            </div>
        @endif
        @if ($projects->isEmpty())
            <p class="text-[14px] text-muted">Aucun projet finançable pour l’instant : il faut une ZUMRA reconnue ayant contribué dans les derniers jours.</p>
        @else
            <form wire:submit="allocate" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                    <label for="projectId" class="text-[13px] font-semibold text-ink">Projet</label>
                    <select id="projectId" wire:model="projectId" @class(['min-h-11 w-full rounded-button border bg-card px-3 text-[15px] text-ink', $errors->has('projectId') ? 'border-danger' : 'border-chip-line'])>
                        <option value="">Choisir un projet</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} — {{ $zumraNames[$p->zumra_id] }}</option>
                        @endforeach
                    </select>
                    @error('projectId')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
                <x-wire-field name="amount" label="Montant en ZAHAB" inputmode="numeric" autocomplete="off" :hint="'Au plus '.$format($fund['available']).' ZAHAB.'" />
                <x-wire-field name="purpose" label="Objet (public)" autocomplete="off" hint="Publié dans le rapport, sans nom de personne." />
                <x-button wire:loading.attr="disabled" wire:target="allocate">
                    <span wire:loading.remove wire:target="allocate">Décider l’affectation</span>
                    <span wire:loading wire:target="allocate">Enregistrement…</span>
                </x-button>
            </form>
        @endif
    </section>

    <section aria-labelledby="affectations" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="affectations" class="text-[18px] font-extrabold">Affectations</h2>
        @forelse ($allocations as $a)
            <div wire:key="alloc-{{ $a->id }}" class="flex flex-col gap-1 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                <span class="text-[14px] font-extrabold">{{ $format($a->amount) }} ZAHAB — {{ $a->project_name }} ({{ $a->zumra_name }})</span>
                <span class="text-[13px]">{{ $a->purpose }}</span>
                <span class="text-[12px] text-muted">Décidée le {{ $date($a->created_at) }} · {{ $a->isPaid() ? 'versée le '.$date($a->paid_at) : 'à verser' }}</span>
                @unless ($a->isPaid())
                    <x-button type="button" variant="secondary" wire:click="markPaid('{{ $a->id }}')" wire:loading.attr="disabled" class="self-start !px-4 !text-[14px]">J’ai versé cette somme</x-button>
                @endunless
            </div>
        @empty
            <p class="text-[14px] text-muted">Aucune affectation pour l’instant.</p>
        @endforelse
    </section>

    <section aria-labelledby="mois" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="mois" class="text-[18px] font-extrabold">Par mois</h2>
        @forelse ($monthly as $ym => $total)
            <p wire:key="mois-{{ $ym }}" class="flex justify-between gap-3 text-[14px]"><span class="capitalize">{{ $month($ym) }}</span><strong>{{ $format($total) }}</strong></p>
        @empty
            <p class="text-[14px] text-muted">Aucune contribution pour l’instant.</p>
        @endforelse
    </section>

    <section aria-labelledby="recentes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="recentes" class="text-[18px] font-extrabold">Dernières contributions</h2>
        @forelse ($recent as $c)
            <div wire:key="recent-{{ $c->id }}" class="flex items-center justify-between gap-3 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                <div class="flex min-w-0 flex-col">
                    <span class="text-[14px] font-extrabold">{{ $owners[$c->wallet->owner_id] ?? 'Titulaire supprimé' }}</span>
                    <span class="text-[12px] text-muted">{{ $date($c->created_at) }} · {{ $statusLabels[$c->status] ?? $c->status }} · {{ $c->source === 'wallet' ? 'portefeuille' : 'Mobile Money' }} · {{ $c->environment === 'live' ? 'réel' : 'essai' }}</span>
                </div>
                <span class="shrink-0 text-[15px] font-extrabold">{{ $format($c->amount) }}</span>
            </div>
        @empty
            <p class="text-[14px] text-muted">Aucune contribution pour l’instant.</p>
        @endforelse
    </section>
</div>
