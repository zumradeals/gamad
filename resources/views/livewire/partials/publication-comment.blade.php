@php
    $visible = $comment->isVisible();
    $own = $viewer && $viewer->id === $comment->author_id;
@endphp
<article wire:key="comment-{{ $comment->id }}" @class(['flex flex-col gap-2 border-t border-line-soft pt-3', 'ml-5 border-l-2 border-l-line pl-3' => $depth === 1]) aria-label="Commentaire de {{ $comment->author->name }}">
    <div class="flex items-center gap-2">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-type-learning-bg text-[11px] font-extrabold text-ink">{{ $comment->author->initials() }}</span>
        <div class="min-w-0 flex-1"><strong class="text-[13px]">{{ $comment->author->name }}</strong> <span class="text-[11px] text-muted">{{ $comment->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans(['short' => true]) }}</span></div>
    </div>
    @if ($comment->deleted_at)
        <p class="text-[13px] italic text-muted">Commentaire supprimé par son auteur.</p>
    @elseif ($comment->hidden_at)
        <p class="text-[13px] italic text-muted">Commentaire masqué par la modération.</p>
        @if ($own)
            <p class="text-[12px] text-muted">Motif : {{ $comment->hidden_reason }}</p>
            <button type="button" wire:click="explain('{{ $comment->id }}')" class="min-h-11 self-start text-[12px] font-semibold text-link underline">Présenter mon explication</button>
            @if ($explainId === $comment->id)
                <form wire:submit="sendExplanation" class="flex flex-col gap-2">
                    <textarea wire:model="explanation" maxlength="500" rows="3" aria-label="Mon explication" class="w-full rounded-button border border-line p-3 text-[13px]"></textarea>
                    @error('reason')<span class="text-[12px] text-danger">{{ $message }}</span>@enderror
                    <x-button type="submit">Envoyer mon explication</x-button>
                </form>
            @endif
        @endif
    @elseif ($visible)
        @if ($editingId === $comment->id)
            <form wire:submit="saveEdit" class="flex flex-col gap-2">
                <textarea wire:model="editBody" maxlength="500" rows="3" aria-label="Modifier mon commentaire" class="w-full rounded-button border border-line p-3 text-[13px]"></textarea>
                @error('body')<p role="alert" class="text-[12px] text-danger">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2"><x-button type="submit">Enregistrer</x-button><x-button type="button" variant="secondary" wire:click="cancelEdit">Annuler</x-button></div>
            </form>
        @else
            <p class="whitespace-pre-line break-words text-[14px]">{{ $comment->body }}</p>
            @if ($viewer?->isActive())
                <div class="flex flex-wrap items-center gap-3 text-[12px] font-semibold text-link">
                    @if ($depth === 0)<button type="button" wire:click="reply('{{ $comment->id }}')" class="min-h-11">Répondre</button>@endif
                    @if ($own)
                        <button type="button" wire:click="edit('{{ $comment->id }}')" class="min-h-11">Modifier</button>
                        <button type="button" wire:click="delete('{{ $comment->id }}')" wire:confirm="Supprimer ce commentaire ?" class="min-h-11">Supprimer</button>
                    @else
                        <button type="button" wire:click="signaler('{{ $comment->id }}')" class="min-h-11">Signaler</button>
                    @endif
                </div>
            @endif
        @endif
    @endif
</article>
