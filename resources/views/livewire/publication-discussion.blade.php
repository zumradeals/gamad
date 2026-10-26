<section id="discussion" aria-labelledby="discussion-titre" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
    <h2 id="discussion-titre" class="text-[18px] font-extrabold">Discussion publique</h2>
    <p class="text-[13px] leading-normal text-muted">Échangez autour de cette publication. Pour proposer une aide ou engager une conversation privée, utilisez l'action prévue sur la publication.</p>

    @if ($notice !== '')<p role="status" class="rounded-button bg-type-knowledge-bg p-3 text-[13px] text-type-knowledge-fg">{{ $notice }}</p>@endif

    @forelse ($comments as $comment)
        @include('livewire.partials.publication-comment', ['comment' => $comment, 'depth' => 0])
        @if ($comment->isVisible())
            @foreach ($comment->replies as $reply)
                @include('livewire.partials.publication-comment', ['comment' => $reply, 'depth' => 1])
            @endforeach
        @endif
    @empty
        <p class="rounded-button bg-ground p-3 text-[13px] text-muted">Aucun commentaire pour l'instant. Une discussion peut commencer simplement par une question ou une expérience.</p>
    @endforelse

    @if ($hasMore)
        <button type="button" wire:click="more" class="min-h-11 self-start text-[13px] font-bold text-link">Afficher d'autres commentaires</button>
    @endif

    @if ($viewer?->isActive())
        <form wire:submit="send" class="flex flex-col gap-2 border-t border-line-soft pt-4">
            @if ($replyTo)
                <div class="flex items-center justify-between gap-2 text-[13px]"><strong>Votre réponse à un commentaire</strong><button type="button" wire:click="cancelReply" class="min-h-11 underline">Annuler</button></div>
            @else
                <label for="comment-body" class="text-[13px] font-bold">Participer à la discussion</label>
            @endif
            <textarea id="comment-body" wire:model="body" maxlength="500" rows="3" placeholder="Écrivez une question, un conseil ou une expérience…" class="min-h-24 w-full rounded-button border border-chip-line bg-card p-3 text-[14px]"></textarea>
            @error('body')<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror
            <div class="flex items-center justify-between gap-2"><span class="text-[12px] text-muted">500 caractères maximum</span><x-button type="submit" wire:loading.attr="disabled" wire:target="send">Publier le commentaire</x-button></div>
        </form>
    @else
        <p class="border-t border-line-soft pt-4 text-[13px] text-muted"><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-bold underline">Connectez-vous</a> avec un compte vérifié pour commenter.</p>
    @endif

    @if ($reportId && $viewer?->isActive())
        <form wire:submit="sendReport" class="flex flex-col gap-3 rounded-button border border-line bg-ground p-3">
            <h3 class="text-[15px] font-extrabold">Signaler ce commentaire</h3>
            <label class="text-[13px] font-bold" for="comment-report-reason">Motif</label>
            <select id="comment-report-reason" wire:model="reportCategory" class="min-h-11 rounded-button border border-chip-line bg-card p-2.5 text-[14px]">
                <option value="">Choisissez un motif</option>
                @foreach (\App\Enums\ReportCategory::cases() as $category)<option value="{{ $category->value }}">{{ $category->label() }}</option>@endforeach
            </select>
            @error('reportCategory')<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror
            <label class="text-[13px] font-bold" for="comment-report-details">Précision facultative</label>
            <textarea id="comment-report-details" wire:model="reportDetails" maxlength="280" rows="2" class="w-full rounded-button border border-chip-line bg-card p-3 text-[14px]"></textarea>
            @error('reportDetails')<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror
            <p class="text-[12px] text-muted">Le signalement est privé : son auteur ne connaîtra pas votre identité.</p>
            <div class="flex flex-wrap gap-2"><x-button type="submit">Envoyer le signalement</x-button><x-button type="button" variant="secondary" wire:click="cancelReport">Annuler</x-button></div>
        </form>
    @endif
</section>
