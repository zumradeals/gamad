<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Paiement</h1>
        <p class="text-muted">Réservé au super administrateur. Les recharges de portefeuille passent par GeniusPay (Wave, Orange Money, MTN, Moov, carte). Les clés secrètes ne sont jamais réaffichées. Chaque changement est inscrit au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif
    @if ($testResult)
        <x-status :tone="$testFailed ? 'error' : 'info'">{{ $testResult }}</x-status>
    @endif

    <section aria-labelledby="etat" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="etat" class="text-[18px] font-extrabold">État</h2>
        @if ($configured)
            <p class="text-[14px] font-bold text-type-knowledge-fg">Clés complètes pour le mode {{ $currentMode === 'live' ? 'réel' : 'essai' }}.@if ($currentMode === 'sandbox') Aucun argent réel ne circule.@endif</p>
        @else
            <p class="text-[14px] font-bold text-type-call-fg">Non configuré pour le mode {{ $currentMode === 'live' ? 'réel' : 'essai' }} : la recharge est masquée.</p>
        @endif
        <p class="text-[13px] text-muted">{{ $hasWebhookSecret ? 'Secret du webhook enregistré.' : 'Secret du webhook absent : aucun rappel du prestataire ne sera accepté (les paiements se rapprochent alors à la main).' }}</p>
        <x-button :href="route('espace.paiements')" variant="secondary">Voir les paiements</x-button>
    </section>

    <section aria-labelledby="webhook" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="webhook" class="text-[18px] font-extrabold">Adresse du webhook</h2>
        <p class="text-[13px] text-muted">À déclarer dans le tableau de bord GeniusPay (événements : paiement réussi, échoué, annulé, remboursé). Le secret qu’il vous donne (commençant par whsec_) se saisit ci-dessous.</p>
        <p class="break-all rounded-button bg-type-call-bg px-3 py-2 font-mono text-[13px]" data-testid="webhook-url">{{ $webhookUrl }}</p>
    </section>

    <form wire:submit="save" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" aria-labelledby="cles">
        <h2 id="cles" class="text-[18px] font-extrabold">Mode et clés</h2>

        <div class="flex flex-col gap-1.5">
            <label for="mode" class="text-[13px] font-semibold text-ink">Mode</label>
            <select id="mode" wire:model="mode"
                    @class(['min-h-11 w-full rounded-button border bg-card px-3 text-[15px] text-ink', $errors->has('mode') ? 'border-danger' : 'border-chip-line'])>
                @foreach ($modes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('mode')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>

        @if ($mode === 'live')
            <label class="flex min-h-11 items-start gap-3 text-[14px]">
                <input type="checkbox" wire:model="confirmLive" class="mt-1 size-5">
                <span>Je confirme le passage au mode réel : l’avis juridique est obtenu et l’entité bénéficiaire est désignée.</span>
            </label>
            @error('confirmLive')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        @endif

        <x-wire-field name="baseUrl" label="Adresse de l’API" autocomplete="off" />
        <x-wire-field name="apiKey" label="Clé publique (pk_…)" autocomplete="off" />
        <x-wire-field name="apiSecret" label="Clé secrète (sk_…)" type="password" autocomplete="new-password"
                      :hint="$hasSecret ? 'Une clé secrète est enregistrée. Laissez ce champ vide pour la conserver.' : 'Aucune clé secrète enregistrée.'" />
        <x-wire-field name="webhookSecret" label="Secret du webhook (whsec_…)" type="password" autocomplete="new-password"
                      :hint="$hasWebhookSecret ? 'Un secret est enregistré. Laissez ce champ vide pour le conserver.' : 'Aucun secret enregistré.'" />
        <x-wire-field name="rechargeMin" label="Recharge minimum (ZAHAB)" inputmode="numeric" autocomplete="off" />
        <x-wire-field name="rechargeMax" label="Recharge maximum (ZAHAB)" inputmode="numeric" autocomplete="off" />

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>

    <section aria-labelledby="essai" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="essai" class="text-[18px] font-extrabold">Essayer la connexion</h2>
        <p class="text-[13px] text-muted">Interroge le compte marchand avec les clés enregistrées. Rien n’est débité.</p>
        <x-button type="button" variant="secondary" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">
            <span wire:loading.remove wire:target="testConnection">Essayer la connexion</span>
            <span wire:loading wire:target="testConnection">Essai en cours…</span>
        </x-button>
    </section>
</div>
