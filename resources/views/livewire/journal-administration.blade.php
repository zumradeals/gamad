<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Espace" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Journal d’administration</h1>
    </div>

    @include('livewire.partials.moderation-nav', ['current' => 'journal'])

    <p class="text-[13px] leading-normal text-muted">
        Chaque acte de modération et d’administration y est inscrit. Le journal se lit seulement : rien ne s’y modifie ni ne s’y efface.
        @cannot('super-admin') Vous y lisez les actes de modération. @endcannot
    </p>

    <div class="flex flex-col gap-1.5">
        <label for="famille" class="text-[13px] font-extrabold">Type d’acte</label>
        <select id="famille" wire:model.live="family" class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px] md:max-w-xs">
            <option value="">Tous</option>
            @foreach ($families as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div wire:loading.delay wire:target="family, previousPage, nextPage" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    <ol class="flex flex-col gap-2.5">
        @forelse ($actions as $action)
            @php
                $subject = \App\Support\JournalReader::subjectName($action, $subjects);
                $details = \App\Support\JournalReader::details($action);
            @endphp
            <li wire:key="acte-{{ $action->id }}" class="flex flex-col gap-1 rounded-card border border-line bg-card p-3.5">
                <p class="break-words text-[14px] leading-snug">
                    <strong class="font-extrabold">{{ $action->actor_label }}</strong>
                    {{ \App\Support\JournalReader::label($action->action) }}@if ($subject) <strong class="font-extrabold">{{ $subject }}</strong>@endif.
                </p>
                @if (filled($action->reason))
                    <p class="whitespace-pre-line break-words text-[13px] leading-normal"><strong>Motif :</strong> {{ $action->reason }}</p>
                @endif
                @foreach ($details as $line)
                    <p class="break-words text-[13px] leading-normal text-muted">{{ $line }}</p>
                @endforeach
                <p class="text-[12px] text-muted">{{ $action->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y, H:i') }}</p>
            </li>
        @empty
            <li class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucun acte à afficher.</p>
                <p class="mt-1 text-muted">Les actes apparaissent ici dès qu’ils sont accomplis.</p>
            </li>
        @endforelse
    </ol>

    @if ($actions->hasPages())
        <nav aria-label="Pages du journal" class="flex items-center justify-between gap-2">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$actions->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $actions->currentPage() }} sur {{ $actions->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $actions->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
