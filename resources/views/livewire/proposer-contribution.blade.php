@php
    $field = fn (string $name) => 'min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px] '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $help = \App\Enums\HelpKind::tryFrom($kind);
    $types = ['gamad' => 'GAMAD', 'zumra' => 'Une ZUMRA', 'project' => 'Un projet', 'company' => 'Une entreprise'];
@endphp
<div class="flex flex-col gap-3.5" x-data="{ type: @js($targetType ?: '') }">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $need ? route('publications.show', $need) : route('contributions.index') }}" aria-label="Retour" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Proposer une contribution</h1>
    </div>

    <form wire:submit="save" class="flex flex-col gap-4">
        <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Votre proposition est volontaire. Elle ne devient une contribution qu’une fois acceptée par le bénéficiaire, puis confirmée comme réalisée. Elle ne donne aucun droit de propriété, de direction ou de contrôle.</p>

        @if ($need)
            <section class="flex flex-col gap-1 rounded-card border border-line bg-card p-3" aria-label="Besoin concerné">
                <span class="text-[12px] font-extrabold uppercase tracking-wide text-muted">Vous répondez au besoin</span>
                <a href="{{ route('publications.show', $need) }}" class="break-words text-[16px] font-extrabold">{{ $need->title ?: \Illuminate\Support\Str::limit($need->body, 80) }}</a>
            </section>
        @endif

        <fieldset class="flex flex-col gap-2">
            <legend class="text-[15px] font-extrabold">1. Comment souhaitez-vous contribuer ?</legend>
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
            @if ($help?->disclaimer())<p class="text-[13px] font-semibold text-type-call-fg">{{ $help->disclaimer() }} Aucun paiement n’est possible dans GAMAD à ce stade, aucun numéro de compte ni de Mobile Money n’est demandé.</p>@endif
        </fieldset>

        <section class="flex flex-col gap-2" aria-labelledby="q2">
            <h2 id="q2" class="text-[15px] font-extrabold">2. À qui souhaitez-vous contribuer ?</h2>
            @if ($fixedBeneficiary)
                <p class="flex items-center gap-2 rounded-button border border-line bg-card px-3 py-3 text-[15px]"><x-icon :name="$beneficiaryKind->icon()" :size="20" /><span><strong>{{ $beneficiaryKind->label() }}</strong> · {{ $beneficiaryLabel }}</span></p>
            @else
                <label for="target-type" class="sr-only">Type de bénéficiaire</label>
                <select id="target-type" x-model="type" x-on:change="$wire.set('target', $event.target.value === 'gamad' ? 'gamad' : '')" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px]">
                    <option value="">Choisir un bénéficiaire</option>
                    @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <div x-show="type === 'zumra'" x-cloak><label for="target-zumra" class="sr-only">ZUMRA</label>
                    <select id="target-zumra" x-on:change="$wire.set('target', $event.target.value ? 'zumra:' + $event.target.value : '')" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px]"><option value="">Choisir une ZUMRA</option>@foreach ($zumras as $option)<option value="{{ $option->id }}" @selected($target === 'zumra:'.$option->id)>{{ $option->name }}</option>@endforeach</select></div>
                <div x-show="type === 'project'" x-cloak><label for="target-project" class="sr-only">Projet</label>
                    <select id="target-project" x-on:change="$wire.set('target', $event.target.value ? 'project:' + $event.target.value : '')" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px]"><option value="">Choisir un projet</option>@foreach ($projects as $option)<option value="{{ $option->id }}" @selected($target === 'project:'.$option->id)>{{ $option->name }}</option>@endforeach</select>
                    <p class="mt-1 text-[12px] text-muted">Seuls les projets qui ont publié leur présentation publique sont proposés.</p></div>
                <div x-show="type === 'company'" x-cloak><label for="target-company" class="sr-only">Entreprise</label>
                    <select id="target-company" x-on:change="$wire.set('target', $event.target.value ? 'company:' + $event.target.value : '')" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px]"><option value="">Choisir une entreprise</option>@foreach ($companies as $option)<option value="{{ $option->id }}" @selected($target === 'company:'.$option->id)>{{ $option->name }}</option>@endforeach</select>
                    <p class="mt-1 text-[12px] text-muted">Une contribution à une entreprise n’est ni un investissement ni une prise de participation.</p></div>
            @endif
            @error('beneficiary')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            @error('need')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </section>

        <section class="flex flex-col gap-1.5" aria-labelledby="q3">
            <label id="q3" for="offer" class="text-[15px] font-extrabold">3. Que proposez-vous concrètement ?</label>
            <textarea id="offer" wire:model="offer" rows="4" maxlength="{{ \App\Models\ContributionProposal::OFFER_MAX }}" placeholder="Ex. Deux demi-journées par mois pour accompagner les jeunes en développement web." class="min-h-[110px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal {{ $errors->has('offer') ? 'border-danger' : 'border-chip-line' }}"></textarea>
            @error('offer')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </section>

        <section class="flex flex-col gap-1.5" aria-labelledby="q4">
            <label id="q4" for="message" class="text-[15px] font-extrabold">4. Un message (facultatif)</label>
            <textarea id="message" wire:model="message" rows="3" maxlength="{{ \App\Models\ContributionProposal::MESSAGE_MAX }}" placeholder="Quelques mots pour vous présenter." class="w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] {{ $errors->has('message') ? 'border-danger' : 'border-chip-line' }}"></textarea>
            <p class="text-[12px] text-muted">Seuls vous et le bénéficiaire lisent ce message. Ne mettez ni téléphone ni e-mail : vous échangerez dans GAMAD.</p>
            @error('message')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </section>

        <details class="rounded-card border border-line bg-card p-4">
            <summary class="min-h-11 cursor-pointer content-center text-[15px] font-extrabold">Ajouter des détails (facultatif)</summary>
            <div class="mt-3 flex flex-col gap-3">
                <div class="flex flex-col gap-1.5"><label for="domain_id" class="text-[13px] font-bold text-muted">Domaine</label>
                    <select id="domain_id" wire:model="domain_id" class="{{ $field('domain_id') }}"><option value="">Aucun domaine</option>@foreach ($domains as $domain)<option value="{{ $domain->id }}">{{ $domain->name }}</option>@endforeach</select>
                    @error('domain_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div class="flex flex-col gap-1.5"><label for="city_id" class="text-[13px] font-bold text-muted">Localité</label>
                    <select id="city_id" wire:model="city_id" class="{{ $field('city_id') }}"><option value="">Aucune localité</option>@foreach ($cities as $city)<option value="{{ $city->id }}">{{ $city->label() }}</option>@endforeach</select>
                    @error('city_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
            </div>
        </details>

        <fieldset class="flex flex-col gap-2">
            <legend class="text-[15px] font-extrabold">Qui voit votre proposition ?</legend>
            <label class="flex min-h-11 items-start gap-3 rounded-button border border-line px-3 py-2.5 text-[14px]"><input type="radio" wire:model="visibility" value="private" class="mt-0.5 size-5 shrink-0"><span><strong>Privée</strong> : vous et le bénéficiaire seulement (recommandé).</span></label>
            @if ($canBePublic)
                <label class="flex min-h-11 items-start gap-3 rounded-button border border-line px-3 py-2.5 text-[14px]"><input type="radio" wire:model="visibility" value="public" class="mt-0.5 size-5 shrink-0"><span><strong>Publique</strong> : votre nom et ce que vous proposez apparaissent dans la page Contributions. Votre message reste privé.</span></label>
            @else
                <p class="text-[12px] text-muted">Une proposition adressée à une personne reste privée.</p>
            @endif
            @error('visibility')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </fieldset>

        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            <x-button wire:loading.attr="disabled" wire:target="save" class="sm:flex-1"><span wire:loading.remove wire:target="save">Envoyer ma proposition</span><span wire:loading wire:target="save">Envoi…</span></x-button>
            <x-button :href="$need ? route('publications.show', $need) : route('contributions.index')" variant="secondary" class="sm:flex-1">Annuler</x-button>
        </div>
    </form>
</div>
