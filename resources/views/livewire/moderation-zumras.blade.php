<div class="flex flex-col gap-3.5">
    <h1 class="text-[20px] font-extrabold">Modération des ZUMRA</h1>
    @include('livewire.partials.moderation-nav', ['current' => 'zumras'])
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">
            @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    <p class="text-[13px] leading-normal text-muted">La Discussion d’une ZUMRA est privée : vous ne lisez que les messages signalés. Chaque décision exige un motif et est inscrite au journal.</p>
    <nav aria-label="Files de ZUMRA" class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('examiner')" @class(['min-h-11 rounded-chip px-4 text-[13px] font-bold', 'bg-ink text-white' => $tab === 'examiner', 'border border-line bg-card' => $tab !== 'examiner'])>À examiner ({{ $toReviewCount }})</button>
        <button type="button" wire:click="setTab('suspendues')" @class(['min-h-11 rounded-chip px-4 text-[13px] font-bold', 'bg-ink text-white' => $tab === 'suspendues', 'border border-line bg-card' => $tab !== 'suspendues'])>Suspendues et masqués ({{ $suspendedCount }})</button>
    </nav>

    @if ($tab === 'examiner')
        @foreach ($zumras as $zumra)
            <section wire:key="zumra-{{ $zumra->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex items-center justify-between gap-2"><strong class="break-words text-[15px]">{{ $zumra->name }}</strong><span class="text-[11px] font-bold text-muted">ZUMRA</span></div>
                <p class="whitespace-pre-line break-words text-[14px]">{{ \Illuminate\Support\Str::limit($zumra->mission, 300) }}</p>
                <a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-link">Voir la ZUMRA</a>
                <div class="rounded-button bg-ground p-3">
                    <strong class="text-[12px]">Signalements reçus</strong>
                    @foreach ($zumra->reports as $report)
                        <p class="text-[12px]">{{ $report->category->label() }}@if ($report->details) — {{ $report->details }}@endif</p>
                    @endforeach
                </div>
                <label for="reason-{{ $zumra->id }}" class="text-[13px] font-bold">Motif de la décision</label>
                <textarea id="reason-{{ $zumra->id }}" wire:model="reasons.{{ $zumra->id }}" rows="2" maxlength="500" class="min-h-20 rounded-button border border-line p-3 text-[13px]"></textarea>
                <div class="flex flex-wrap gap-2">
                    <x-button type="button" wire:click="suspend('{{ $zumra->id }}')">Suspendre la ZUMRA</x-button>
                    <x-button type="button" variant="secondary" wire:click="dismissZumra('{{ $zumra->id }}')">Classer sans suite</x-button>
                </div>
            </section>
        @endforeach
        @foreach ($posts as $post)
            <section wire:key="post-{{ $post->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex items-center justify-between gap-2"><strong class="break-words text-[14px]">{{ $post->author?->name ?? 'Compte supprimé' }} · {{ $post->zumra->name }}</strong><span class="text-[11px] font-bold text-muted">Message</span></div>
                <p class="whitespace-pre-line break-words text-[14px]">{{ $post->body }}</p>
                <div class="rounded-button bg-ground p-3">
                    <strong class="text-[12px]">Signalements reçus</strong>
                    @foreach ($post->reports as $report)
                        <p class="text-[12px]">{{ $report->category->label() }}@if ($report->details) — {{ $report->details }}@endif</p>
                    @endforeach
                </div>
                <label for="reason-{{ $post->id }}" class="text-[13px] font-bold">Motif de la décision</label>
                <textarea id="reason-{{ $post->id }}" wire:model="reasons.{{ $post->id }}" rows="2" maxlength="500" class="min-h-20 rounded-button border border-line p-3 text-[13px]"></textarea>
                <div class="flex flex-wrap gap-2">
                    <x-button type="button" wire:click="hidePost('{{ $post->id }}')">Masquer le message</x-button>
                    <x-button type="button" variant="secondary" wire:click="dismissPost('{{ $post->id }}')">Classer sans suite</x-button>
                </div>
            </section>
        @endforeach
        @if ($zumras->isEmpty() && $posts->isEmpty())
            <p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Aucun signalement à examiner.</p>
        @endif
    @else
        @foreach ($suspended as $zumra)
            <section wire:key="suspended-{{ $zumra->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex items-center justify-between gap-2"><strong class="break-words text-[15px]">{{ $zumra->name }}</strong><span class="text-[11px] font-bold text-muted">ZUMRA suspendue</span></div>
                <p class="text-[12px]"><strong>Motif :</strong> {{ $zumra->suspension_reason }}</p>
                <label for="reason-{{ $zumra->id }}" class="text-[13px] font-bold">Motif du rétablissement (facultatif)</label>
                <textarea id="reason-{{ $zumra->id }}" wire:model="reasons.{{ $zumra->id }}" rows="2" maxlength="500" class="min-h-20 rounded-button border border-line p-3 text-[13px]"></textarea>
                <div class="flex flex-wrap gap-2"><x-button type="button" wire:click="reinstate('{{ $zumra->id }}')">Rétablir la ZUMRA</x-button></div>
            </section>
        @endforeach
        @foreach ($hiddenPosts as $post)
            <section wire:key="hidden-{{ $post->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex items-center justify-between gap-2"><strong class="break-words text-[14px]">{{ $post->author?->name ?? 'Compte supprimé' }} · {{ $post->zumra->name }}</strong><span class="text-[11px] font-bold text-muted">Message masqué</span></div>
                <p class="whitespace-pre-line break-words text-[14px]">{{ $post->body }}</p>
                <p class="text-[12px]"><strong>Motif :</strong> {{ $post->hidden_reason }}</p>
                <label for="reason-{{ $post->id }}" class="text-[13px] font-bold">Motif du rétablissement (facultatif)</label>
                <textarea id="reason-{{ $post->id }}" wire:model="reasons.{{ $post->id }}" rows="2" maxlength="500" class="min-h-20 rounded-button border border-line p-3 text-[13px]"></textarea>
                <div class="flex flex-wrap gap-2"><x-button type="button" wire:click="restorePost('{{ $post->id }}')">Rétablir le message</x-button></div>
            </section>
        @endforeach
        @if ($suspended->isEmpty() && $hiddenPosts->isEmpty())
            <p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Aucune ZUMRA suspendue, aucun message masqué.</p>
        @endif
    @endif
</div>
