@php use App\Enums\PaymentStatus; @endphp
@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
    $format = fn (int $n) => number_format($n, 0, ',', ' ');
    $statusLabels = ['recorded' => 'Enregistrée', 'awaiting_validation' => 'En attente de validation', 'refused' => 'Refusée', 'refunded' => 'Remboursée', 'withdrawn' => 'Retirée'];
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ $zumra ? route('zumras.portefeuille', $zumra) : route('portefeuille') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← {{ $zumra ? 'Portefeuille de la ZUMRA' : 'Mon portefeuille' }}</a>
        <h1 class="text-[24px] font-extrabold leading-tight">{{ $zumra ? 'Contribution de la ZUMRA' : 'Ma contribution' }}</h1>
        @unless (\App\Support\Experience::initial())
        <p class="text-muted">La contribution est facultative et sans montant imposé : un signe d’engagement qui finance les projets des ZUMRA engagées. Elle ne donne ni rang, ni visibilité, ni pouvoir de décision. <a href="{{ route('rapport-contributions') }}" class="font-semibold underline">Lire le rapport public</a>.</p>
        @endunless
    </div>

    @if ($message)
        <x-status>{{ $message }}</x-status>
    @endif
    @if ($returnedPayment && $returnedPayment->status === PaymentStatus::Completed && $returnedPayment->contribution_id)
        <x-status>Merci : votre contribution de {{ $format($returnedPayment->amount) }} ZAHAB est enregistrée.</x-status>
    @elseif ($returnedPayment && in_array($returnedPayment->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true))
        <x-status tone="error">Le paiement n’a pas abouti : rien n’a été débité et aucune contribution n’a été enregistrée.</x-status>
    @endif

    @if ($pending->isNotEmpty())
        <section wire:poll.10s="refresh" aria-labelledby="attente" class="flex flex-col gap-1 rounded-card border border-line bg-type-call-bg p-4 md:p-[18px]">
            <h2 id="attente" class="text-[16px] font-extrabold text-type-call-fg">Paiement en attente de confirmation</h2>
            <p class="text-[14px]">Votre contribution sera enregistrée dès que le prestataire confirme le paiement ; cette page se met à jour toute seule.</p>
        </section>
    @endif

    @unless (\App\Support\Experience::initial())
    <section aria-labelledby="etat" class="flex flex-col gap-1 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="etat" class="text-[13px] font-semibold text-muted">État</h2>
        @if ($engaged)
            <p class="text-[20px] font-extrabold" data-testid="etat">{{ $zumra ? 'ZUMRA engagée' : 'Contributeur' }}</p>
            <p class="text-[13px] text-muted">Jusqu’au {{ $date($engagedUntil) }}, sauf nouvelle contribution ({{ $days }} jours après chaque contribution).</p>
        @else
            <p class="text-[20px] font-extrabold" data-testid="etat">{{ $zumra ? 'Pas encore engagée' : 'Pas de contribution récente' }}</p>
            <p class="text-[13px] text-muted">{{ $zumra ? 'Une contribution rend la ZUMRA engagée pendant '.$days.' jours : elle peut alors orienter sa contribution et son projet devient finançable si elle est aussi reconnue par GAMAD.' : 'Une contribution vous rend contributeur pendant '.$days.' jours.' }}</p>
        @endif
    </section>

    @endunless

    @unless (\App\Support\Experience::initial())
    <form wire:submit="contribute" aria-labelledby="contribuer" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="contribuer" class="text-[18px] font-extrabold">Contribuer</h2>
        @if ($sandbox)
            <p class="rounded-button border border-line bg-type-call-bg px-3 py-2 text-[13px] font-semibold text-type-call-fg" data-testid="mode-essai">Mode d’essai : aucun argent réel n’est en jeu.</p>
        @endif

        <x-wire-field name="amount" label="Montant en ZAHAB" inputmode="numeric" autocomplete="off"
                      :hint="'Au moins '.$format($minimum).' ZAHAB (1 ZAHAB = 1 FCFA). Le montant est libre et reste privé.'" />

        <div class="flex flex-col gap-1.5">
            <label for="orientation" class="text-[13px] font-semibold text-ink">Orienter ma contribution</label>
            <select id="orientation" wire:model="orientation" class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3 text-[15px] text-ink">
                <option value="fund">Le fonds commun (le Président affecte)</option>
                @foreach ($zumraOptions as $z)
                    <option value="zumra:{{ $z->id }}">ZUMRA engagée : {{ $z->name }}</option>
                @endforeach
                @foreach ($projectOptions as $p)
                    <option value="project:{{ $p->id }}">Projet : {{ $p->name }}</option>
                @endforeach
            </select>
            <p class="text-[12px] text-muted">Une orientation est un souhait : le Président affecte le fonds, et ne finance que les projets de ZUMRA engagées et reconnues.</p>
        </div>

        <fieldset class="flex flex-col gap-1">
            <legend class="text-[13px] font-semibold text-ink">Payer</legend>
            <label class="flex min-h-11 items-center gap-3 text-[14px]">
                <input type="radio" wire:model="source" value="wallet" class="h-5 w-5">
                <span>Depuis le portefeuille (solde : {{ $format($balance) }} ZAHAB)</span>
            </label>
            @if ($directAvailable)
                <label class="flex min-h-11 items-center gap-3 text-[14px]">
                    <input type="radio" wire:model="source" value="direct" class="h-5 w-5">
                    <span>Par Mobile Money, directement (page sécurisée de GeniusPay)</span>
                </label>
            @endif
        </fieldset>

        <x-button wire:loading.attr="disabled" wire:target="contribute">
            <span wire:loading.remove wire:target="contribute">Contribuer</span>
            <span wire:loading wire:target="contribute">Un instant…</span>
        </x-button>
    </form>

    <section aria-labelledby="choix" class="flex flex-col gap-1 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="choix" class="text-[18px] font-extrabold">Mes choix</h2>
        <label class="flex min-h-11 items-start gap-3 text-[14px]">
            <input type="checkbox" class="mt-1 h-5 w-5" @checked($wallet->monthly_reminder) wire:change="setReminder($event.target.checked)">
            <span>Me rappeler chaque mois : une demande de contribution apparaît dans « À faire maintenant » tant que je n’ai pas contribué ce mois-ci.</span>
        </label>
        @unless ($zumra)
            <label class="flex min-h-11 items-start gap-3 text-[14px]">
                <input type="checkbox" class="mt-1 h-5 w-5" @checked($wallet->show_badge) wire:change="setBadge($event.target.checked)">
                <span>Afficher le badge « Contributeur » sur mon profil (sans montant, tant que je suis contributeur).</span>
            </label>
        @endunless
    </section>

    @else
        <p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Les nouvelles contributions sont en pause. Vos contributions et les demandes en attente restent consultables ci-dessous.</p>
    @endunless

    <section aria-labelledby="impact" class="flex flex-col gap-1 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="impact" class="text-[18px] font-extrabold">Mon rapport d’impact</h2>
        @if ($impact['count'] > 0)
            <p class="text-[14px]" data-testid="impact"><strong>{{ $format($impact['total']) }} ZAHAB</strong> donnés en {{ $impact['count'] }} contribution{{ $impact['count'] > 1 ? 's' : '' }}, sur {{ $impact['months'] }} mois{{ $impact['since'] ? ', depuis le '.$date($impact['since']) : '' }}.</p>
            <p class="text-[13px] text-muted">Seuls des chiffres réels sont affichés. L’emploi du fonds est détaillé, sans aucun nom, dans le rapport public.</p>
        @else
            <p class="text-[14px] text-muted">Aucune contribution pour l’instant.</p>
        @endif
    </section>

    <section aria-labelledby="historique" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="historique" class="text-[18px] font-extrabold">Historique</h2>
        @forelse ($history as $c)
            <div wire:key="contribution-{{ $c->id }}" class="flex items-center justify-between gap-3 border-b border-line-soft pb-3 last:border-b-0 last:pb-0">
                <div class="flex min-w-0 flex-col">
                    <span class="text-[14px] font-extrabold">{{ $statusLabels[$c->status] ?? $c->status }}</span>
                    <span class="text-[12px] text-muted">{{ $date($c->created_at) }} · {{ $c->source === 'wallet' ? 'portefeuille' : 'Mobile Money' }}</span>
                    @if ($c->isAwaiting())
                        <button type="button" wire:click="withdraw('{{ $c->id }}')" class="mt-1 inline-flex min-h-11 items-center self-start text-[13px] font-semibold underline">Retirer la demande</button>
                    @endif
                </div>
                <span class="shrink-0 text-[15px] font-extrabold">{{ $format($c->amount) }}</span>
            </div>
        @empty
            <p class="text-[14px] text-muted">Aucune contribution pour l’instant.</p>
        @endforelse
        {{ $history->links() }}
    </section>
</div>
