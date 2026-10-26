<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Module Projets</h1>
        <p class="text-muted">Réservé au super administrateur. Les Projets sont accueillis par les ZUMRA : ils ne s’ouvrent jamais sans elles. Tant que vous ne choisissez rien, ils suivent le module ZUMRA, comme avant. Aucune donnée n’est supprimée et aucun projet existant n’est modifié. Chaque changement est inscrit au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    <form wire:submit="save" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" aria-labelledby="etat-projets">
        <h2 id="etat-projets" class="text-[18px] font-extrabold">État du module</h2>
        <fieldset class="flex flex-col gap-2">
            <legend class="sr-only">État du module Projets</legend>
            @foreach ($states as $value => $label)
                <label class="flex min-h-11 items-start gap-3 rounded-button border border-line px-3 py-2.5 text-[15px]">
                    <input type="radio" wire:model="state" value="{{ $value }}" class="mt-1 size-5 shrink-0">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
            @error('state')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </fieldset>
        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
