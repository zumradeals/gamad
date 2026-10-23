<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('zumras.show', $zumra) }}" aria-label="Retour à la ZUMRA" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Inviter des personnes</h1>
    </div>

    <p class="text-[14px] leading-normal text-muted">Dans <strong>{{ $zumra->name }}</strong> : cherchez ce que les personnes peuvent apporter, puis invitez-les avec un mot. L’invitation est valable {{ \App\Models\Invitation::VALID_DAYS }} jours.</p>

    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    <div class="flex min-h-12 items-center gap-2.5 rounded-button border border-chip-line bg-card px-3.5 text-muted focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-link">
        <x-icon name="search" :size="20" />
        <label for="recherche" class="sr-only">Rechercher une capacité ou un nom</label>
        <input id="recherche" type="search" wire:model.live.debounce.300ms="query" maxlength="100" autocomplete="off" placeholder="Une capacité ou un nom"
               class="min-h-11 min-w-0 flex-1 border-0 bg-transparent text-[15px] text-ink outline-none placeholder:text-muted">
    </div>

    <div wire:loading.delay wire:target="query" class="text-[13px] font-semibold text-muted" role="status">Recherche en cours…</div>

    @forelse ($shown as $profile)
        @php
            $personUser = $profile->user;
            $invited = in_array($profile->user_id, $invitedIds, true);
            $requested = in_array($profile->user_id, $requestedIds, true);
        @endphp
        <article wire:key="invite-{{ $profile->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
            <div class="flex items-center gap-3">
                <x-avatar :profile="$profile" :size="48" />
                <div class="flex min-w-0 flex-col">
                    <a href="{{ route('personnes.show', $profile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $personUser->name }}</a>
                    @if ($profile->city)
                        <span class="-mt-2 text-[12px] text-muted">{{ $profile->city->label() }}</span>
                    @endif
                </div>
            </div>

            @if ($profile->offeredSkills->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    <span class="text-[11px] font-extrabold tracking-[0.8px] text-type-knowledge-fg">PEUT APPORTER</span>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($profile->offeredSkills as $skill)
                            <li class="rounded-chip bg-type-knowledge-bg px-2.5 py-[5px] text-[13px] font-bold text-type-knowledge-fg">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($invited)
                <span class="inline-flex min-h-11 items-center gap-1.5 text-[14px] font-extrabold text-type-knowledge-fg"><x-icon name="check" :size="16" :stroke="3" />Invitation envoyée</span>
            @elseif ($requested)
                <p class="text-[14px] font-semibold text-muted">Cette personne a déjà demandé à rejoindre : <a href="{{ route('zumras.show', $zumra) }}">acceptez sa demande</a>.</p>
            @elseif ($inviting === $profile->id)
                <form wire:submit="send" class="flex flex-col gap-2">
                    <label for="mot-invitation" class="text-[13px] font-extrabold">Un mot pour {{ $personUser->name }} (facultatif)</label>
                    <textarea id="mot-invitation" wire:model="inviteWord" rows="3" maxlength="{{ \App\Models\Invitation::MESSAGE_MAX }}"
                              @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('inviteWord') ? 'border-danger' : 'border-chip-line'])
                              @if ($errors->has('inviteWord')) aria-invalid="true" aria-describedby="invite-erreur" @endif></textarea>
                    @error('inviteWord')
                        <p id="invite-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button wire:loading.attr="disabled" wire:target="send" class="sm:flex-1">Envoyer l’invitation</x-button>
                        <x-button type="button" variant="secondary" wire:click="stopInvite" class="sm:flex-1">Annuler</x-button>
                    </div>
                </form>
            @else
                <x-button type="button" wire:click="startInvite('{{ $profile->id }}')">Inviter</x-button>
            @endif
        </article>
    @empty
        <section aria-labelledby="vide" class="flex flex-col gap-2 rounded-card border border-line bg-card p-5">
            <h2 id="vide" class="text-[18px] font-extrabold">{{ $key !== '' ? 'Personne ne correspond à cette recherche.' : 'Personne à inviter pour l’instant.' }}</h2>
            <p class="text-muted">{{ $key !== '' ? 'Essayez une autre capacité.' : 'Les personnes qui indiquent ce qu’elles peuvent apporter apparaîtront ici.' }}</p>
        </section>
    @endforelse

    @if ($people->hasPages())
        <nav aria-label="Pages de résultats" class="flex items-center justify-between gap-3">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$people->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $people->currentPage() }} sur {{ $people->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $people->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
