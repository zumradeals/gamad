@php
    $date = fn ($value, $format = 'j F Y') => $value->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat($format);
    $days = (int) config('gamad.moderation.explanation_days');
@endphp

<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Espace" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Modération des personnes</h1>
    </div>

    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    @include('livewire.partials.moderation-nav', ['current' => 'personnes'])

    <p class="text-[13px] leading-normal text-muted">
        Une personne n’est jamais suspendue sans avoir pu s’expliquer : vous lui envoyez d’abord un avis, avec le motif. Elle dispose de {{ $days }} jours pour répondre.
        Vous pouvez suspendre dès sa réponse, ou à la fin du délai. Chaque acte est inscrit au journal.
    </p>

    <div wire:loading.delay wire:target="issue, suspend, closeNotice, reinstate" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    <section aria-labelledby="avis-en-cours" class="flex flex-col gap-3">
        <h2 id="avis-en-cours" class="text-[18px] font-extrabold">Avis en cours ({{ $notices->count() }})</h2>

        @forelse ($notices as $notice)
            @php $id = $notice->id; @endphp
            <section wire:key="avis-{{ $id }}" aria-label="Avis pour {{ $notice->user->name }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[15px] font-extrabold">{{ $notice->user->name }}</span>
                    <span class="text-[12px] text-muted">Avis du {{ $date($notice->created_at) }}{{ $notice->issuer ? ' par '.$notice->issuer->name : '' }}</span>
                </div>
                <p class="whitespace-pre-line break-words text-[14px] leading-normal"><strong>Motif de l’avis :</strong> {{ $notice->reason }}</p>

                <div class="flex flex-col gap-1 rounded-button bg-ground p-3">
                    <span class="text-[12px] font-extrabold text-muted">EXPLICATION DE LA PERSONNE</span>
                    @if ($notice->isExplained())
                        <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ $notice->explanation }}</p>
                    @elseif ($notice->readyForDecision())
                        <p class="text-[13px] text-muted">Pas d’explication : le délai est écoulé depuis le {{ $date($notice->deadline()) }}.</p>
                    @else
                        <p class="text-[13px] text-muted">Pas encore d’explication. Elle a jusqu’au {{ $date($notice->deadline(), 'j F Y, H:i') }}.</p>
                    @endif
                </div>

                @can('sanction-person', $notice->user)
                    <div class="flex flex-col gap-2">
                        <label for="motif-{{ $id }}" class="text-[13px] font-extrabold">Motif (obligatoire pour suspendre : la personne le verra)</label>
                        <textarea id="motif-{{ $id }}" wire:model="reasons.{{ $id }}" rows="2" maxlength="{{ \App\Services\PersonModeration::REASON_MAX }}"
                                  @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has("reasons.$id") ? 'border-danger' : 'border-chip-line'])
                                  @if ($errors->has("reasons.$id")) aria-invalid="true" aria-describedby="motif-erreur-{{ $id }}" @endif></textarea>
                        @error("reasons.$id")
                            <p id="motif-erreur-{{ $id }}" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-col gap-2 sm:flex-row">
                            @if ($notice->readyForDecision())
                                <x-button type="button" wire:click="suspend('{{ $id }}')" wire:loading.attr="disabled" wire:target="suspend, closeNotice" class="sm:flex-1">Suspendre la personne</x-button>
                            @endif
                            <x-button type="button" variant="secondary" wire:click="closeNotice('{{ $id }}')" wire:loading.attr="disabled" wire:target="suspend, closeNotice" class="sm:flex-1">Classer l’avis sans suite</x-button>
                        </div>
                    </div>
                @else
                    <p class="text-[13px] text-muted">Seul le super administrateur décide pour cette personne.</p>
                @endcan
            </section>
        @empty
            <section class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucun avis en cours.</p>
            </section>
        @endforelse
    </section>

    <section aria-labelledby="envoyer-avis" class="flex flex-col gap-3">
        <h2 id="envoyer-avis" class="text-[18px] font-extrabold">Envoyer un avis</h2>

        <div class="flex flex-col gap-1.5">
            <label for="recherche-personne" class="text-[13px] font-extrabold">Chercher une personne par son nom</label>
            <input id="recherche-personne" type="search" wire:model.live.debounce.400ms="search" autocomplete="off" maxlength="100"
                   class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px]">
        </div>

        @if (mb_strlen(trim($search)) >= 2)
            @forelse ($found as $person)
                @php $id = $person->id; @endphp
                <section wire:key="personne-{{ $id }}" aria-label="{{ $person->name }}" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
                    <span class="text-[15px] font-extrabold">{{ $person->name }}</span>

                    @if ($noticeOf->has($id))
                        <p class="text-[13px] text-muted">Un avis est déjà en cours pour cette personne.</p>
                    @else
                        <label for="motif-{{ $id }}" class="text-[13px] font-extrabold">Motif de l’avis (la personne le verra)</label>
                        <textarea id="motif-{{ $id }}" wire:model="reasons.{{ $id }}" rows="2" maxlength="{{ \App\Services\PersonModeration::REASON_MAX }}"
                                  @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has("reasons.$id") ? 'border-danger' : 'border-chip-line'])
                                  @if ($errors->has("reasons.$id")) aria-invalid="true" aria-describedby="motif-erreur-{{ $id }}" @endif></textarea>
                        @error("reasons.$id")
                            <p id="motif-erreur-{{ $id }}" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                        @enderror
                        <x-button type="button" wire:click="issue('{{ $id }}')" wire:loading.attr="disabled" wire:target="issue">Envoyer l’avis</x-button>
                    @endif
                </section>
            @empty
                <p class="text-[14px] text-muted">Aucune personne ne correspond à « {{ $search }} ».</p>
            @endforelse
        @endif
    </section>

    <section aria-labelledby="suspendues" class="flex flex-col gap-3">
        <h2 id="suspendues" class="text-[18px] font-extrabold">Personnes suspendues ({{ $suspended->count() }})</h2>

        @forelse ($suspended as $person)
            @php $id = $person->id; @endphp
            <section wire:key="suspendue-{{ $id }}" aria-label="{{ $person->name }}, suspendue" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[15px] font-extrabold">{{ $person->name }}</span>
                    <span class="text-[12px] text-muted">Suspendue le {{ $date($person->suspended_at) }}{{ $person->suspender ? ' par '.$person->suspender->name : '' }}</span>
                </div>
                <p class="whitespace-pre-line break-words text-[14px] leading-normal"><strong>Motif :</strong> {{ $person->suspension_reason }}</p>

                <label for="motif-{{ $id }}" class="text-[13px] font-extrabold">Raison du rétablissement (facultative, inscrite au journal)</label>
                <textarea id="motif-{{ $id }}" wire:model="reasons.{{ $id }}" rows="2" maxlength="{{ \App\Services\PersonModeration::REASON_MAX }}"
                          @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has("reasons.$id") ? 'border-danger' : 'border-chip-line'])
                          @if ($errors->has("reasons.$id")) aria-invalid="true" aria-describedby="motif-erreur-{{ $id }}" @endif></textarea>
                @error("reasons.$id")
                    <p id="motif-erreur-{{ $id }}" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
                <x-button type="button" variant="secondary" wire:click="reinstate('{{ $id }}')" wire:loading.attr="disabled" wire:target="reinstate">Rétablir le compte</x-button>
            </section>
        @empty
            <section class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucune personne suspendue.</p>
            </section>
        @endforelse
    </section>
</div>
