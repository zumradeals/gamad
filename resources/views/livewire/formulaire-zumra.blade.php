<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $editing ? route('zumras.show', $zumra) : route('zumras.index') }}" aria-label="Retour" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $editing ? 'Modifier la ZUMRA' : 'Créer une ZUMRA' }}</h1>
    </div>

    <form wire:submit="save" class="flex flex-col gap-3.5">
        @unless ($editing)
            <p class="text-[14px] leading-normal text-muted">Une ZUMRA est une communauté qui apprend, travaille et agit ensemble autour d’<strong>un seul domaine</strong>. Vous en serez le responsable principal ; vous pouvez la lancer seul et former l’équipe ensuite. Le nom et le domaine ne pourront plus changer.</p>
        @endunless

        @if ($editing)
            <dl class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
                <div><dt class="text-[12px] font-bold text-muted">Nom</dt><dd class="text-[15px] font-extrabold">{{ $zumra->name }}</dd></div>
                <div><dt class="text-[12px] font-bold text-muted">Domaine</dt><dd class="text-[15px] font-extrabold">{{ $zumra->domain->name }}</dd></div>
                <p class="text-[12px] text-muted">Le nom et le domaine ne changent pas après la création.</p>
            </dl>
        @else
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-[13px] font-bold text-muted">Nom de la ZUMRA</label>
                <input id="name" type="text" wire:model="name" maxlength="{{ $nameMax }}" autocomplete="off" placeholder="Ex. Couture solidaire"
                       @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('name') ? 'border-danger' : 'border-chip-line'])
                       @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                @error('name')
                    <p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-[13px] font-bold text-muted">Nature de la ZUMRA</legend>
                @foreach ($spheres as $option)
                    <label for="sphere-{{ $option->value }}" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-button border border-chip-line px-3 has-[:checked]:border-ink has-[:checked]:bg-soft-blue">
                        <input id="sphere-{{ $option->value }}" type="radio" wire:model="sphere" value="{{ $option->value }}" class="size-5 accent-ink">
                        <span class="text-[15px] font-semibold">{{ $option->label() }}</span>
                    </label>
                @endforeach
                @error('sphere')
                    <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="flex flex-col gap-1.5">
                <label for="domain_id" class="text-[13px] font-bold text-muted">Domaine d’activité (un seul)</label>
                <select id="domain_id" wire:model="domain_id"
                        @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('domain_id') ? 'border-danger' : 'border-chip-line'])
                        @if ($errors->has('domain_id')) aria-invalid="true" aria-describedby="domain-error" @endif>
                    <option value="">Choisir un domaine</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                    @endforeach
                </select>
                @error('domain_id')
                    <p id="domain-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="flex flex-col gap-1.5">
            <label for="city_id" class="text-[13px] font-bold text-muted">Ville (facultative)</label>
            <select id="city_id" wire:model="city_id"
                    @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('city_id') ? 'border-danger' : 'border-chip-line'])>
                <option value="">Aucune ville</option>
                @foreach ($cities as $city)
                    <option value="{{ $city->id }}">{{ $city->label() }}</option>
                @endforeach
            </select>
            @error('city_id')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>

        <fieldset class="flex flex-col gap-2">
            <legend class="mb-1 text-[13px] font-bold text-muted">Où la ZUMRA se retrouve</legend>
            @foreach ($formats as $option)
                <label for="format-{{ $option->value }}" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-button border border-chip-line px-3 has-[:checked]:border-ink has-[:checked]:bg-soft-blue">
                    <input id="format-{{ $option->value }}" type="radio" wire:model="format" value="{{ $option->value }}" class="size-5 accent-ink">
                    <span class="text-[15px] font-semibold">{{ $option->label() }}</span>
                </label>
            @endforeach
            @error('format')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </fieldset>

        <fieldset class="flex flex-col gap-2">
            <legend class="mb-1 text-[13px] font-bold text-muted">Comment on rejoint la ZUMRA</legend>
            @foreach ($joinModes as $option)
                <label for="join-mode-{{ $option->value }}" class="flex min-h-12 cursor-pointer items-start gap-3 rounded-button border border-chip-line px-3 py-2.5 has-[:checked]:border-ink has-[:checked]:bg-soft-blue">
                    <input id="join-mode-{{ $option->value }}" type="radio" wire:model="join_mode" value="{{ $option->value }}" class="mt-0.5 size-5 shrink-0 accent-ink">
                    <span class="flex flex-col"><span class="text-[15px] font-semibold">{{ $option->label() }}</span><span class="text-[13px] leading-snug text-muted">{{ $option->hint() }}</span></span>
                </label>
            @endforeach
            @error('join_mode')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.mission].length }">
            <label for="mission" class="text-[13px] font-bold text-muted">Description courte</label>
            <textarea id="mission" wire:model="mission" rows="5" maxlength="{{ $missionMax }}" x-on:input="count = [...$event.target.value].length"
                      placeholder="Ex. Former des couturières et couturiers, et produire des uniformes scolaires à prix juste."
                      @class(['min-h-[120px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal', $errors->has('mission') ? 'border-danger' : 'border-chip-line'])
                      @if ($errors->has('mission')) aria-invalid="true" aria-describedby="mission-error" @endif></textarea>
            <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($mission) }}</span> / {{ $missionMax }}</span>
            @error('mission')
                <p id="mission-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
            <p class="text-[12px] leading-normal text-muted">Dites ce que la ZUMRA veut faire et pour qui. Ne mettez ni téléphone ni e-mail. Le texte reste un texte simple : les adresses web n’y sont pas cliquables.</p>
        </div>

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $editing ? 'Enregistrer' : 'Créer la ZUMRA' }}</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
