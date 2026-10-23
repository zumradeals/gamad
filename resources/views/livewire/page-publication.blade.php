@php
    use App\Enums\GestureState;
    use App\Enums\NeedState;

    $state = $publication->need_state;
    $active = $publication->acceptsGestures();
    $stateClasses = match ($state) {
        NeedState::InProgress => 'bg-type-call-bg text-type-call-fg',
        NeedState::Resolved => 'bg-type-knowledge-bg text-type-knowledge-fg',
        NeedState::Closed => 'border border-line bg-ground text-muted',
        default => $type->badgeClasses(),
    };
    $live = (int) $publication->live_gestures_count;
@endphp

<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $viewer ? route('fil') : url('/') }}" aria-label="{{ $viewer ? 'Retour au Fil' : 'Retour à l’accueil' }}" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $isAuthor ? $type->ownTitle() : $type->label() }}</h1>
    </div>

    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    @if ($publication->isHidden() && ($isAuthor || ($viewer && $viewer->can('moderate'))))
        <section aria-labelledby="masquee" class="flex flex-col gap-2.5 rounded-card border border-danger bg-card p-4">
            <h2 id="masquee" class="text-[15px] font-extrabold text-danger">Cette publication est masquée</h2>
            <p class="text-[14px] leading-normal"><strong>Motif :</strong> {{ $publication->hidden_reason }}</p>
            @if ($isAuthor)
                <p class="text-[13px] leading-normal text-muted">Personne d’autre que vous et la modération ne la voit. Vous pouvez vous expliquer : la modération lira votre explication avant de décider.</p>
                <form wire:submit="explain" class="flex flex-col gap-2" x-data="{ n: {{ mb_strlen($explanation) }} }">
                    <label for="explication" class="text-[14px] font-extrabold">Votre explication</label>
                    <textarea id="explication" wire:model="explanation" rows="4" maxlength="{{ \App\Services\Moderation::EXPLANATION_MAX }}" x-on:input="n = $el.value.length"
                              @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('explanation') ? 'border-danger' : 'border-chip-line'])
                              @if ($errors->has('explanation')) aria-invalid="true" aria-describedby="explication-erreur" @endif></textarea>
                    <div class="flex justify-between gap-2">
                        @error('explanation')
                            <p id="explication-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                        @else
                            <span></span>
                        @enderror
                        <span class="text-[12px] text-muted"><span x-text="n"></span> / {{ \App\Services\Moderation::EXPLANATION_MAX }}</span>
                    </div>
                    <x-button wire:loading.attr="disabled" wire:target="explain">{{ $publication->explained_at ? 'Mettre à jour mon explication' : 'Envoyer mon explication' }}</x-button>
                    @if ($publication->explained_at)
                        <p class="text-[12px] text-muted">Explication envoyée. Vous pouvez la modifier tant que la publication est masquée.</p>
                    @endif
                </form>
            @else
                <a href="{{ route('moderation', ['onglet' => 'masquees']) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Ouvrir la file de modération</a>
            @endif
        </section>
    @endif

    @if ($isAuthor)
        <section aria-label="Ma publication" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $type->badgeClasses() }}">{{ $type->label() }}</span>
                @if ($state)
                    <span class="rounded-chip px-2.5 py-1 text-[12px] font-extrabold {{ $stateClasses }}">{{ $state->label() }}</span>
                @endif
            </div>
            <p class="whitespace-pre-line break-words text-[15px] leading-normal">{{ $publication->body }}</p>
            @if ($publication->skills->isNotEmpty())
                <ul class="flex flex-wrap gap-1.5" aria-label="Capacités">
                    @foreach ($publication->skills as $skill)
                        <li class="rounded-chip px-3 py-1 text-[13px] font-bold {{ $type->badgeClasses() }}">{{ $skill->name }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($publication->project)
                <a href="{{ route('projets.show', $publication->project) }}" class="inline-flex min-h-11 items-center self-start text-[13px] font-bold text-link">Projet : {{ $publication->project->name }}</a>
            @endif
            @if ($type->takesGestures())
                <span class="text-[13px] font-semibold text-muted">{{ $live > 0 ? $type->factLabel($live) : 'Personne n’a encore répondu.' }}</span>
            @endif
            @unless ($publication->isPublic())
                <span class="text-[12px] text-muted">Visible des personnes inscrites seulement.</span>
            @endunless
        </section>

        @if ($type->takesGestures())
        <h2 class="mt-1 text-[15px] font-extrabold">{{ $type->receivedTitle() }}</h2>

        @forelse ($received as $item)
            @php
                $helper = $item->author;
                $helperProfile = $helper->profile;
                $helperProfile?->setRelation('user', $helper);
                $canSee = $helperProfile && Gate::forUser($viewer)->allows('view', $helperProfile);
            @endphp
            <section wire:key="geste-{{ $item->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4" aria-label="{{ $type->gestureLabel() }} : {{ $helper->name }}">
                <div class="flex items-center gap-3">
                    @if ($helperProfile)
                        <x-avatar :profile="$helperProfile" :size="48" />
                    @endif
                    <div class="flex min-w-0 flex-col">
                        @if ($canSee)
                            <a href="{{ route('personnes.show', $helperProfile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $helper->name }}</a>
                        @else
                            <span class="break-words text-[15px] font-extrabold">{{ $helper->name }}</span>
                        @endif
                        @if ($helperProfile?->city)
                            <span class="-mt-2 text-[12px] text-muted">{{ $helperProfile->city->label() }}</span>
                        @endif
                    </div>
                </div>

                @if ($helperProfile && $helperProfile->offeredSkills->isNotEmpty())
                    <ul class="flex flex-wrap gap-1.5" aria-label="Ce que cette personne peut apporter">
                        @foreach ($helperProfile->offeredSkills->take(3) as $skill)
                            <li class="rounded-chip bg-type-knowledge-bg px-2.5 py-1 text-[12px] font-bold text-type-knowledge-fg">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (filled($item->message))
                    <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ $item->message }}</p>
                @endif

                @if ($item->state === GestureState::Sent && $active)
                    <div class="flex gap-2">
                        <x-button type="button" wire:click="accept('{{ $item->id }}')" wire:loading.attr="disabled" wire:target="accept, decline" class="flex-1 !text-[14px]">Accepter</x-button>
                        <x-button type="button" variant="secondary" wire:click="decline('{{ $item->id }}')" wire:loading.attr="disabled" wire:target="accept, decline" class="flex-1 !text-[14px]">Décliner</x-button>
                    </div>
                @elseif ($item->state === GestureState::Accepted)
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <span class="inline-flex min-h-11 flex-1 items-center gap-1.5 text-[13px] font-extrabold text-type-knowledge-fg"><x-icon name="check" :size="14" :stroke="3" />Acceptée</span>
                        @if ($item->conversation)
                            <x-button :href="route('conversations.show', $item->conversation)" class="!text-[14px]">Écrire</x-button>
                        @endif
                    </div>
                @elseif ($item->state === GestureState::Declined)
                    <span class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted">Déclinée</span>
                @else
                    <span class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted">En attente (la publication est close)</span>
                @endif
            </section>
        @empty
            <section class="rounded-card border border-line bg-card p-4">
                <p class="text-[14px] text-muted">Aucune réponse pour l’instant. Elles apparaîtront ici dès qu’une personne vous répondra.</p>
            </section>
        @endforelse
        @endif

        @if ($type->isNeed() && $active)
            <p class="text-[12px] leading-normal text-muted">Vous pouvez accepter plusieurs propositions.</p>

            <div class="mt-1 flex flex-col gap-2" x-data="{ confirming: null }">
                <div x-show="confirming === null" class="flex flex-col gap-2">
                    <button type="button" x-on:click="confirming = 'resolve'" class="min-h-[52px] rounded-button bg-accent px-3.5 text-[14px] font-bold text-ink">Marquer comme résolu</button>
                    <button type="button" x-on:click="confirming = 'close'" class="inline-flex min-h-11 items-center justify-center text-[14px] font-semibold text-link underline">Fermer sans suite</button>
                </div>

                <div x-show="confirming === 'resolve'" x-cloak class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                    @if ($accepted->isNotEmpty())
                        @include('livewire.partials.gratitude-form', ['mode' => 'resolve'])
                    @else
                        <p class="text-[14px] font-semibold">Marquer ce besoin comme résolu ? Il n’acceptera plus de proposition.</p>
                        <x-button type="button" variant="accent" wire:click="resolve" wire:loading.attr="disabled" wire:target="resolve">Oui, il est résolu</x-button>
                    @endif
                    <x-button type="button" variant="secondary" x-on:click="confirming = null">Annuler</x-button>
                </div>

                <div x-show="confirming === 'close'" x-cloak class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
                    <p class="text-[14px] font-semibold">Fermer ce besoin sans suite ? Il n’acceptera plus de proposition.</p>
                    <x-button type="button" wire:click="close" wire:loading.attr="disabled" wire:target="close">Oui, fermer sans suite</x-button>
                    <x-button type="button" variant="secondary" x-on:click="confirming = null">Annuler</x-button>
                </div>
            </div>
        @elseif ($type->isNeed())
            <p class="rounded-button border border-line bg-card px-4 py-3 text-[14px] font-semibold">{{ $state === NeedState::Resolved ? 'Ce besoin est résolu.' : 'Ce besoin est fermé sans suite.' }}</p>

            @if ($state === NeedState::Resolved && $gratitude)
                <section aria-labelledby="merci" class="flex flex-col gap-1.5 rounded-card border border-line bg-card p-4">
                    <h2 id="merci" class="text-[15px] font-extrabold">Votre remerciement</h2>
                    <p class="whitespace-pre-line break-words text-[14px] leading-normal">« {{ $gratitude->body }} »</p>
                    <span class="text-[13px] text-muted">Pour {{ $gratitude->helper->name }}.@if ($gratitude->isHidden()) Cette personne l’a masqué de son profil.@endif</span>
                </section>
            @elseif ($state === NeedState::Resolved && $accepted->isNotEmpty())
                <section aria-labelledby="remercier" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                    <h2 id="remercier" class="text-[15px] font-extrabold">Remercier une personne</h2>
                    @include('livewire.partials.gratitude-form', ['mode' => 'thank'])
                </section>
            @endif
        @endif
    @else
        <x-publication-card :publication="$publication" :viewer="$viewer" :gesture="$gesture" :link="false" />
    @endif

    <livewire:gesture-sheet />
</div>
