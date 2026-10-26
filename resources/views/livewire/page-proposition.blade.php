@php
    $kind = $proposal->kind;
    $beneficiaryKind = $proposal->beneficiaryKind();
    $author = $proposal->author;
    $profile = $author?->profile;
    $profile?->setRelation('user', $author);
    $canSeeAuthor = $isAuthor || ($profile && \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('view', $profile));
    $box = 'flex flex-col gap-2.5 rounded-card border border-line bg-card p-4';
@endphp
<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $private ? route('espace.mes-contributions') : route('contributions.index') }}" aria-label="Retour" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Contribution</h1>
    </div>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @if ($errors->any())<div role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif

    <section class="{{ $box }}" aria-labelledby="titre-proposition">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $kind->badgeClasses() }}"><x-icon :name="$kind->icon()" :size="14" />{{ $kind->label() }}</span>
            <span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->state->badgeClasses() }}">{{ $proposal->state->label() }}</span>
            @if ($private)<span class="rounded-chip bg-ground px-2.5 py-1 text-[12px] font-bold text-muted">{{ $proposal->isPublic() ? 'Publique' : 'Privée' }}</span>@endif
        </div>
        <h2 id="titre-proposition" class="break-words text-[20px] font-extrabold leading-tight">{{ $kind->label() }} pour {{ $proposal->beneficiaryName() }}</h2>
        <p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $proposal->offer }}</p>
        @if ($kind->disclaimer())<p class="rounded-button bg-type-call-bg px-3 py-2 text-[13px] font-semibold text-type-call-fg">{{ $kind->disclaimer() }} Aucune somme n’est enregistrée ici.</p>@endif
        <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[14px]">
            <dt class="font-semibold text-muted">Bénéficiaire</dt><dd class="m-0">{{ $beneficiaryKind->label() }} · {{ $proposal->beneficiaryName() }}</dd>
            <dt class="font-semibold text-muted">Proposée par</dt><dd class="m-0">{{ $canSeeAuthor ? $author->name : 'Un membre de GAMAD' }}</dd>
            @if ($proposal->city)<dt class="font-semibold text-muted">Localité</dt><dd class="m-0">{{ $proposal->city->label() }}</dd>@endif
            @if ($proposal->domain)<dt class="font-semibold text-muted">Domaine</dt><dd class="m-0">{{ $proposal->domain->name }}</dd>@endif
            <dt class="font-semibold text-muted">Date</dt><dd class="m-0">{{ $proposal->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->isoFormat('D MMMM YYYY') }}</dd>
            @if ($proposal->realized_at)<dt class="font-semibold text-muted">Réalisation confirmée</dt><dd class="m-0">{{ $proposal->realized_at->copy()->locale('fr')->timezone('Africa/Abidjan')->isoFormat('D MMMM YYYY') }}</dd>@endif
        </dl>
        @if ($proposal->need && \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('view', $proposal->need))
            <a href="{{ route('publications.show', $proposal->need) }}" class="inline-flex min-h-11 items-center self-start text-[14px] font-bold">En réponse au besoin : {{ $proposal->need->title ?: \Illuminate\Support\Str::limit($proposal->need->body, 50) }}</a>
        @endif
    </section>

    @if ($private && filled($proposal->message))
        <section class="{{ $box }}"><h2 class="text-[15px] font-extrabold">Message</h2><p class="whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $proposal->message }}</p></section>
    @endif

    @if ($private && filled($proposal->decision_note))
        <section class="{{ $box }}"><h2 class="text-[15px] font-extrabold">Réponse du bénéficiaire</h2><p class="whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $proposal->decision_note }}</p></section>
    @endif

    @if ($private && $proposal->conversation)
        <x-button :href="route('conversations.show', $proposal->conversation)" variant="secondary">Écrire dans la conversation</x-button>
    @endif

    @if ($can['decide'])
        <section class="{{ $box }}" aria-labelledby="repondre">
            <h2 id="repondre" class="text-[16px] font-extrabold">Répondre à cette proposition</h2>
            <p class="text-[13px] text-muted">Accepter n’est pas confirmer la réalisation : vous la confirmerez quand la contribution aura eu lieu. Aucune obligation de part ni d’autre.</p>
            <label for="note" class="text-[13px] font-bold text-muted">Un mot pour la personne (facultatif)</label>
            <textarea id="note" wire:model="note" rows="2" maxlength="{{ \App\Models\ContributionProposal::NOTE_MAX }}" class="w-full rounded-button border border-chip-line bg-card px-3.5 py-3 text-[15px]"></textarea>
            @error('note')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button type="button" wire:click="accept" wire:loading.attr="disabled" wire:target="accept, decline" class="sm:flex-1">Accepter</x-button>
                <x-button type="button" variant="secondary" wire:click="decline" wire:loading.attr="disabled" wire:target="accept, decline" class="sm:flex-1">Refuser</x-button>
            </div>
        </section>
    @endif

    @if ($can['declare'])
        <section class="{{ $box }}"><h2 class="text-[16px] font-extrabold">Votre contribution a eu lieu ?</h2><p class="text-[13px] text-muted">Dites-le : le bénéficiaire devra le confirmer pour qu’elle compte comme réalisée.</p>
            <x-button type="button" wire:click="declareDone" wire:loading.attr="disabled" wire:target="declareDone">Déclarer ma contribution réalisée</x-button></section>
    @endif

    @if ($can['confirm'])
        <section class="{{ $box }}"><h2 class="text-[16px] font-extrabold">{{ $proposal->state === \App\Enums\ProposalState::Declared ? 'La personne déclare avoir contribué' : 'La contribution a-t-elle eu lieu ?' }}</h2><p class="text-[13px] text-muted">Votre confirmation est explicite : elle seule fait de cette proposition une contribution réalisée.</p>
            <x-button type="button" variant="accent" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" wire:confirm="Confirmer que cette contribution a bien été réalisée ?">Confirmer la réalisation</x-button></section>
    @endif

    @if ($can['withdraw'])
        <x-button type="button" variant="secondary" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw" wire:confirm="Retirer cette proposition ?">Retirer ma proposition</x-button>
    @endif
</div>
