@php
    $titles = [
        'personal_work' => ['Ma réalisation', 'Ce que j’ai réalisé', 'Publier ma réalisation', 'Ex. J’ai remis en état cette machine avec des pièces de récupération. Voici comment.'],
        'share' => ['Je partage', 'Votre partage', 'Publier mon partage', 'Une idée, un retour d’expérience, une découverte… De quoi aimeriez-vous parler ?'],
        'need' => ['Nouveau besoin', 'Votre besoin', 'Publier mon besoin', 'Ex. Je cherche quelqu’un qui sait installer un logiciel de caisse pour ma boutique, samedi matin si possible.'],
        'knowledge' => ['Partager un savoir', 'Votre savoir', 'Publier mon savoir', 'Ex. Atelier ouvert à tous : tenir les comptes d’un petit commerce. Samedi à 10 h, en ligne.'],
        'learning_wish' => ['Je veux apprendre', 'Ce que vous voulez apprendre', 'Publier ma demande', 'Ex. Je veux apprendre à installer et réparer des panneaux solaires. Je suis disponible le week-end.'],
        'zumra_call' => ['Appel à rejoindre', 'Votre appel', 'Publier l’appel', 'Ex. Nous cherchons des couturières et des couturiers pour rejoindre la ZUMRA : atelier chaque samedi matin.'],
        'project_update' => ['Avancée de projet', 'Votre avancée', 'Publier l’avancée', 'Ex. Les trois premières écoles ont validé la commande : nous commençons la coupe lundi.'],
        'achievement' => ['Réalisation', 'Ce qui a été fait', 'Publier la réalisation', 'Ex. 120 uniformes ont été livrés aux élèves de deux écoles du quartier.'],
        'offer' => ['Proposer mon aide', 'Votre aide', 'Publier mon offre', 'Ex. Je peux aider à tenir les comptes d’une petite boutique, le soir après 18 h.'],
    ][$type->value];
    [$heading, $label, $button, $placeholder] = $titles;
@endphp

<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $project ? route('projets.show', ['project' => $project, 'onglet' => 'fil']) : route('agir') }}" aria-label="Retour au choix du type" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $heading }}</h1>
    </div>

    <form wire:submit="publish" class="flex flex-col gap-3.5">
        <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $type->badgeClasses() }}">{{ $type->label() }}</span>
        @if ($project)
            <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Vous publiez au nom du projet <strong>{{ $project->name }}</strong> ({{ $zumra->name }}).</p>
        @elseif ($zumra)
            <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Vous publiez au nom de <strong>{{ $zumra->name }}</strong>.</p>
        @endif

        <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.body].length }">
            <label for="body" class="text-[13px] font-bold text-muted">{{ $label }}</label>
            <textarea id="body" wire:model="body" rows="6" maxlength="{{ $bodyMax }}" placeholder="{{ $placeholder }}"
                      x-on:input="count = [...$event.target.value].length"
                      @class(['min-h-[140px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal', $errors->has('body') ? 'border-danger' : 'border-chip-line'])
                      @if ($errors->has('body')) aria-invalid="true" aria-describedby="body-error" @endif></textarea>
            <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($body) }}</span> / {{ $bodyMax }}</span>
            @error('body')
                <p id="body-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
            <p class="text-[12px] leading-normal text-muted">Soyez précis : ce qu’il faut faire, où, et quand. Ne mettez ni téléphone ni e-mail ici. Le texte reste un texte simple : les adresses web n’y sont pas cliquables.</p>
        </div>

        @if (! $zumra && ! $project)
            <div class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
                <label for="publication-media" class="text-[15px] font-extrabold">Ajouter une image, une vidéo ou un document</label>
                <p class="text-[12px] text-muted">Un fichier facultatif : JPEG, PNG, WebP ou PDF (5 Mo), MP4 ou WebM (10 Mo). Ajoutez une légende dans votre texte.</p>
                <input id="publication-media" type="file" wire:model="media" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" class="min-h-11 w-full min-w-0 text-[13px]">
                <p wire:loading wire:target="media" class="text-[13px] text-muted" role="status">Envoi du fichier…</p>
                @if ($media)<span class="break-words text-[13px] font-semibold">{{ $media->getClientOriginalName() }}</span><x-button type="button" variant="secondary" wire:click="removeMedia">Retirer le fichier</x-button>@endif
                @error('media')<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror
            </div>
        @endif

        @if ($type->isNeed())
            <details class="rounded-card border border-line bg-card p-4" @if ($errors->has('needPurpose') || $errors->has('needTiming')) open @endif>
                <summary class="min-h-11 cursor-pointer content-center text-[15px] font-extrabold">Préciser mon besoin (facultatif)</summary>
                <div class="mt-3 flex flex-col gap-3">
                    <x-wire-field name="needPurpose" label="Pour faire quoi ?" maxlength="150" placeholder="Ex. Tenir les comptes de ma boutique" />
                    <x-wire-field name="needTiming" label="Où et quand ?" maxlength="100" placeholder="Ex. À distance, samedi matin" />
                    <p class="text-[12px] leading-relaxed text-muted">Ces précisions seront ajoutées au texte publié, dans la limite totale de {{ $bodyMax }} caractères. N’indiquez ni adresse privée, ni téléphone, ni e-mail.</p>
                </div>
            </details>
        @endif

        @include('livewire.partials.skill-picker', [
            'list' => 'skills', 'items' => $skills, 'input' => $skillsInput, 'suggestions' => $suggestions, 'tone' => 'neutral', 'plain' => true,
            'max' => \App\Models\Publication::MAX_SKILLS,
            'title' => $type->isNeed() ? 'Capacité recherchée (facultatif)' : 'Capacités concernées (facultatif)',
            'help' => 'Touchez une suggestion ou écrivez la vôtre.', 'placeholder' => 'Ajouter une capacité…',
        ])

        <div class="flex flex-col gap-1.5">
            <label for="domain_id" class="text-[13px] font-bold text-muted">Domaine (facultatif)</label>
            <select id="domain_id" wire:model="domain_id"
                    @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('domain_id') ? 'border-danger' : 'border-chip-line'])>
                <option value="">Aucun domaine</option>
                @foreach ($domains as $domain)
                    <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                @endforeach
            </select>
            @error('domain_id')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="city_id" class="text-[13px] font-bold text-muted">Lieu (facultatif)</label>
            <select id="city_id" wire:model="city_id"
                    @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('city_id') ? 'border-danger' : 'border-chip-line'])>
                <option value="">Aucun lieu</option>
                @foreach ($cities as $city)
                    <option value="{{ $city->id }}">{{ $city->label() }}</option>
                @endforeach
            </select>
            @error('city_id')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
            <p class="text-[12px] leading-normal text-muted">{{ $profileHasCity ? 'Repris de votre profil. Vous pouvez le changer pour cette publication.' : 'Vous pouvez choisir une ville, ou renseigner la vôtre dans votre profil.' }}</p>
        </div>

        <fieldset class="flex flex-col gap-1.5">
            <legend class="mb-1 text-[13px] font-bold text-muted">Qui peut la voir ?</legend>
            <div @class(['grid gap-2', 'grid-cols-2' => ! $zumra, 'grid-cols-1 sm:grid-cols-3' => $zumra])>
                @foreach ($zumra ? [\App\Models\Publication::PUBLIC => 'Public', \App\Models\Publication::MEMBERS => 'Inscrits seulement', \App\Models\Publication::ZUMRA => 'ZUMRA seulement'] : [\App\Models\Publication::PUBLIC => 'Public', \App\Models\Publication::MEMBERS => 'Inscrits seulement'] as $value => $libelle)
                    <button type="button" wire:click="$set('visibility', '{{ $value }}')" aria-pressed="{{ $visibility === $value ? 'true' : 'false' }}"
                            @class(['min-h-12 rounded-button px-2 text-[14px]', 'border-2 border-ink font-extrabold' => $visibility === $value, 'border border-chip-line font-semibold' => $visibility !== $value, 'bg-card text-ink'])>{{ $libelle }}</button>
                @endforeach
            </div>
            <p class="text-[12px] leading-normal text-muted">{{ match ($visibility) { \App\Models\Publication::PUBLIC => 'Même sans compte, on peut la lire.', \App\Models\Publication::ZUMRA => 'Seuls les membres de la ZUMRA peuvent la lire.', default => 'Seules les personnes inscrites peuvent la lire.' } }}</p>
            @error('visibility')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-button wire:loading.attr="disabled" wire:target="publish,media" class="min-h-[52px]">
            <span wire:loading.remove wire:target="publish">{{ $button }}</span>
            <span wire:loading wire:target="publish">Publication…</span>
        </x-button>

        @if ($type->takesGestures())
            <p class="text-[12px] leading-normal text-muted">Aucun chiffre d’approbation n’est affiché. On verra seulement combien de personnes {{ $type->isNeed() ? 'proposent leur aide' : 'répondent' }}.</p>
        @else
            <p class="text-[12px] leading-normal text-muted">Aucun chiffre d’approbation n’est affiché.</p>
        @endif
    </form>
</div>
