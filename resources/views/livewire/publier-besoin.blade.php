@php
    $field = fn (string $name) => 'min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px] '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $help = \App\Enums\HelpKind::tryFrom($kind);
@endphp
<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('contributions.index') }}" aria-label="Retour aux contributions" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Publier un besoin</h1>
    </div>

    <form wire:submit="save" class="flex flex-col gap-4">
        <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Quatre questions suffisent. Ni budget ni document n’est demandé. Toute aide proposée reste volontaire.</p>

        <section class="flex flex-col gap-1.5" aria-labelledby="q1">
            <label id="q1" for="title" class="text-[15px] font-extrabold">1. De quoi avez-vous besoin ?</label>
            <input id="title" type="text" wire:model="title" maxlength="{{ \App\Services\ContributionNeeds::TITLE_MAX }}" autocomplete="off" placeholder="Ex. Achat d’ordinateurs pour la formation" class="{{ $field('title') }}" @if ($errors->has('title')) aria-invalid="true" aria-describedby="title-error" @endif>
            @error('title')<p id="title-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </section>

        <fieldset class="flex flex-col gap-2" aria-describedby="q2-aide">
            <legend class="text-[15px] font-extrabold">2. Quel type d’aide recherchez-vous ?</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($kinds as $option)
                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-button border px-3 py-2 {{ $kind === $option->value ? 'border-ink bg-soft-blue' : 'border-line bg-card' }}">
                        <input type="radio" wire:model.live="kind" value="{{ $option->value }}" class="size-5 shrink-0">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $option->badgeClasses() }}" aria-hidden="true"><x-icon :name="$option->icon()" :size="18" /></span>
                        <span class="flex min-w-0 flex-col"><span class="text-[15px] font-extrabold">{{ $option->label() }}</span><span class="text-[12px] text-muted">{{ $option->pitch() }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('kind')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            @if ($help?->disclaimer())<p id="q2-aide" class="text-[13px] font-semibold text-type-call-fg">{{ $help->disclaimer() }} Aucun paiement n’est possible dans GAMAD à ce stade.</p>@endif
        </fieldset>

        <section class="flex flex-col gap-1.5" aria-labelledby="q3">
            <label id="q3" for="beneficiary" class="text-[15px] font-extrabold">3. Pour qui est ce besoin ?</label>
            <select id="beneficiary" wire:model.live="beneficiary" class="{{ $field('beneficiary') }}">
                @foreach ($options as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            @error('beneficiary')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            <p class="text-[12px] text-muted">Vous ne publiez qu’au nom de ce que vous représentez. Un projet doit d’abord avoir publié sa présentation publique.</p>
        </section>

        <section class="flex flex-col gap-1.5" aria-labelledby="q4">
            <label id="q4" for="details" class="text-[15px] font-extrabold">4. Quelques précisions utiles</label>
            <textarea id="details" wire:model="details" rows="4" maxlength="{{ \App\Models\Publication::BODY_MAX }}" placeholder="Ex. Équiper notre espace de formation en informatique pour 30 jeunes. Ne mettez ni téléphone ni e-mail." class="min-h-[110px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal {{ $errors->has('details') ? 'border-danger' : 'border-chip-line' }}"></textarea>
            @error('details')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </section>

        <details class="rounded-card border border-line bg-card p-4">
            <summary class="min-h-11 cursor-pointer content-center text-[15px] font-extrabold">Ajouter des détails (facultatif)</summary>
            <div class="mt-3 flex flex-col gap-3">
                <div class="flex flex-col gap-1.5"><label for="domain_id" class="text-[13px] font-bold text-muted">Domaine</label>
                    <select id="domain_id" wire:model="domain_id" class="{{ $field('domain_id') }}"><option value="">Aucun domaine</option>@foreach ($domains as $domain)<option value="{{ $domain->id }}">{{ $domain->name }}</option>@endforeach</select>
                    @error('domain_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div class="flex flex-col gap-1.5"><label for="city_id" class="text-[13px] font-bold text-muted">Localité</label>
                    <x-city-picker id="city_id" model="city_id" :value="$city_id" placeholder="Aucune localité" :class="$field('city_id')" />
                    @error('city_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div class="flex flex-col gap-1.5"><label for="needed_by" class="text-[13px] font-bold text-muted">À obtenir avant le</label>
                    <input id="needed_by" type="date" wire:model="needed_by" class="{{ $field('needed_by') }}">
                    @error('needed_by')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div class="flex flex-col gap-1.5"><label for="skills" class="text-[13px] font-bold text-muted">Compétences souhaitées (trois au plus, séparées par une virgule)</label>
                    <input id="skills" type="text" wire:model="skills" maxlength="120" autocomplete="off" placeholder="Ex. Électricité, Formation" class="{{ $field('skills') }}">
                    @error('skills')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
            </div>
        </details>

        <fieldset class="flex flex-col gap-2">
            <legend class="text-[15px] font-extrabold">Qui verra ce besoin ?</legend>
            @foreach ($audiences as $value => $label)
                <label class="flex min-h-11 items-start gap-3 rounded-button border border-line px-3 py-2.5 text-[14px]"><input type="radio" wire:model="visibility" value="{{ $value }}" class="mt-0.5 size-5 shrink-0"><span>{{ $label }}</span></label>
            @endforeach
            @error('visibility')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            <p class="text-[12px] text-muted">Vos coordonnées ne sont jamais affichées. Les personnes qui répondent vous écrivent dans GAMAD, et vous décidez d’accepter ou non.</p>
        </fieldset>

        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            <x-button wire:loading.attr="disabled" wire:target="save" class="sm:flex-1"><span wire:loading.remove wire:target="save">Publier le besoin</span><span wire:loading wire:target="save">Publication…</span></x-button>
            <x-button :href="route('contributions.index')" variant="secondary" class="sm:flex-1">Annuler</x-button>
        </div>
    </form>
</div>
