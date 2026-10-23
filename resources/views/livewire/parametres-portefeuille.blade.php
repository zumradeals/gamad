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
        <h2 id="etat" class="text-[18px] font-extrabold">État et plafonds</h2>

        <div class="flex flex-col gap-1.5">
            <label for="enabled" class="text-[13px] font-semibold text-ink">Portefeuilles</label>
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
