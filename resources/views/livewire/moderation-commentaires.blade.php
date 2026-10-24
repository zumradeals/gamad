<div class="flex flex-col gap-3.5">
    <h1 class="text-[20px] font-extrabold">Modération des commentaires</h1>
    @include('livewire.partials.moderation-nav', ['current' => 'commentaires'])
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    <nav aria-label="Files de commentaires" class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('examiner')" @class(['min-h-11 rounded-chip px-4 text-[13px] font-bold', 'bg-ink text-white' => $tab === 'examiner', 'border border-line bg-card' => $tab !== 'examiner'])>À examiner ({{ $toReviewCount }})</button>
        <button type="button" wire:click="setTab('masques')" @class(['min-h-11 rounded-chip px-4 text-[13px] font-bold', 'bg-ink text-white' => $tab === 'masques', 'border border-line bg-card' => $tab !== 'masques'])>Masqués ({{ $hiddenCount }})</button>
    </nav>
    @forelse ($tab === 'examiner' ? $toReview : $hidden as $comment)
        <section wire:key="moderate-comment-{{ $comment->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
            <div class="flex items-center justify-between gap-2"><strong class="text-[14px]">{{ $comment->author?->name ?? 'Compte supprimé' }}</strong><span class="text-[11px] text-muted">{{ $comment->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</span></div>
            <p class="whitespace-pre-line break-words text-[14px]">{{ $comment->body ?? 'Commentaire supprimé' }}</p>
            <a href="{{ route('publications.show', $comment->publication).'#discussion' }}" class="text-[12px] font-semibold text-link">Voir la publication (selon les droits)</a>
            @if ($tab === 'examiner')
                <div class="rounded-button bg-ground p-3">
                    <strong class="text-[12px]">Signalements reçus</strong>
                    @foreach ($comment->reports as $report)
                        <p class="text-[12px]">{{ $report->category->label() }}@if ($report->details) — {{ $report->details }}@endif</p>
                    @endforeach
                </div>
            @else
                <p class="text-[12px]"><strong>Motif :</strong> {{ $comment->hidden_reason }}</p>
                <p class="text-[12px]"><strong>Explication de l'auteur :</strong> {{ $comment->author_explanation ?: 'Aucune explication reçue.' }}</p>
            @endif
            <label for="reason-{{ $comment->id }}" class="text-[13px] font-bold">Motif de la décision</label>
            <textarea id="reason-{{ $comment->id }}" wire:model="reasons.{{ $comment->id }}" rows="2" maxlength="500" class="min-h-20 rounded-button border border-line p-3 text-[13px]"></textarea>
            @error('reason')<p role="alert" class="text-[12px] text-danger">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-2">
                @if ($tab === 'examiner')
                    <x-button type="button" wire:click="hide('{{ $comment->id }}')">Masquer</x-button>
                    <x-button type="button" variant="secondary" wire:click="dismiss('{{ $comment->id }}')">Classer sans suite</x-button>
                @else
                    <x-button type="button" wire:click="restore('{{ $comment->id }}')">Rétablir</x-button>
                @endif
            </div>
        </section>
    @empty
        <p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Aucun commentaire dans cette file.</p>
    @endforelse
</div>
