@php
    $tz = 'Africa/Abidjan';
    $opened = $conversation->created_at->copy()->timezone($tz)->locale('fr');
    $openedLabel = $opened->isToday() ? 'aujourd’hui' : 'le '.$opened->translatedFormat('j F Y');
    $about = $conversation->publication ? \Illuminate\Support\Str::limit($conversation->publication->body, 40) : null;
    $canOpenOther = $otherProfile && Gate::forUser($viewer)->allows('view', $otherProfile->setRelation('user', $other));
@endphp

<div class="flex flex-col gap-3.5" x-data  x-on:message-sent.window="$nextTick(() => window.scrollTo(0, document.body.scrollHeight))">
    <div class="-mx-4 -mt-4 flex min-h-16 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Espace" class="flex size-11 shrink-0 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        @if ($otherProfile)
            <x-avatar :profile="$otherProfile" :size="40" />
        @else
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-line-soft text-[14px] font-extrabold text-muted" aria-hidden="true">?</span>
        @endif
        <div class="ml-2 flex min-w-0 flex-1 flex-col">
            <h1 class="truncate text-[15px] font-extrabold">{{ $other?->name ?? 'Personne supprimée' }}</h1>
            @if ($conversation->publication && Gate::forUser($viewer)->allows('view', $conversation->publication))
                <a href="{{ route('publications.show', $conversation->publication) }}" class="-my-3.5 truncate py-3.5 text-[12px] font-semibold">À propos de : {{ $about }}</a>
            @elseif (! $conversation->publication && $conversation->origin !== 'relation')
                <span class="text-[12px] text-muted">Publication supprimée</span>
            @endif
        </div>
    </div>

    <div class="rounded-button bg-type-call-bg px-3 py-2.5 text-[12px] font-semibold leading-normal text-type-call-fg">
        Votre e-mail et votre téléphone restent privés. Ne les partagez que si vous le décidez.
    </div>

    <section aria-labelledby="prochaine-action" class="rounded-card border border-line bg-card p-4 md:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="prochaine-action" class="text-[18px] font-extrabold">Notre prochaine action</h2>@if ($conversation->next_action)<span class="rounded-chip px-3 py-1 text-[12px] font-bold {{ $conversation->next_action_confirmed_at ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'bg-type-call-bg text-type-call-fg' }}">{{ $conversation->next_action_confirmed_at ? 'Convenue ensemble' : 'À confirmer' }}</span>@endif</div>
        @if ($conversation->next_action)
            <p class="mt-3 whitespace-pre-line text-[15px] leading-relaxed [overflow-wrap:anywhere]">{{ $conversation->next_action }}</p>
            @if ($conversation->next_action_date)<p class="mt-2 text-[14px] font-semibold">Date {{ $conversation->next_action_confirmed_at ? 'convenue' : 'proposée' }} : {{ $conversation->next_action_date->copy()->locale('fr')->translatedFormat('j F Y') }}</p>@endif
            @unless ($conversation->next_action_confirmed_at)<p class="mt-2 text-[13px] text-muted">{{ $conversation->next_action_by === $viewer->id ? 'Vous avez proposé cette action. Elle attend la confirmation de l’autre personne.' : 'L’autre personne propose cette action. Confirmez-la si elle vous convient, ou proposez-en une autre.' }}</p>@endunless
            @if ($canSend && ! $editingAction)
                <div class="mt-3 flex flex-wrap gap-2">
                    @if (! $conversation->next_action_confirmed_at && $conversation->next_action_by !== $viewer->id)<x-button type="button" wire:click="confirmAction({{ $conversation->next_action_revision }})" wire:loading.attr="disabled" variant="accent">Confirmer cette action</x-button>@endif
                    <x-button type="button" wire:click="withdrawAction({{ $conversation->next_action_revision }})" wire:loading.attr="disabled" variant="secondary">Retirer cette action</x-button>
                </div>
            @endif
        @else
            <p class="mt-2 text-[14px] leading-relaxed text-muted">Convenez d’un premier geste concret : ce que vous allez faire ensemble et, si utile, à quelle date.</p>
        @endif
        @error('action')<p role="alert" class="mt-3 text-[14px] font-semibold text-danger">{{ $message }}</p>@enderror
        @if ($canSend)
            @if ($editingAction)
                <form wire:submit="proposeAction" class="mt-4 flex flex-col gap-3 border-t border-line pt-4">
                    <label for="action-text" class="text-[13px] font-bold">Que proposez-vous de faire ?</label>
                    <textarea id="action-text" wire:model="actionText" rows="3" maxlength="{{ \App\Models\Conversation::ACTION_MAX }}" required placeholder="Ex. Préparer ensemble le tableau des dépenses" class="min-h-24 w-full rounded-button border border-chip-line px-3 py-3 text-[15px]" @error('actionText') aria-invalid="true" aria-describedby="action-text-error" @enderror></textarea>
                    @error('actionText')<p id="action-text-error" role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror
                    <x-wire-field name="actionDate" type="date" label="Date proposée (facultatif)" :min="now()->timezone('Africa/Abidjan')->toDateString()" />
                    <p class="text-[12px] leading-relaxed text-muted">Privé entre vous deux. Chaque nouvelle proposition remplace la précédente et doit être confirmée par l’autre personne. Aucun rappel automatique.</p>
                    <div class="flex flex-wrap gap-2"><x-button wire:loading.attr="disabled" wire:target="proposeAction">Proposer cette action</x-button><x-button type="button" variant="secondary" wire:click="closeAction">Annuler la saisie</x-button></div>
                </form>
            @else
                <x-button type="button" variant="secondary" wire:click="beginAction" class="mt-3">{{ $conversation->next_action ? 'Proposer une autre action' : 'Proposer notre prochaine action' }}</x-button>
            @endif
        @endif
    </section>

    <div wire:poll.{{ \App\Livewire\PageConversation::POLL_SECONDS }}s class="flex flex-col gap-3.5" aria-live="polite" aria-label="Messages">
        <span class="self-center text-[11px] font-bold text-muted">Conversation ouverte {{ $openedLabel }}</span>

        @forelse ($messages as $message)
            @php
                $mine = $message->sender_id === $viewer->getKey();
                $time = $message->created_at->copy()->timezone($tz)->format('H:i');
            @endphp
            <div wire:key="message-{{ $message->id }}" @class(['flex max-w-[80%] flex-col gap-0.5', 'items-end self-end' => $mine, 'items-start self-start' => ! $mine])>
                @if ($message->isErased())
                    <span class="rounded-[16px] border border-dashed border-chip-line px-3.5 py-2.5 text-[14px] italic text-muted">Message supprimé</span>
                @else
                    <span @class(['whitespace-pre-line break-words rounded-[16px] px-3.5 py-2.5 text-[15px] leading-[1.45]', 'bg-ink text-white' => $mine, 'border border-line bg-card' => ! $mine])>{{ $message->body }}</span>
                @endif
                <span class="text-[11px] text-muted">{{ $time }}</span>
            </div>
        @empty
            <p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Aucun message pour l’instant. Écrivez le premier.</p>
        @endforelse
    </div>

    @if ($canSend && ! $editingAction)
        <form wire:submit="send" class="sticky bottom-[76px] -mx-4 flex flex-col gap-1 border-t border-line bg-card px-3 py-2.5 md:static md:mx-0 md:rounded-card md:border">
            @error('body')
                <p role="alert" class="px-1 text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
            <div class="flex items-end gap-2">
                <label for="body" class="sr-only">Votre message</label>
                <textarea id="body" wire:model="body" rows="1" maxlength="{{ \App\Models\Conversation::MESSAGE_MAX }}" placeholder="Écrire un message…"
                          @class(['min-h-12 flex-1 resize-none rounded-[24px] border bg-card px-4 py-3 text-[15px]', $errors->has('body') ? 'border-danger' : 'border-chip-line'])
                          @if ($errors->has('body')) aria-invalid="true" @endif></textarea>
                <button type="submit" aria-label="Envoyer" wire:loading.attr="disabled" wire:target="send"
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-ink text-white disabled:opacity-60"><x-icon name="send" :size="20" /></button>
            </div>
        </form>
    @elseif (! $canSend)
        <p class="rounded-card border border-line bg-card p-4 text-[14px] font-semibold text-muted">{{ $other ? 'Cet échange n’accepte plus de messages. Vous pouvez toujours relire la conversation.' : 'Cette personne a supprimé son compte : vous pouvez relire la conversation, mais plus lui écrire.' }}</p>
    @endif
</div>
