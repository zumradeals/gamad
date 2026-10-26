<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Module ZUMRA</h1>
        <p class="text-muted">Réservé au super administrateur. Le module reste désactivé tant que vous ne l’ouvrez pas ici : aucun déploiement ni aucune migration ne l’active. Chaque changement est inscrit au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    <form wire:submit="save" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" aria-labelledby="etat-zumra">
        <h2 id="etat-zumra" class="text-[18px] font-extrabold">État du module</h2>

        <fieldset class="flex flex-col gap-2">
            <legend class="sr-only">État du module ZUMRA</legend>
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
            <p class="text-[13px] text-muted">En test privé, seuls les super administrateurs et ces comptes, à l’adresse e-mail vérifiée, voient et utilisent les ZUMRA. Les autres personnes ne voient rien.</p>
            @error('testers')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
