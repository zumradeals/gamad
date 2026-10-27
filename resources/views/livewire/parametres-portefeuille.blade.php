<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Portefeuilles</h1>
        <p class="text-muted">Réservé au super administrateur. Un portefeuille compte des ZAHAB (1 ZAHAB = 1 FCFA). Tant qu’ils sont fermés, personne ne les voit. Les recharges par Mobile Money viennent avec la prochaine étape du lot. Chaque changement est inscrit au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    <form wire:submit="save" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" aria-labelledby="etat">
        <h2 id="etat" class="text-[18px] font-extrabold">Service Zahab Wallet, état et plafonds</h2>
        <p class="text-[13px] leading-normal text-muted">Le service Zahab Wallet est le portefeuille que chaque personne (et chaque ZUMRA, par ses responsables) active si elle le veut, depuis Mon espace › Services GAMAD. Il est <strong>désactivé par défaut</strong> et aucun déploiement ne l’ouvre. Le mode d’essai ou réel de GeniusPay, et les clés, se règlent dans Paramètres › Paiement.</p>

        <div class="flex flex-col gap-1.5">
            <label for="moduleState" class="text-[13px] font-semibold text-ink">État du service Zahab Wallet</label>
            <select id="moduleState" wire:model="moduleState" @class(['min-h-11 w-full rounded-button border bg-card px-3 text-[15px] text-ink', $errors->has('moduleState') ? 'border-danger' : 'border-chip-line'])>
                @foreach ($moduleStates as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            @error('moduleState')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="testers" class="text-[13px] font-semibold text-ink">Comptes testeurs (adresses e-mail, une par ligne)</label>
            <textarea id="testers" wire:model="testers" rows="3" @class(['w-full rounded-button border bg-card px-3 py-2 text-[15px]', $errors->has('testers') ? 'border-danger' : 'border-chip-line'])></textarea>
            @error('testers')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>
        <x-wire-field name="payoutMin" label="Reversement minimum (ZAHAB)" inputmode="numeric" autocomplete="off" />

        <div class="flex flex-col gap-1.5">
            <label for="enabled" class="text-[13px] font-semibold text-ink">Ancien réglage « Portefeuilles » (ancienne expérience seulement)</label>
            <select id="enabled" wire:model="enabled"
                    @class(['min-h-11 w-full rounded-button border bg-card px-3 text-[15px] text-ink', $errors->has('enabled') ? 'border-danger' : 'border-chip-line'])>
                @foreach ($states as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('enabled')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>

        <x-wire-field name="maxPerson" label="Solde maximum d’une personne (ZAHAB)" inputmode="numeric" autocomplete="off" />
        <x-wire-field name="maxZumra" label="Solde maximum d’une ZUMRA (ZAHAB)" inputmode="numeric" autocomplete="off" />
        <x-wire-field name="contributionMin" label="Contribution minimum (ZAHAB)" inputmode="numeric" autocomplete="off" />
        <x-wire-field name="engagementDays" label="Durée d’engagement après une contribution (jours)" inputmode="numeric" autocomplete="off" hint="Une personne ou une ZUMRA reste « contributeur » ou « engagée » ce nombre de jours après sa dernière contribution." />
        <x-wire-field name="validationThreshold" label="Dépense d’une ZUMRA soumise à votre validation au-delà de (ZAHAB)" inputmode="numeric" autocomplete="off" />

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
