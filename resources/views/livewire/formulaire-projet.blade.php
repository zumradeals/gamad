<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $editing ? route('projets.show', $projectModel) : route('zumras.show', ['zumra' => $zumra, 'onglet' => 'projets']) }}" aria-label="Retour" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $editing ? 'Modifier le projet' : 'Créer un projet' }}</h1>
    </div>

    <form wire:submit="save" class="flex flex-col gap-3.5">
        <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Projet de <strong>{{ $zumra->name }}</strong>. Sa page sera publique : tout le monde pourra la lire, même sans compte.</p>

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-[13px] font-bold text-muted">Nom du projet</label>
            <input id="name" type="text" wire:model="name" maxlength="{{ $nameMax }}" autocomplete="off" placeholder="Ex. Uniformes scolaires de la rentrée"
                   @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('name') ? 'border-danger' : 'border-chip-line'])
                   @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
            @error('name')
                <p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.goal].length }">
            <label for="goal" class="text-[13px] font-bold text-muted">But du projet</label>
            <textarea id="goal" wire:model="goal" rows="5" maxlength="{{ $goalMax }}" x-on:input="count = [...$event.target.value].length"
                      placeholder="Ex. Coudre 200 uniformes pour trois écoles du quartier avant la rentrée."
                      @class(['min-h-[120px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal', $errors->has('goal') ? 'border-danger' : 'border-chip-line'])
                      @if ($errors->has('goal')) aria-invalid="true" aria-describedby="goal-error" @endif></textarea>
            <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($goal) }}</span> / {{ $goalMax }}</span>
            @error('goal')
                <p id="goal-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
            <p class="text-[12px] leading-normal text-muted">Dites ce que le projet veut obtenir, pour qui, et pour quand. Ne mettez ni téléphone ni e-mail. Le texte reste un texte simple : les adresses web n’y sont pas cliquables.</p>
        </div>

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $editing ? 'Enregistrer' : 'Créer le projet' }}</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
