@php
    $labelClass = 'text-[15px] font-extrabold';
    $field = fn ($name) => 'min-h-12 w-full rounded-button border bg-card px-3.5 text-[16px] '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $area = fn ($name) => 'w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[16px] leading-normal '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $cityLabel = $city_id !== '' ? $cities->firstWhere('id', $city_id)?->label() : null;
    $domainLabel = $domain_id !== '' ? $domains->firstWhere('id', $domain_id)?->name : null;
@endphp
<div class="flex flex-col gap-3.5" data-opportunity-form>
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        @if ($step > 1)
            <button type="button" wire:click="back" aria-label="Revenir à l’étape précédente" class="flex size-11 items-center justify-center text-ink"><x-icon name="back" /></button>
        @else
            <a href="{{ route('entreprises.opportunities', $company) }}" aria-label="Retour à mes opportunités" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        @endif
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $editing ? 'Modifier l’opportunité' : 'Publier une opportunité' }}</h1>
    </div>

    <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Au nom de <strong>{{ $company->name }}</strong>. Présentez simplement le poste, la mission ou la collaboration que vous proposez. Votre annonce sera visible sur GAMAD et sur votre page entreprise.</p>
    @unless ($verified)
        <p role="status" class="rounded-button bg-type-call-bg px-3 py-2.5 text-[13px] font-semibold text-type-call-fg">Votre entreprise n’est pas encore vérifiée par GAMAD : vous pouvez préparer un brouillon, mais pas publier.</p>
    @endunless

    <ol class="m-0 flex list-none items-center gap-2 p-0" aria-label="Étapes du formulaire">
        @foreach (['Type et description', 'Détails', 'Aperçu'] as $i => $label)
            @php $n = $i + 1; @endphp
            <li @if ($step === $n) aria-current="step" @endif class="flex min-w-0 flex-1 items-center gap-2 {{ $step === $n ? 'font-extrabold text-ink' : 'font-semibold text-muted' }}">
                <span @class(['flex size-7 shrink-0 items-center justify-center rounded-full text-[13px]', 'bg-accent text-ink' => $step === $n, 'bg-ink text-white' => $step > $n, 'border border-chip-line' => $step < $n])>@if ($step > $n)<x-icon name="check" :size="14" :stroke="3" />@else{{ $n }}@endif</span>
                <span class="truncate text-[13px]">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <form wire:submit="{{ $step === 1 ? 'next' : ($step === 2 ? 'preview' : 'publish') }}" class="flex flex-col gap-3.5" novalidate>
        @if ($step === 1)
            <section class="flex flex-col gap-4 rounded-card border border-line bg-card p-4">
                <fieldset>
                    <legend class="{{ $labelClass }} mb-2">1. Que propose votre entreprise ?</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($kinds as $k)
                            <button type="button" wire:click="chooseKind('{{ $k->value }}')" aria-pressed="{{ $kind === $k->value ? 'true' : 'false' }}" @class(['flex min-h-[72px] items-center gap-3 rounded-button bg-card px-3 py-2 text-left', 'border-2 border-link bg-soft-blue' => $kind === $k->value, 'border border-chip-line' => $kind !== $k->value])>
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $k->badgeClasses() }}"><x-icon :name="$k->icon()" :size="20" /></span>
                                <span class="min-w-0 flex-1"><span class="block text-[15px] font-extrabold">{{ $k->chooseLabel() }}</span><span class="block text-[12px] leading-snug text-muted">{{ $k->chooseHint() }}</span></span>
                                @if ($kind === $k->value)<x-icon name="check" :size="18" class="shrink-0 text-link" />@endif
                            </button>
                        @endforeach
                    </div>
                    @error('kind')<p role="alert" class="mt-2 text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </fieldset>
                <div class="flex flex-col gap-1.5">
                    <label for="title" class="{{ $labelClass }}">2. {{ $kindEnum?->titleQuestion() ?? 'Quel est le poste ou le besoin ?' }}</label>
                    <input id="title" type="text" wire:model="title" maxlength="160" placeholder="{{ $kindEnum?->titlePlaceholder() ?? 'Ex. Ingénieur réseau' }}" class="{{ $field('title') }}" @if ($errors->has('title')) aria-invalid="true" @endif>
                    @error('title')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.description].length }">
                    <label for="description" class="{{ $labelClass }}">3. Expliquez ce que vous recherchez</label>
                    <textarea id="description" wire:model="description" rows="6" maxlength="3000" x-on:input="count = [...$event.target.value].length" placeholder="Décrivez le poste, les missions et le contexte." class="min-h-[150px] {{ $area('description') }}" @if ($errors->has('description')) aria-invalid="true" @endif></textarea>
                    <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($description) }}</span> / 3000</span>
                    @error('description')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    <p class="text-[12px] text-muted">Des mots simples suffisent. Ne mettez ni téléphone ni e-mail personnel : les réponses arrivent sur GAMAD.</p>
                </div>
            </section>
            <x-button variant="accent" class="min-h-[52px] w-full" wire:loading.attr="disabled" wire:target="next">Continuer <x-icon name="next" :size="18" class="ml-1.5" /></x-button>
        @endif

        @if ($step === 2)
            <section class="flex flex-col gap-4 rounded-card border border-line bg-card p-4">
                <h2 class="text-[18px] font-extrabold">Détails de l’opportunité <span class="text-[13px] font-semibold text-muted">(tout est facultatif)</span></h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5"><label for="city_id" class="{{ $labelClass }}">Où se déroule-t-elle ?</label>
                        <select id="city_id" wire:model="city_id" class="{{ $field('city_id') }}"><option value="">Non précisé</option>@foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->label() }}</option>@endforeach</select>
                        @error('city_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                    <div class="flex flex-col gap-1.5"><label for="work_mode" class="{{ $labelClass }}">Modalité</label>
                        <select id="work_mode" wire:model="work_mode" class="{{ $field('work_mode') }}"><option value="">Non précisée</option>@foreach (\App\Models\Opportunity::WORK_MODES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="flex flex-col gap-1.5"><label for="workload" class="{{ $labelClass }}">Temps de travail</label>
                        <select id="workload" wire:model="workload" class="{{ $field('workload') }}"><option value="">Non précisé</option>@foreach (\App\Models\Opportunity::WORKLOADS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="flex flex-col gap-1.5"><label for="domain_id" class="{{ $labelClass }}">Domaine d’activité</label>
                        <select id="domain_id" wire:model="domain_id" class="{{ $field('domain_id') }}"><option value="">Aucun domaine</option>@foreach ($domains as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                </div>
                <div class="flex flex-col gap-1.5"><label for="missions" class="{{ $labelClass }}">Missions ou activités attendues</label>
                    <textarea id="missions" wire:model="missions" rows="4" maxlength="2000" placeholder="Une mission par ligne." class="{{ $area('missions') }}"></textarea>
                    @error('missions')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div class="flex flex-col gap-1.5"><label for="profile" class="{{ $labelClass }}">Profil recherché</label>
                    <textarea id="profile" wire:model="profile" rows="3" maxlength="2000" placeholder="Un point par ligne." class="{{ $area('profile') }}"></textarea></div>
                <div class="flex flex-col gap-1.5"><label for="skillsText" class="{{ $labelClass }}">Compétences recherchées</label>
                    <input id="skillsText" type="text" wire:model="skillsText" placeholder="Ex. Réseaux, Électricité, Comptabilité" class="{{ $field('skills') }}">
                    <p class="text-[12px] text-muted">Séparez-les par des virgules ({{ \App\Models\Opportunity::MAX_SKILLS }} au plus).</p>
                    @error('skills')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                @if (in_array($kind, ['job', 'internship'], true))
                    <div class="flex flex-col gap-1.5"><label for="experience" class="{{ $labelClass }}">Expérience souhaitée</label>
                        <input id="experience" type="text" wire:model="experience" maxlength="160" placeholder="Ex. Une première expérience est un plus" class="{{ $field('experience') }}"></div>
                @endif
                <div class="flex flex-col gap-1.5"><label for="qualifications" class="{{ $labelClass }}">Qualification obligatoire</label>
                    <textarea id="qualifications" wire:model="qualifications" rows="2" maxlength="1000" placeholder="Seulement si la loi ou le métier l’exige. Laissez vide sinon." class="{{ $area('qualifications') }}"></textarea></div>
                <div class="flex flex-col gap-1.5"><label for="deadline" class="{{ $labelClass }}">Date limite <span class="text-[13px] font-semibold text-muted">(facultatif)</span></label>
                    <input id="deadline" type="date" wire:model="deadline" class="{{ $field('deadline') }}">
                    @error('deadline')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div class="flex flex-col gap-1.5"><label for="conditions" class="{{ $labelClass }}">Autres précisions</label>
                    <textarea id="conditions" wire:model="conditions" rows="3" maxlength="1000" placeholder="Rémunération, avantages, durée, conditions particulières…" class="{{ $area('conditions') }}"></textarea></div>
                <fieldset class="flex flex-col gap-2"><legend class="{{ $labelClass }} mb-1">Comment répondre ?</legend>
                    @foreach (['gamad' => 'Sur GAMAD (les réponses arrivent dans votre espace)', 'external' => 'Sur le site de mon entreprise'] as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[14px]"><input type="radio" wire:model.live="apply_mode" value="{{ $value }}" class="size-5 shrink-0 accent-ink"><span>{{ $label }}</span></label>
                    @endforeach
                    @if ($apply_mode === 'external')
                        <input type="url" wire:model="external_url" maxlength="500" placeholder="https://…" aria-label="Lien de candidature" class="{{ $field('external_url') }}">
                        @error('external_url')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                        <p class="text-[12px] text-muted">Lien sécurisé (https) uniquement. Il s’ouvrira dans un nouvel onglet ; GAMAD n’aura pas accès aux réponses.</p>
                    @endif
                </fieldset>
            </section>
            <div class="grid grid-cols-[auto_1fr] gap-2">
                <x-button type="button" variant="secondary" class="min-h-[52px]" wire:click="back">Retour</x-button>
                <x-button variant="accent" class="min-h-[52px]" wire:loading.attr="disabled" wire:target="preview">Continuer <x-icon name="next" :size="18" class="ml-1.5" /></x-button>
            </div>
            <button type="button" wire:click="saveDraft" class="inline-flex min-h-11 items-center justify-center text-[14px] font-bold text-link">Enregistrer comme brouillon</button>
        @endif

        @if ($step === 3)
            <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4" aria-label="Aperçu de l’opportunité">
                <h2 class="text-[18px] font-extrabold">Aperçu de votre opportunité</h2>
                <p class="text-[13px] text-muted">Voici comment votre annonce apparaîtra sur GAMAD.</p>
                <div class="flex min-w-0 items-start gap-3">
                    <x-company-logo :company="$company" :size="52" />
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-[13px] font-bold text-muted">{{ $company->name }}</p>
                        <p class="break-words text-[18px] font-extrabold leading-tight">{{ \App\Support\Normalizer::display($title) }}</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            @if ($kindEnum)<span class="rounded-chip px-3 py-1 text-[12px] font-bold {{ $kindEnum->badgeClasses() }}">{{ $kindEnum->label() }}</span>@endif
                            @if ($work_mode !== '')<span class="rounded-chip border border-line px-3 py-1 text-[12px] font-semibold">{{ \App\Models\Opportunity::WORK_MODES[$work_mode] ?? '' }}</span>@endif
                            @if ($workload !== '')<span class="rounded-chip border border-line px-3 py-1 text-[12px] font-semibold">{{ \App\Models\Opportunity::WORKLOADS[$workload] ?? '' }}</span>@endif
                        </div>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-3 text-[13px] text-muted">@if ($cityLabel)<span class="inline-flex items-center gap-1"><x-icon name="pin" :size="15" />{{ $cityLabel }}</span>@endif @if ($deadline !== '')<span>Avant le {{ \Illuminate\Support\Carbon::parse($deadline)->locale('fr')->translatedFormat('j M Y') }}</span>@endif @if ($domainLabel)<span>{{ $domainLabel }}</span>@endif</p>
                    </div>
                </div>
                <p class="whitespace-pre-line break-words text-[14px] leading-relaxed">{{ \App\Support\Normalizer::multiline($description) }}</p>
                @if (\App\Models\Opportunity::bullets($missions))<div><p class="text-[14px] font-extrabold">Missions</p><ul class="list-disc pl-5 text-[14px]">@foreach (\App\Models\Opportunity::bullets($missions) as $line)<li class="break-words">{{ $line }}</li>@endforeach</ul></div>@endif
                @if ($skillList)<ul aria-label="Compétences recherchées" class="flex flex-wrap gap-2">@foreach ($skillList as $skill)<li class="rounded-chip bg-soft-blue px-3 py-1 text-[12px] font-semibold text-link">{{ $skill }}</li>@endforeach</ul>@endif
                <p class="text-[13px] text-muted">{{ $apply_mode === 'external' ? 'Réponse sur le site de l’entreprise : '.$external_url : 'Les réponses arrivent sur GAMAD, dans votre espace entreprise.' }}</p>
            </section>
            @if ($errors->any())
                <div role="alert" class="rounded-button border border-danger bg-card p-3 text-[14px] text-danger"><p class="font-bold">Quelque chose est à corriger avant de publier.</p><ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            @endif
            <div class="grid grid-cols-[auto_1fr] gap-2">
                <x-button type="button" variant="secondary" class="min-h-[52px]" wire:click="edit">Modifier</x-button>
                @if ($verified)
                    <x-button variant="accent" class="min-h-[52px]" wire:loading.attr="disabled" wire:target="publish"><span wire:loading.remove wire:target="publish">Publier l’opportunité</span><span wire:loading wire:target="publish">Publication…</span></x-button>
                @else
                    <x-button type="button" variant="accent" class="min-h-[52px]" wire:click="saveDraft">Enregistrer le brouillon</x-button>
                @endif
            </div>
        @endif
    </form>
</div>
