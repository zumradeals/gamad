@php
    $field = fn (string $name) => 'min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px] '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $notMine = ! $editing && $zumra_id !== '' && ! in_array($zumra_id, $mine, true);
@endphp

<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $editing ? route('projets.show', $projectModel) : route('espace.projets') }}" aria-label="Retour" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $editing ? 'Modifier le projet' : 'Créer un projet' }}</h1>
    </div>

    <form wire:submit="save" class="flex flex-col gap-3.5">
        @unless ($editing)
            <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Votre projet commence <strong>privé</strong> : vous seul le voyez. Vous pourrez ensuite ajouter une équipe, des étapes et, si vous le souhaitez, une présentation publique.</p>
        @endunless

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-[13px] font-bold text-muted">Quel est le nom de votre projet ?</label>
            <input id="name" type="text" wire:model="name" maxlength="{{ $nameMax }}" autocomplete="off" placeholder="Ex. Académie numérique solidaire" class="{{ $field('name') }}" @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
            @error('name')<p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.goal].length }">
            <label for="goal" class="text-[13px] font-bold text-muted">Que souhaitez-vous réaliser ?</label>
            <textarea id="goal" wire:model="goal" rows="4" maxlength="{{ $goalMax }}" x-on:input="count = [...$event.target.value].length"
                      placeholder="Ex. Former 50 jeunes aux métiers du numérique avant la fin de l’année."
                      class="min-h-[110px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal {{ $errors->has('goal') ? 'border-danger' : 'border-chip-line' }}" @if ($errors->has('goal')) aria-invalid="true" aria-describedby="goal-error" @endif></textarea>
            <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($goal) }}</span> / {{ $goalMax }}</span>
            @error('goal')<p id="goal-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            <p class="text-[12px] leading-normal text-muted">Dites ce que le projet veut obtenir, pour qui, et pour quand. Ne mettez ni téléphone ni e-mail.</p>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="domain_id" class="text-[13px] font-bold text-muted">Dans quel domaine ?</label>
            <select id="domain_id" wire:model="domain_id" class="{{ $field('domain_id') }}"><option value="">Choisir un domaine</option>@foreach ($domains as $domain)<option value="{{ $domain->id }}">{{ $domain->name }}</option>@endforeach</select>
            @error('domain_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>

        @if ($editing)
            <div class="flex flex-col gap-1.5">
                <label for="city_id" class="text-[13px] font-bold text-muted">Localité (facultative)</label>
                <x-city-picker id="city_id" model="city_id" :value="$city_id" placeholder="Aucune localité" :class="$field('city_id')" />
                @error('city_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
        @else
            <div class="flex flex-col gap-1.5">
                <label for="zumra_id" class="text-[13px] font-bold text-muted">Quelle ZUMRA accompagnera votre projet ?</label>
                <select id="zumra_id" wire:model.live="zumra_id" class="{{ $field('zumra_id') }}">
                    <option value="">Choisir une ZUMRA</option>
                    @foreach ($zumras as $option)<option value="{{ $option->id }}">{{ $option->name }}{{ in_array($option->id, $mine, true) ? ' (dont je suis membre)' : '' }}</option>@endforeach
                </select>
                @error('zumra_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>

            @if ($notMine)
                <div class="flex flex-col gap-1.5">
                    <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Vous n’êtes pas membre de cette ZUMRA : votre projet lui sera proposé. Elle doit l’accepter ; vous n’avez pas à y adhérer, et le projet reste le vôtre. Il reste privé en attendant.</p>
                    <label for="hosting_message" class="text-[13px] font-bold text-muted">Un mot pour la ZUMRA (facultatif)</label>
                    <textarea id="hosting_message" wire:model="hosting_message" rows="3" maxlength="280" class="w-full rounded-button border border-chip-line bg-card px-3.5 py-3 text-[15px]"></textarea>
                    @error('hosting_message')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @endif
        @endif

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $editing ? 'Enregistrer' : ($notMine ? 'Créer et proposer à la ZUMRA' : 'Créer le projet') }}</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
