<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Espace" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Modération</h1>
    </div>

    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    @include('livewire.partials.moderation-nav', ['current' => 'file'])

    <nav aria-label="Files de modération" class="flex gap-2">
        <button type="button" wire:click="setTab('examiner')" aria-pressed="{{ $tab === 'examiner' ? 'true' : 'false' }}"
                @class(['min-h-11 rounded-chip px-4 text-[13px]', 'bg-ink font-bold text-white' => $tab === 'examiner', 'border border-chip-line bg-card font-semibold text-ink' => $tab !== 'examiner'])>À examiner ({{ $toReviewCount }})</button>
        <button type="button" wire:click="setTab('masquees')" aria-pressed="{{ $tab === 'masquees' ? 'true' : 'false' }}"
                @class(['min-h-11 rounded-chip px-4 text-[13px]', 'bg-ink font-bold text-white' => $tab === 'masquees', 'border border-chip-line bg-card font-semibold text-ink' => $tab !== 'masquees'])>Masquées ({{ $hiddenCount }})</button>
    </nav>

    <div wire:loading.delay wire:target="setTab, hide, dismiss, restore" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    @if ($tab === 'examiner')
        @forelse ($toReview as $publication)
            <section wire:key="review-{{ $publication->id }}" aria-label="Publication signalée de {{ $publication->author->name }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $publication->type->badgeClasses() }}">{{ $publication->type->label() }}</span>
                    <span class="text-[13px] font-extrabold">{{ $publication->author->name }}</span>
                    <span class="text-[12px] text-muted">{{ $publication->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y, H:i') }}</span>
                </div>
                <p class="whitespace-pre-line break-words text-[15px] leading-normal">{{ $publication->body }}</p>

                <div class="flex flex-col gap-1.5 rounded-button bg-ground p-3">
                    <span class="text-[12px] font-extrabold text-muted">{{ $publication->reports->count() }} {{ $publication->reports->count() > 1 ? 'SIGNALEMENTS' : 'SIGNALEMENT' }}</span>
                    <ul class="flex flex-col gap-1">
                        @foreach ($publication->reports as $report)
                            <li class="text-[13px]"><strong>{{ $report->category->label() }}</strong>@if (filled($report->details)) — {{ $report->details }}@endif</li>
                        @endforeach
                    </ul>
                </div>

                @include('livewire.partials.moderation-actions', ['publication' => $publication, 'mode' => 'review'])
            </section>
        @empty
            <section class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucun signalement à examiner.</p>
                <p class="mt-1 text-muted">La file se remplit quand une personne signale une publication.</p>
            </section>
        @endforelse
    @else
        @forelse ($hidden as $publication)
            <section wire:key="hidden-{{ $publication->id }}" aria-label="Publication masquée de {{ $publication->author->name }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $publication->type->badgeClasses() }}">{{ $publication->type->label() }}</span>
                    <span class="text-[13px] font-extrabold">{{ $publication->author->name }}</span>
                    <span class="text-[12px] text-muted">Masquée le {{ $publication->hidden_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}{{ $publication->hider ? ' par '.$publication->hider->name : '' }}</span>
                </div>
                <p class="whitespace-pre-line break-words text-[15px] leading-normal">{{ $publication->body }}</p>
                <p class="text-[13px]"><strong>Motif :</strong> {{ $publication->hidden_reason }}</p>

                <div class="flex flex-col gap-1 rounded-button bg-ground p-3">
                    <span class="text-[12px] font-extrabold text-muted">EXPLICATION DE L’AUTEUR</span>
                    @if ($publication->explained_at)
                        <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ $publication->author_explanation }}</p>
                    @else
                        <p class="text-[13px] text-muted">Pas encore d’explication.</p>
                    @endif
                </div>

                @include('livewire.partials.moderation-actions', ['publication' => $publication, 'mode' => 'hidden'])
            </section>
        @empty
            <section class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucune publication masquée.</p>
            </section>
        @endforelse
    @endif
</div>
