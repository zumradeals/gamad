@php
    $input = fn (string $name) => 'min-h-12 w-full rounded-button border bg-soft-blue px-3 text-[15px] outline-none focus:outline focus:outline-[3px] focus:outline-link '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $err = fn (string $name) => $errors->first($name);
    $steps = [1 => 'Annonceur', 'Objectif', 'Contenu', 'Audience', 'Budget', 'Envoi'];
    $sectionClass = 'rounded-card border border-line bg-card p-4 md:p-[18px]';
    $fcfa = $budget !== '' ? number_format((int) preg_replace('/\D/', '', $budget), 0, ',', ' ') : '—';
@endphp
<div class="flex flex-col gap-4" x-data="{ step: 1 }" x-on:campaign-step.window="step = $event.detail.step; $nextTick(() => document.getElementById('section-' + step)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
    @include('livewire.partials.publicite-entete', ['current' => 'creer'])

    @error('form')<x-status tone="error">{{ $message }}</x-status>@enderror

    {{-- Téléphone : une étape par écran, avec sa progression. Ordinateur : les six sections ensemble. --}}
    <div class="lg:hidden" aria-live="polite">
        <div class="flex justify-between text-[12px] font-extrabold text-muted"><span x-text="'Étape ' + step + ' sur 6'"></span><span x-text="{{ \Illuminate\Support\Js::from($steps) }}[step]"></span></div>
        <div class="mt-2 flex gap-1" aria-hidden="true">@foreach ($steps as $n => $label)<i class="h-[5px] flex-1 rounded-full" x-bind:class="step > {{ $n }} ? 'bg-ink' : (step === {{ $n }} ? 'bg-accent' : 'bg-line')"></i>@endforeach</div>
    </div>
    <ol class="hidden gap-1 lg:flex" aria-label="Étapes">
        @foreach ($steps as $n => $label)<li><a href="#section-{{ $n }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-chip border border-line bg-card py-1 pl-1 pr-3 text-[12px] font-bold text-muted no-underline"><span class="flex size-5 items-center justify-center rounded-full bg-line-soft text-[11px] font-extrabold">{{ $n }}</span>{{ $label }}</a></li>@endforeach
    </ol>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <form wire:submit.prevent="submit" class="flex min-w-0 flex-col gap-3 pb-20 lg:pb-0" novalidate>
            {{-- 1. Annonceur --}}
            <section id="section-1" aria-labelledby="t1" class="{{ $sectionClass }}" x-bind:class="step !== 1 && 'max-lg:hidden'">
                <h2 id="t1" class="text-[17px] font-extrabold"><span class="mr-2 inline-flex size-[26px] items-center justify-center rounded-full bg-ink text-[12px] text-white">1</span>Annonceur</h2>
                <p class="mt-0.5 text-[13px] text-muted">Qui diffuse cette annonce ?</p>
                <fieldset class="mt-3 grid gap-2 md:grid-cols-2">
                    <legend class="sr-only">Annonceur</legend>
                    <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-[14px] border-2 p-3 {{ $advertiser === 'person' ? 'border-ink bg-ground' : 'border-line' }}">
                        <input type="radio" wire:model.live="advertiser" value="person" class="mt-1 size-5 shrink-0 accent-ink">
                        <span><strong class="block text-[14px]">Moi-même</strong><span class="text-[12.5px] text-muted">{{ auth()->user()->name }} · compte personnel</span></span>
                    </label>
                    @foreach ($advertisers['companies'] as $company)
                        <label wire:key="adv-{{ $company->id }}" class="flex min-h-14 cursor-pointer items-start gap-3 rounded-[14px] border-2 p-3 {{ $advertiser === $company->id ? 'border-ink bg-ground' : 'border-line' }}">
                            <input type="radio" wire:model.live="advertiser" value="{{ $company->id }}" class="mt-1 size-5 shrink-0 accent-ink">
                            <span><strong class="block text-[14px]">{{ $company->name }}</strong><span class="text-[12.5px] text-muted">Entreprise vérifiée <span class="text-type-knowledge-fg">✓</span></span></span>
                        </label>
                    @endforeach
                </fieldset>
                @if ($err('company_id'))<p role="alert" class="mt-2 text-[13px] font-semibold text-danger">{{ $err('company_id') }}</p>@endif
                <p class="mt-2 text-[12.5px] text-muted">Seules les personnes et les entreprises vérifiées peuvent diffuser. Le nom de l’annonceur est toujours visible sur l’annonce.</p>
            </section>

            {{-- 2. Objectif --}}
            <section id="section-2" aria-labelledby="t2" class="{{ $sectionClass }}" x-bind:class="step !== 2 && 'max-lg:hidden'">
                <h2 id="t2" class="text-[17px] font-extrabold"><span class="mr-2 inline-flex size-[26px] items-center justify-center rounded-full bg-ink text-[12px] text-white">2</span>Objectif</h2>
                <p class="mt-0.5 text-[13px] text-muted">Que souhaitez-vous obtenir ?</p>
                <fieldset class="mt-3 grid gap-2">
                    <legend class="sr-only">Objectif</legend>
                    @foreach ($objectives as $o)
                        <label wire:key="obj-{{ $o->value }}" class="flex min-h-14 cursor-pointer items-start gap-3 rounded-[14px] border-2 p-3 {{ $objective === $o->value ? 'border-ink bg-ground' : 'border-line' }}">
                            <input type="radio" wire:model.live="objective" value="{{ $o->value }}" class="mt-1 size-5 shrink-0 accent-ink">
                            <span><strong class="block text-[14px]">{{ $o->label() }}</strong><span class="text-[12.5px] text-muted">{{ $o->description() }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
                @if ($err('objective'))<p role="alert" class="mt-2 text-[13px] font-semibold text-danger">{{ $err('objective') }}</p>@endif
                <div class="mt-3 flex flex-col gap-1.5">
                    <label for="cta" class="text-[13px] font-bold">Bouton de l’annonce</label>
                    <select id="cta" wire:model.live="cta" class="{{ $input('cta') }}" @if ($err('cta')) aria-invalid="true" aria-describedby="cta-err" @endif>
                        <option value="">Choisissez d’abord un objectif</option>
                        @foreach ($ctas as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach
                    </select>
                    @if ($err('cta'))<p id="cta-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('cta') }}</p>@endif
                    <p class="text-[12.5px] text-muted">« Échanger » ouvre une conversation privée : la personne décide de vous répondre, vous ne voyez jamais son profil à l’avance.</p>
                </div>
            </section>

            {{-- 3. Contenu --}}
            <section id="section-3" aria-labelledby="t3" class="{{ $sectionClass }}" x-bind:class="step !== 3 && 'max-lg:hidden'">
                <h2 id="t3" class="text-[17px] font-extrabold"><span class="mr-2 inline-flex size-[26px] items-center justify-center rounded-full bg-ink text-[12px] text-white">3</span>Contenu</h2>
                <p class="mt-0.5 text-[13px] text-muted">Ce que les personnes verront.</p>
                <div class="mt-3 flex flex-col gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label for="title" class="flex justify-between text-[13px] font-bold">Titre <span class="font-medium text-muted">{{ mb_strlen($title) }} / 40</span></label>
                        <input id="title" type="text" wire:model.live.debounce.400ms="title" maxlength="40" class="{{ $input('title') }}" @if ($err('title')) aria-invalid="true" aria-describedby="title-err" @endif>
                        @if ($err('title'))<p id="title-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('title') }}</p>@endif
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="body" class="flex justify-between text-[13px] font-bold">Texte <span class="font-medium text-muted">{{ mb_strlen($body) }} / 180</span></label>
                        <textarea id="body" wire:model.live.debounce.400ms="body" rows="3" maxlength="180" class="{{ $input('body') }} py-3 leading-relaxed" aria-describedby="body-hint{{ $err('body') ? ' body-err' : '' }}" @if ($err('body')) aria-invalid="true" @endif></textarea>
                        <p id="body-hint" class="text-[12.5px] text-muted">Texte simple : pas de lien dans le texte, pas de promesse de gain.</p>
                        @if ($err('body'))<p id="body-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('body') }}</p>@endif
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="image" class="flex justify-between text-[13px] font-bold">Image <span class="font-medium text-muted">1 image · JPEG, PNG ou WebP · 3 Mo</span></label>
                        @if ($image || $imagePath)
                            <div class="flex min-h-12 items-center justify-between gap-2 rounded-button border border-chip-line bg-soft-blue px-3 text-[14px]"><span class="flex items-center gap-2 text-muted"><x-icon name="document" :size="18" />Image choisie</span><button type="button" wire:click="removeImage" class="min-h-11 px-2 font-bold text-link">Retirer</button></div>
                        @else
                            <input id="image" type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="{{ $input('image') }} py-2.5" @if ($err('image')) aria-invalid="true" aria-describedby="image-err" @endif>
                        @endif
                        <div wire:loading wire:target="image" class="text-[13px] font-semibold text-muted" role="status">Envoi de l’image…</div>
                        @if ($err('image'))<p id="image-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('image') }}</p>@endif
                    </div>
                    @if ($cta === 'link')
                        <div class="flex flex-col gap-1.5">
                            <label for="destination_url" class="flex justify-between text-[13px] font-bold">Page de destination</label>
                            <input id="destination_url" type="url" wire:model.blur="destination_url" maxlength="500" placeholder="https://" class="{{ $input('destination_url') }}" @if ($err('destination_url')) aria-invalid="true" aria-describedby="url-err" @endif>
                            <p class="text-[12.5px] text-muted">Une seule adresse en https, affichée sur le bouton et contrôlée par la modération.</p>
                            @if ($err('destination_url'))<p id="url-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('destination_url') }}</p>@endif
                        </div>
                    @endif
                </div>
            </section>

            {{-- 4. Audience --}}
            <section id="section-4" aria-labelledby="t4" class="{{ $sectionClass }}" x-bind:class="step !== 4 && 'max-lg:hidden'">
                <h2 id="t4" class="text-[17px] font-extrabold"><span class="mr-2 inline-flex size-[26px] items-center justify-center rounded-full bg-ink text-[12px] text-white">4</span>Audience</h2>
                <p class="mt-0.5 text-[13px] text-muted">Qui doit voir votre annonce ?</p>
                <div class="mt-3 grid grid-cols-2 gap-1.5 rounded-[14px] bg-line-soft p-1" role="radiogroup" aria-label="Mode de diffusion">
                    @foreach (['personalized' => ['Personnalisée', 'selon les profils consentants'], 'contextual' => ['Contextuelle', 'selon l’espace de diffusion']] as $value => [$label, $sub])
                        <label class="flex min-h-11 cursor-pointer flex-col items-center justify-center rounded-[11px] px-2 py-1 text-center text-[13px] font-bold {{ $audience_mode === $value ? 'bg-card text-ink shadow-sm' : 'text-muted' }}">
                            <input type="radio" wire:model.live="audience_mode" value="{{ $value }}" class="sr-only">{{ $label }}<small class="block text-[11.5px] font-medium">{{ $sub }}</small>
                        </label>
                    @endforeach
                </div>

                @if ($audience_mode === 'personalized')
                    <div class="mt-3 flex flex-col gap-4">
                        @include('livewire.partials.skill-picker', ['list' => 'skills', 'items' => $skills, 'input' => $skillsInput, 'suggestions' => $skillSuggestions, 'tone' => 'neutral', 'plain' => true, 'title' => 'Compétences ou savoir-faire', 'help' => 'Les personnes qui les proposent ou veulent les apprendre.', 'placeholder' => 'Couture, comptabilité…', 'max' => 8])
                        @if ($err('skillsInput'))<p role="alert" class="-mt-2 text-[13px] font-semibold text-danger">{{ $err('skillsInput') }}</p>@endif

                        <div class="flex flex-col gap-2">
                            <p class="text-[13px] font-bold">Domaines d’intérêt <span class="font-medium text-muted">jusqu’à 8</span></p>
                            <div class="flex flex-wrap gap-2" role="group" aria-label="Domaines d’intérêt">
                                @foreach ($domains as $d)
                                    <button type="button" wire:key="dom-{{ $d->id }}" wire:click="toggleDomain('{{ $d->id }}')" aria-pressed="{{ in_array($d->id, $domainIds, true) ? 'true' : 'false' }}" @class(['inline-flex min-h-11 items-center rounded-chip border px-3.5 text-[13px] font-semibold', 'border-link bg-soft-blue text-link' => in_array($d->id, $domainIds, true), 'border-chip-line bg-card' => ! in_array($d->id, $domainIds, true)])>{{ $d->name }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <p class="text-[13px] font-bold">Lieux <span class="font-medium text-muted">jusqu’à 5</span></p>
                            @if ($chosenCities->isNotEmpty())
                                <ul class="flex flex-wrap gap-2">@foreach ($chosenCities as $city)<li wire:key="city-{{ $city->id }}" class="inline-flex min-h-11 items-center gap-0.5 rounded-chip bg-soft-blue pl-3 pr-1 text-[13px] font-bold text-link">{{ $city->label() }}<button type="button" wire:click="removeCity('{{ $city->id }}')" aria-label="Retirer {{ $city->label() }}" class="flex size-11 items-center justify-center"><x-icon name="close" :size="14" :stroke="2.5" /></button></li>@endforeach</ul>
                            @endif
                            <input id="cityInput" type="text" wire:model.live.debounce.250ms="cityInput" autocomplete="off" placeholder="Rechercher une ville ou une commune" aria-label="Rechercher un lieu" class="{{ $input('cityInput') }}">
                            @if ($citySuggestions->isNotEmpty())
                                <ul class="flex flex-wrap gap-2">@foreach ($citySuggestions as $city)<li wire:key="sug-{{ $city->id }}"><button type="button" wire:click="addCity('{{ $city->id }}')" class="inline-flex min-h-11 items-center rounded-chip border border-chip-line bg-card px-3.5 text-[13px] font-semibold">{{ $city->label() }}</button></li>@endforeach</ul>
                            @endif
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[14px]"><input type="checkbox" wire:model.live="remote" class="size-5 shrink-0 accent-ink">À distance : le lieu ne restreint pas l’audience</label>
                        </div>

                        <div class="flex gap-2.5 rounded-button bg-type-knowledge-bg px-3.5 py-3 text-[12.5px] text-type-knowledge-fg">
                            <x-icon name="shield" :size="20" /><span><strong>Respect de la vie privée.</strong> Seules les personnes qui ont autorisé la publicité personnalisée sont concernées. Vous ne voyez jamais leur nom ni leurs données ; jamais de conversations privées ni de données sensibles.</span>
                        </div>
                        @if ($err('audience'))<p role="alert" class="text-[13px] font-semibold text-danger">{{ $err('audience') }}</p>@endif
                    </div>
                @else
                    <p class="mt-3 rounded-button bg-soft-blue px-3.5 py-3 text-[13px] text-[#0a4c85]">Votre annonce est montrée selon l’espace où elle apparaît, sans utiliser les profils : aucun consentement n’est nécessaire et aucun critère n’est demandé.</p>
                @endif
            </section>

            {{-- 5. Budget et durée --}}
            <section id="section-5" aria-labelledby="t5" class="{{ $sectionClass }}" x-bind:class="step !== 5 && 'max-lg:hidden'">
                <h2 id="t5" class="text-[17px] font-extrabold"><span class="mr-2 inline-flex size-[26px] items-center justify-center rounded-full bg-ink text-[12px] text-white">5</span>Budget et durée</h2>
                <p class="mt-0.5 text-[13px] text-muted">Combien et pendant combien de temps ?</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="budget" class="flex justify-between text-[13px] font-bold">Budget prévisionnel <span class="font-medium text-muted">FCFA</span></label>
                        <input id="budget" type="text" inputmode="numeric" wire:model.live.debounce.400ms="budget" class="{{ $input('budget_fcfa') }}" placeholder="30 000" @if ($err('budget_fcfa')) aria-invalid="true" aria-describedby="budget-err" @endif>
                        @if ($err('budget_fcfa'))<p id="budget-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('budget_fcfa') }}</p>@endif
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-bold">Durée</span>
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Durée">@foreach ([7, 14, 15, 30] as $days)<button type="button" wire:click="setDuration({{ $days }})" class="inline-flex min-h-11 items-center rounded-chip border border-chip-line bg-card px-3.5 text-[13px] font-semibold">{{ $days }} jours</button>@endforeach</div>
                    </div>
                    <div class="flex flex-col gap-1.5"><label for="starts_on" class="text-[13px] font-bold">Début</label><input id="starts_on" type="date" wire:model.live="starts_on" class="{{ $input('starts_on') }}" @if ($err('starts_on')) aria-invalid="true" aria-describedby="start-err" @endif>@if ($err('starts_on'))<p id="start-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('starts_on') }}</p>@endif</div>
                    <div class="flex flex-col gap-1.5"><label for="ends_on" class="text-[13px] font-bold">Fin</label><input id="ends_on" type="date" wire:model.live="ends_on" class="{{ $input('ends_on') }}" @if ($err('ends_on')) aria-invalid="true" aria-describedby="end-err" @endif>@if ($err('ends_on'))<p id="end-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('ends_on') }}</p>@endif</div>
                </div>
                <x-status class="mt-3 !border-type-call-bg !bg-type-call-bg !text-type-call-fg"><strong>Budget prévisionnel : aucun paiement n’est demandé.</strong> Aucune diffusion payante ne sera activée avant la validation du dispositif d’encaissement. Votre campagne acceptée par la modération restera « Prête ».</x-status>
            </section>

            {{-- 6. Vérifier et soumettre --}}
            <section id="section-6" aria-labelledby="t6" class="{{ $sectionClass }}" x-bind:class="step !== 6 && 'max-lg:hidden'">
                <h2 id="t6" class="text-[17px] font-extrabold"><span class="mr-2 inline-flex size-[26px] items-center justify-center rounded-full bg-ink text-[12px] text-white">6</span>Vérifier et soumettre</h2>
                <p class="mt-0.5 text-[13px] text-muted">Dernière relecture avant la modération.</p>
                <dl class="mt-3 text-[13px]">
                    @foreach ([['Annonceur', $advertiserName.($advertiserVerified ? ' ✓' : '')], ['Objectif', $objective ? \App\Enums\AdObjective::from($objective)->label() : '—'], ['Audience', $audience_mode === 'personalized' ? 'Personnalisée · '.count($skills).' compétence(s) · '.(count($cityIds) + ($remote ? 1 : 0)).' lieu(x)' : 'Contextuelle'], ['Budget et durée', $fcfa.' FCFA prévus'.($starts_on && $ends_on ? ' · du '.\Carbon\Carbon::parse($starts_on)->locale('fr')->translatedFormat('j M').' au '.\Carbon\Carbon::parse($ends_on)->locale('fr')->translatedFormat('j M Y') : '')]] as [$k, $v])
                        <div class="flex justify-between gap-3 border-b border-line-soft py-1.5 last:border-0"><dt class="text-muted">{{ $k }}</dt><dd class="text-right font-bold">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                <x-status class="mt-3"><strong>Modération préalable.</strong> Votre annonce est relue avant toute diffusion. Acceptée, elle reste « Prête » tant que la diffusion payante n’est pas ouverte. Vous suivez tout dans « Mes campagnes ».</x-status>
                <div class="mt-3 flex flex-col gap-1.5">
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 text-[13.5px] leading-snug"><input type="checkbox" wire:model.live="rulesAccepted" class="mt-0.5 size-5 shrink-0 accent-ink" @if ($err('rulesAccepted')) aria-invalid="true" aria-describedby="rules-err" @endif><span>Je confirme que mon annonce respecte les règles de GAMAD : honnête, sans promesse de gain, sans contenu interdit ou réglementé, sans donnée personnelle d’autrui.</span></label>
                    @if ($err('rulesAccepted'))<p id="rules-err" role="alert" class="text-[13px] font-semibold text-danger">{{ $err('rulesAccepted') }}</p>@endif
                </div>
                @if ($editing)<p class="mt-3 text-[12.5px] text-muted">Une annonce déjà acceptée, une fois modifiée, repart à la modération.</p>@endif
                <div class="mt-4 hidden flex-wrap justify-between gap-2 lg:flex">
                    <x-button type="button" variant="secondary" wire:click="saveDraft" wire:loading.attr="disabled">{{ $editing ? 'Enregistrer' : 'Enregistrer le brouillon' }}</x-button>
                    <x-button wire:loading.attr="disabled" wire:target="submit"><x-icon name="shield" :size="18" />Soumettre à la modération</x-button>
                </div>
            </section>

            {{-- Téléphone : barre d'actions fixe --}}
            <div class="fixed inset-x-0 bottom-[68px] z-30 flex gap-2 border-t border-line bg-card px-4 py-2.5 lg:hidden">
                <x-button type="button" variant="secondary" class="flex-[.5]" x-show="step > 1" x-on:click="step--; window.scrollTo({ top: 0 })">Retour</x-button>
                <x-button type="button" variant="secondary" class="flex-[.5]" x-show="step === 1" wire:click="saveDraft">Brouillon</x-button>
                <x-button type="button" class="flex-1" x-show="step < 6" x-on:click="step++; window.scrollTo({ top: 0 })">Continuer</x-button>
                <x-button class="flex-1" x-show="step === 6" wire:loading.attr="disabled" wire:target="submit">Soumettre à la modération</x-button>
            </div>
        </form>

        {{-- Aperçu et estimation --}}
        <aside class="flex flex-col gap-3 lg:sticky lg:top-4" aria-label="Aperçu et estimation">
            <div x-bind:class="! [3, 6].includes(step) && 'max-lg:hidden'">
                <p class="mb-2 text-[14px] font-extrabold">Aperçu dans le Fil</p>
                @include('livewire.partials.publicite-apercu', ['name' => $advertiserName, 'verified' => $advertiserVerified, 'title' => $title, 'body' => $body, 'imageUrl' => $previewImage, 'ctaLabel' => $ctaLabel])
                <p class="mt-1.5 text-[11.5px] text-muted">Votre annonce est toujours signalée « Sponsorisé » et peut être masquée ou signalée.</p>
            </div>
            @if ($audience_mode === 'personalized')
                <section class="rounded-card border border-line bg-card p-4" aria-labelledby="portee" x-bind:class="step !== 4 && 'max-lg:hidden'">
                    <p id="portee" class="text-[12.5px] font-bold text-muted">Portée estimée</p>
                    <p class="text-[24px] font-extrabold tracking-[-0.4px]" data-testid="portee">{{ $levelLabel }}</p>
                    <div class="my-2 h-2.5 overflow-hidden rounded-full bg-line-soft" role="img" aria-label="Portée : {{ $levelLabel }}"><div class="h-full rounded-full bg-accent" style="width: {{ $gauge }}%"></div></div>
                    <p class="text-[12px] text-muted">Ordre de grandeur seulement : le nombre exact de personnes n’est jamais affiché.@if ($level === 'too_small') <strong class="text-danger">Audience trop restreinte pour protéger les personnes : la diffusion personnalisée n’est pas possible, élargissez vos critères ou choisissez la diffusion contextuelle.</strong>@endif</p>
                </section>
            @endif
        </aside>
    </div>
</div>
