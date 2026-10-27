<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Service GAMAD Publicité</h1>
        <p class="text-muted">Réservé au super administrateur. Le service reste désactivé tant que vous ne l’ouvrez pas ici : aucun déploiement ni aucune migration ne l’active. Son ouverture n’active aucune diffusion payante. Chaque changement est inscrit au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    <form wire:submit="save" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" aria-labelledby="etat-publicite">
        <h2 id="etat-publicite" class="text-[18px] font-extrabold">État du service</h2>

        <fieldset class="flex flex-col gap-2">
            <legend class="sr-only">État du service GAMAD Publicité</legend>
            @foreach ($states as $value => $label)
                <label class="flex min-h-11 items-start gap-3 rounded-button border border-line px-3 py-2.5 text-[15px]">
                    <input type="radio" wire:model="state" value="{{ $value }}" class="mt-1 size-5 shrink-0">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
            @error('state')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </fieldset>

        <div class="flex flex-col gap-1.5">
            <label for="testeurs" class="text-[13px] font-semibold text-ink">Comptes testeurs (une adresse e-mail par ligne)</label>
            <textarea id="testeurs" wire:model="testers" rows="4" maxlength="2000" autocomplete="off" @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('testers') ? 'border-danger' : 'border-chip-line'])></textarea>
            <p class="text-[13px] text-muted">En test privé, seuls les super administrateurs et ces comptes, à l’adresse e-mail vérifiée, voient et utilisent GAMAD Publicité. Les autres personnes ne voient rien.</p>
            @error('testers')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>

        <fieldset class="flex flex-col gap-2 border-t border-line-soft pt-3">
            <legend class="text-[16px] font-extrabold">Diffusion payante</legend>
            <p class="text-[13px] text-muted">Fermée par défaut : tant qu’elle est fermée, aucune annonce n’est affichée à personne, même si le service est public. Ne l’ouvrez qu’une fois le dispositif d’encaissement validé.</p>
            @foreach (['closed' => 'Fermée : aucune annonce n’est affichée', 'open' => 'Ouverte : les campagnes acceptées sont diffusées (dispositif d’encaissement validé)'] as $value => $label)
                <label class="flex min-h-11 items-start gap-3 rounded-button border border-line px-3 py-2.5 text-[15px]"><input type="radio" wire:model="diffusion" value="{{ $value }}" class="mt-1 size-5 shrink-0"><span>{{ $label }}</span></label>
            @endforeach
            @error('diffusion')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            <label class="flex min-h-11 items-start gap-3 text-[14px]"><input type="checkbox" wire:model="paymentConfirmed" class="mt-0.5 size-5 shrink-0"><span>Je confirme que le dispositif d’encaissement est validé (requis pour ouvrir la diffusion).</span></label>
            @error('paymentConfirmed')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </fieldset>

        <div class="flex flex-col gap-1.5 border-t border-line-soft pt-3">
            <label for="min-audience" class="text-[13px] font-semibold text-ink">Audience minimale d’une campagne personnalisée (profils consentants)</label>
            <input id="min-audience" type="number" min="20" max="100000" wire:model="minAudience" @class(['min-h-11 w-full max-w-[160px] rounded-button border bg-card px-3.5 text-[15px]', $errors->has('minAudience') ? 'border-danger' : 'border-chip-line'])>
            <p class="text-[13px] text-muted">Protège l’identité des membres : en dessous, une campagne personnalisée n’est pas envoyée à la modération. 100 par défaut ; 20 au minimum.</p>
            @error('minAudience')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
