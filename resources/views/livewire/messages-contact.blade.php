@php $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y, H:i'); @endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace', ['rubrique' => 'administration']) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Administration</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Messages de contact</h1>
        <p class="text-muted">Réservé au super administrateur : ces messages contiennent des données personnelles. Chaque lecture est inscrite au journal d’administration. Répondez depuis votre messagerie ; les messages sont effacés au bout de {{ (int) config('gamad.contact.retention_months') }} mois.</p>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="etat" class="text-[13px] font-extrabold">État</label>
        <select id="etat" wire:model.live="state" class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px] md:max-w-xs">
            <option value="">Tous ({{ $newCount }} nouveau{{ $newCount > 1 ? 'x' : '' }})</option>
            @foreach (\App\Models\ContactMessage::STATES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div wire:loading.delay wire:target="state, open, handle, delete, previousPage, nextPage" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    @if ($opened)
        <section aria-labelledby="detail" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-card p-4 md:p-[18px]">
            <h2 id="detail" class="text-[18px] font-extrabold">{{ $opened->subjectLabel() }}</h2>
            <p class="text-[14px]"><strong>{{ $opened->name }}</strong> — <a href="mailto:{{ $opened->email }}" class="font-semibold underline">{{ $opened->email }}</a></p>
            <p class="text-[12px] text-muted">Reçu le {{ $date($opened->created_at) }} · {{ \App\Models\ContactMessage::STATES[$opened->state] }}@if ($opened->user_id) · envoyé depuis un compte connecté @endif</p>
            <p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $opened->body }}</p>
            <div class="flex flex-wrap gap-2">
                @if ($opened->state !== 'handled')<x-button type="button" wire:click="handle('{{ $opened->id }}')" class="!px-4 !text-[14px]">Marquer comme traité</x-button>@endif
                <x-button type="button" variant="secondary" wire:click="close" class="!px-4 !text-[14px]">Fermer</x-button>
                <x-button type="button" variant="secondary" wire:click="delete('{{ $opened->id }}')" wire:confirm="Supprimer définitivement ce message ?" class="!px-4 !text-[14px]">Supprimer</x-button>
            </div>
        </section>
    @endif

    <ol class="flex flex-col gap-2.5">
        @forelse ($items as $item)
            <li wire:key="msg-{{ $item->id }}">
                <button type="button" wire:click="open('{{ $item->id }}')" class="flex min-h-11 w-full flex-col gap-1 rounded-card border border-line bg-card p-3.5 text-left hover:border-ink">
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-[14px] {{ $item->state === 'new' ? 'font-extrabold' : 'font-semibold' }}">{{ $item->subjectLabel() }}</span>
                        <span class="shrink-0 rounded-chip bg-soft-blue px-2.5 py-1 text-[12px] font-bold text-link">{{ \App\Models\ContactMessage::STATES[$item->state] }}</span>
                    </span>
                    <span class="text-[13px] text-muted">{{ $item->name }} · {{ $date($item->created_at) }}</span>
                </button>
            </li>
        @empty
            <li class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucun message.</p>
                <p class="mt-1 text-muted">Les messages envoyés depuis la page Contact apparaissent ici.</p>
            </li>
        @endforelse
    </ol>

    @if ($items->hasPages())
        <nav aria-label="Pages des messages" class="flex items-center justify-between gap-2">
            <x-button type="button" variant="secondary" wire:click="previousPage" :disabled="$items->onFirstPage()">Précédent</x-button>
            <span class="text-[13px] font-semibold text-muted">Page {{ $items->currentPage() }} sur {{ $items->lastPage() }}</span>
            <x-button type="button" variant="secondary" wire:click="nextPage" :disabled="! $items->hasMorePages()">Suivant</x-button>
        </nav>
    @endif
</div>
