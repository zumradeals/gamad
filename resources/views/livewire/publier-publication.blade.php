@if ($zumra || $project)
{{-- Une seule racine, au début d'une ligne : sans elle Livewire pose son identifiant sur le premier élément interne et le formulaire n'envoie plus rien. --}}
<div>
    @include('livewire.partials.publier-legacy')
</div>
@else
@php
    // Une intention, une question principale, quelques précisions facultatives, un aperçu (D-184).
    $words = [
        'need' => ['J’ai besoin d’aide', 'Expliquez simplement ce que vous cherchez. Pas besoin de termes techniques.', 'De quoi avez-vous besoin ?', 'Je cherche quelqu’un pour installer une caméra dans ma boutique à Abidjan.', 'Publier ma demande'],
        'learning_wish' => ['Je veux apprendre', 'Dites simplement ce que vous aimeriez savoir faire.', 'Qu’aimeriez-vous apprendre ?', 'Je veux apprendre à réparer les téléphones.', 'Publier ma demande d’apprentissage'],
        'offer' => ['Je peux aider', 'Dites simplement ce que vous savez faire et comment vous pouvez aider.', 'Que pouvez-vous faire pour aider quelqu’un ?', 'Je peux aider à tenir les comptes d’une petite boutique, le soir après 18 h.', 'Publier ma proposition'],
        'knowledge' => ['Je partage un savoir', 'Expliquez quelque chose aux autres, avec vos mots.', 'Qu’aimeriez-vous expliquer ou apprendre aux autres ?', 'Voici comment tenir les comptes d’un petit commerce en trois étapes.', 'Partager mon savoir'],
        'personal_work' => ['Je montre une réalisation', 'Présentez un projet ou un résultat, même modeste.', 'Qu’avez-vous réalisé ?', 'J’ai remis en état cette machine avec des pièces de récupération.', 'Partager ma réalisation'],
        'share' => ['Je partage', 'Une idée, un retour d’expérience, une découverte.', 'De quoi aimeriez-vous parler ?', 'Une idée, un retour d’expérience, une découverte…', 'Publier mon partage'],
    ][$type->value] ?? null;
    abort_unless($words, 404);
    [$heading, $subtitle, $question, $placeholder, $button] = $words;
    if ($type === \App\Enums\PublicationType::Offer && ($matching['enabled'] ?? false)) {
        if ($matching['activity'] === 'training') {
            [$heading, $subtitle, $question, $placeholder, $button] = ['Proposer une formation', 'Dites ce que vous transmettez, à qui et comment.', 'Que voulez-vous transmettre ?', 'Ce que vous transmettez, à qui, où et dans quelles conditions.', 'Publier mon offre de formation'];
        } elseif ($matching['terms'] === 'paid') {
            [$heading, $subtitle, $question, $placeholder, $button] = ['Proposer mes services', 'Dites ce que vous pouvez réaliser et vos conditions.', 'Quel service proposez-vous ?', 'Ce que vous pouvez réaliser, vos disponibilités et les conditions proposées.', 'Publier mon offre de services'];
        }
    }
    $timings = ['Dès que possible', 'Cette semaine', 'Ce mois-ci', 'Dans plus d’un mois'];
    $visibilityLabels = [\App\Models\Publication::PUBLIC => 'Tout le monde', \App\Models\Publication::MEMBERS => 'Seulement les membres GAMAD'];
    $cityLabel = $city_id !== '' ? $cities->firstWhere('id', $city_id)?->label() : null;
    $stepNow = $previewing ? 3 : $step;
    $profile = auth()->user()->ensureProfile();
    $canBack = $stepNow > 1;
@endphp

<div class="flex flex-col gap-3.5" data-agir-form="{{ $slug }}">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        @if ($canBack)
            <button type="button" wire:click="back" aria-label="Revenir à l’étape précédente" class="flex size-11 items-center justify-center text-ink"><x-icon name="back" /></button>
        @else
            <a href="{{ route('fil') }}" aria-label="Quitter le formulaire"
               x-data x-on:click="if ($wire.body.trim() !== '' && ! confirm('Quitter ce formulaire ? Votre texte reste sauvegardé sur cet appareil pendant 7 jours.')) $event.preventDefault()"
               class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        @endif
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $heading }}</h1>
    </div>

    {{-- Brouillon local : rien ne s’affiche tant qu’aucun texte récupérable n’existe. --}}
    <section x-data="gamadDraft($wire, @js('gamad:draft:'.auth()->id().':'.$slug))" x-show="saved" x-cloak class="rounded-card border border-line bg-soft-blue p-3" aria-label="Brouillon récupérable">
        <p class="text-[14px] font-bold">Vous aviez commencé un texte. Le reprendre ?</p>
        <p class="mt-0.5 text-[12px] text-muted">Seuls les textes sont gardés sur cet appareil. Joignez de nouveau votre fichier si besoin.</p>
        <div class="mt-2 flex flex-wrap gap-2"><x-button type="button" x-on:click="resume()">Reprendre</x-button><x-button type="button" variant="secondary" x-on:click="discard()">Effacer le brouillon</x-button></div>
        <p class="sr-only" x-text="status" role="status"></p>
    </section>

    <ol class="m-0 flex list-none items-center gap-2 p-0" aria-label="Étapes du formulaire">
        @foreach (['Détails', 'Compléments', 'Aperçu'] as $i => $label)
            @php $n = $i + 1; @endphp
            <li @if ($stepNow === $n) aria-current="step" @endif class="flex min-w-0 flex-1 items-center gap-2 {{ $stepNow === $n ? 'font-extrabold text-ink' : 'font-semibold text-muted' }}">
                <span @class(['flex size-7 shrink-0 items-center justify-center rounded-full text-[13px]', 'bg-accent text-ink' => $stepNow === $n, 'bg-ink text-white' => $stepNow > $n, 'border border-chip-line' => $stepNow < $n])>@if ($stepNow > $n)<x-icon name="check" :size="14" :stroke="3" />@else{{ $n }}@endif</span>
                <span class="truncate text-[13px]">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <form wire:submit="{{ $previewing ? 'publish' : ($step === 1 ? 'next' : 'preview') }}" class="flex flex-col gap-3.5" novalidate>
        {{-- Étape 1 : la seule question obligatoire. --}}
        @if ($stepNow === 1)
            <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <p class="text-[14px] leading-normal text-muted">{{ $subtitle }}</p>
                <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.body].length }">
                    <label for="body" class="text-[17px] font-extrabold">{{ $question }}</label>
                    <textarea id="body" wire:model="body" rows="6" maxlength="{{ $bodyMax }}" placeholder="{{ $placeholder }}" autofocus
                              x-on:input="count = [...$event.target.value].length"
                              @class(['min-h-[160px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[16px] leading-normal', $errors->has('body') ? 'border-danger' : 'border-chip-line'])
                              @if ($errors->has('body')) aria-invalid="true" aria-describedby="body-error" @endif></textarea>
                    <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($body) }}</span> / {{ $bodyMax }}</span>
                    @error('body')
                        <p id="body-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                    @enderror
                    <p class="text-[12px] leading-normal text-muted">Ne mettez ni téléphone ni e-mail ici : les échanges se font par la conversation. Les adresses web ne sont pas cliquables.</p>
                </div>
            </section>
            <x-button variant="accent" class="min-h-[52px] w-full" wire:loading.attr="disabled" wire:target="next">Continuer</x-button>
        @endif

        {{-- Étape 2 : compléments, tous facultatifs. --}}
        @if ($stepNow === 2)
            <section class="flex flex-col gap-4 rounded-card border border-line bg-card p-4">
                <div class="flex flex-col gap-1.5">
                    <label for="city_id" class="text-[15px] font-extrabold">Où ? <span class="text-[13px] font-semibold text-muted">(facultatif)</span></label>
                    <select id="city_id" wire:model="city_id" @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[16px]', $errors->has('city_id') ? 'border-danger' : 'border-chip-line'])>
                        <option value="">Aucun lieu précis</option>
                        @foreach ($cities as $city)<option value="{{ $city->id }}">{{ $city->label() }}</option>@endforeach
                    </select>
                    @error('city_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    <p class="text-[12px] text-muted">{{ $profileHasCity ? 'Repris de votre profil. Vous pouvez le changer.' : 'Choisissez une ville, ou renseignez la vôtre dans votre profil.' }}</p>
                </div>

                @if ($type->isNeed())
                    <div class="flex flex-col gap-1.5">
                        <label for="needTiming" class="text-[15px] font-extrabold">Pour quand ? <span class="text-[13px] font-semibold text-muted">(facultatif)</span></label>
                        <select id="needTiming" wire:model="needTiming" @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[16px]', $errors->has('needTiming') ? 'border-danger' : 'border-chip-line'])>
                            <option value="">Pas de date précise</option>
                            @foreach ($timings as $timing)<option value="{{ $timing }}">{{ $timing }}</option>@endforeach
                            @if ($needTiming !== '' && ! in_array($needTiming, $timings, true))<option value="{{ $needTiming }}">{{ $needTiming }}</option>@endif
                        </select>
                        @error('needTiming')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div class="flex flex-col gap-2">
                    <span class="text-[15px] font-extrabold">Ajouter une photo ou d’autres détails <span class="text-[13px] font-semibold text-muted">(facultatif)</span></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="publication-media" class="relative inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-button border border-ink bg-card px-4 text-[14px] font-bold focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-link">
                            <x-icon name="plus" :size="18" />Ajouter une photo ou un document
                            <input id="publication-media" type="file" wire:model="media" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" class="absolute inset-0 size-full cursor-pointer opacity-0">
                        </label>
                    </div>
                    <p wire:loading wire:target="media" class="text-[13px] text-muted" role="status">Envoi du fichier…</p>
                    @if ($media)
                        <div class="flex flex-wrap items-center gap-2 rounded-button bg-ground px-3 py-2">
                            <span class="min-w-0 flex-1 break-words text-[13px] font-semibold">{{ $media->getClientOriginalName() }}</span>
                            <button type="button" wire:click="removeMedia" class="min-h-11 px-2 text-[13px] font-bold text-danger">Retirer</button>
                        </div>
                    @endif
                    @error('media')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    <p class="text-[12px] text-muted">JPEG, PNG, WebP ou PDF (10 Mo au plus), MP4 ou WebM. Le fichier reste privé tant que la publication n’est pas visible par la personne concernée.</p>
                </div>

                <fieldset class="flex flex-col gap-1.5">
                    <legend class="mb-1 text-[15px] font-extrabold">Qui peut la voir ?</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($visibilityLabels as $value => $libelle)
                            <button type="button" wire:click="$set('visibility', '{{ $value }}')" aria-pressed="{{ $visibility === $value ? 'true' : 'false' }}"
                                    @class(['flex min-h-12 items-center justify-center gap-2 rounded-button bg-card px-3 text-[14px] text-ink', 'border-2 border-ink font-extrabold' => $visibility === $value, 'border border-chip-line font-semibold' => $visibility !== $value])>@if ($visibility === $value)<x-icon name="check" :size="16" :stroke="3" />@endif{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <p class="text-[12px] leading-normal text-muted">{{ $visibility === \App\Models\Publication::PUBLIC ? 'Même sans compte, on peut la lire.' : 'Seules les personnes inscrites à GAMAD peuvent la lire.' }}</p>
                    @error('visibility')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <details class="rounded-button border border-line p-3" @if ($errors->has('skills*') || $errors->has('domain_id') || $errors->has('needPurpose')) open @endif>
                    <summary class="min-h-11 cursor-pointer content-center text-[14px] font-bold">Plus d’options (facultatif)</summary>
                    <div class="mt-3 flex flex-col gap-3">
                        @if ($type->isNeed())
                            <x-wire-field name="needPurpose" label="Pour faire quoi ?" maxlength="150" placeholder="Ex. Tenir les comptes de ma boutique" />
                        @endif
                        @include('livewire.partials.skill-picker', [
                            'list' => 'skills', 'items' => $skills, 'input' => $skillsInput, 'suggestions' => $suggestions, 'tone' => 'neutral', 'plain' => true,
                            'max' => \App\Models\Publication::MAX_SKILLS,
                            'title' => $type->isNeed() ? 'Capacité recherchée (facultatif)' : 'Capacités concernées (facultatif)',
                            'help' => 'Touchez une suggestion ou écrivez la vôtre.', 'placeholder' => 'Ajouter une capacité…',
                        ])
                        <div class="flex flex-col gap-1.5">
                            <label for="domain_id" class="text-[13px] font-bold text-muted">Domaine (facultatif)</label>
                            <select id="domain_id" wire:model="domain_id" @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[16px]', $errors->has('domain_id') ? 'border-danger' : 'border-chip-line'])>
                                <option value="">Aucun domaine</option>
                                @foreach ($domains as $domain)<option value="{{ $domain->id }}">{{ $domain->name }}</option>@endforeach
                            </select>
                            @error('domain_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                        </div>
                        @if (in_array($type, [\App\Enums\PublicationType::Need, \App\Enums\PublicationType::Offer, \App\Enums\PublicationType::Knowledge, \App\Enums\PublicationType::LearningWish], true))
                            @include('livewire.partials.matching-publication-editor')
                        @endif
                    </div>
                </details>
            </section>
            <div class="grid grid-cols-[auto_1fr] gap-2">
                <x-button type="button" variant="secondary" class="min-h-[52px]" wire:click="back">Retour</x-button>
                <x-button variant="accent" class="min-h-[52px]" wire:loading.attr="disabled" wire:target="preview">Continuer</x-button>
            </div>
        @endif

        {{-- Étape 3 : l’aperçu est exactement ce qui sera publié. --}}
        @if ($stepNow === 3)
            <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4" aria-label="Aperçu de la publication">
                <h2 class="text-[18px] font-extrabold">Relisez avant de publier</h2>
                <div class="flex items-center gap-3">
                    <x-avatar :profile="$profile" :size="44" />
                    <div class="min-w-0">
                        <p class="break-words text-[15px] font-extrabold">{{ auth()->user()->name }}</p>
                        <span class="inline-block rounded-chip px-2.5 py-0.5 text-[12px] font-bold {{ $type->badgeClasses() }}">{{ \App\Services\MatchingConditions::label($type, $matching ?? []) }}</span>
                    </div>
                </div>
                <p class="whitespace-pre-wrap break-words text-[15px] leading-normal">{{ $previewBody }}</p>
                @if ($media && str_starts_with((string) $media->getMimeType(), 'image/') && $media->isPreviewable())
                    <img src="{{ $media->temporaryUrl() }}" alt="Image jointe" class="max-h-64 w-full rounded-button object-cover">
                @elseif ($media)
                    <p class="break-words text-[13px]">Fichier joint : {{ $media->getClientOriginalName() }}</p>
                @endif
                <ul class="m-0 flex list-none flex-col gap-1 p-0 text-[13px] text-muted">
                    @if ($cityLabel)<li class="flex items-center gap-1.5"><x-icon name="pin" :size="16" />{{ $cityLabel }}</li>@endif
                    <li>Visible par : {{ $visibilityLabels[$visibility] ?? 'Tout le monde' }}</li>
                </ul>
                @if (in_array($type, [\App\Enums\PublicationType::Need, \App\Enums\PublicationType::LearningWish, \App\Enums\PublicationType::Offer, \App\Enums\PublicationType::Knowledge], true) && ($matching['enabled'] ?? false))
                    <p class="text-[13px] font-bold">Conditions : {{ \App\Services\MatchingConditions::TERMS[$matching['terms'] ?? 'discuss'] ?? 'À convenir explicitement' }}</p>
                    @if (! empty($matching['budget']))<p class="text-[13px]">{{ $matching['budget'] }}</p>@endif
                    <p class="text-[12px] text-muted">Les conditions doivent être convenues ensemble avant de commencer. GAMAD ne crée aucun contrat ni paiement.</p>
                @endif
            </section>
            @if ($errors->any())
                <div role="alert" class="rounded-button border border-danger bg-card p-3 text-[14px] text-danger">
                    <p class="font-bold">Quelque chose est à corriger avant de publier.</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="grid grid-cols-[auto_1fr] gap-2">
                <x-button type="button" variant="secondary" class="min-h-[52px]" wire:click="edit">Modifier</x-button>
                <x-button variant="accent" class="min-h-[52px]" wire:loading.attr="disabled" wire:target="publish">
                    <span wire:loading.remove wire:target="publish">{{ $button }}</span>
                    <span wire:loading wire:target="publish">Publication…</span>
                </x-button>
            </div>
            <p class="text-[12px] leading-normal text-muted">{{ $type->takesGestures() ? 'Aucun chiffre d’approbation n’est affiché. On verra seulement combien de personnes '.($type->isNeed() ? 'proposent leur aide' : 'répondent').'.' : 'Aucun chiffre d’approbation n’est affiché.' }}</p>
        @endif
    </form>
</div>
@endif
