@php
    $tabs = ['toutes' => 'Toutes', 'published' => 'Publiées', 'draft' => 'Brouillons', 'closed' => 'Clôturées', 'archived' => 'Archivées'];
@endphp
<div class="flex min-w-0 flex-col gap-4">
    <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink"><x-icon name="back" :size="18" /> Retour à la page de l’entreprise</a>
    <header class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-card p-5">
        <div class="min-w-0"><h1 class="text-[24px] font-extrabold">Nos opportunités</h1><p class="text-[14px] text-muted">{{ $company->name }} — emplois, stages, missions et collaborations.</p></div>
        <x-button :href="route('opportunites.create', $company)" variant="accent"><x-icon name="plus" :size="18" class="mr-2" />Publier une opportunité</x-button>
    </header>
    @unless ($verified)<p role="status" class="rounded-button bg-type-call-bg px-4 py-3 text-[14px] font-semibold text-type-call-fg">Votre entreprise n’est pas encore vérifiée par GAMAD : vous pouvez préparer des brouillons, mais pas publier.</p>@endunless
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @if ($message)<x-status>{{ $message }}</x-status>@endif

    <div role="tablist" aria-label="État des opportunités" class="-mx-1 flex gap-2 overflow-x-auto px-1 py-1">
        @foreach ($tabs as $slug => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $slug ? 'true' : 'false' }}" wire:click="setTab('{{ $slug }}')" class="min-h-11 shrink-0 rounded-full px-4 text-[13px] font-bold {{ $tab === $slug ? 'bg-link text-white' : 'border border-line bg-card' }}">{{ $label }} <span class="ml-1 opacity-80">{{ $slug === 'toutes' ? $total : ($counts[$slug] ?? 0) }}</span></button>
        @endforeach
    </div>

    <section class="space-y-3" aria-label="Opportunités de l’entreprise">
        @forelse ($items as $item)
            <article wire:key="mine-{{ $item->id }}" class="rounded-card border border-line bg-card p-4">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0"><h2 class="break-words text-[17px] font-extrabold"><a href="{{ route('opportunites.show', $item) }}" class="text-ink no-underline hover:underline">{{ $item->title }}</a></h2>
                        <p class="text-[13px] text-muted">{{ $item->kind->label() }} · {{ $item->statusLabel() }}@if ($item->deadline) · avant le {{ $item->deadline->locale('fr')->translatedFormat('j M Y') }}@endif · Réf. {{ $item->reference }}</p></div>
                    <span class="rounded-chip px-3 py-1 text-[12px] font-bold {{ $item->kind->badgeClasses() }}">{{ $item->kind->label() }}</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-button :href="route('opportunites.edit', $item)" variant="secondary">{{ $item->status === 'draft' ? 'Continuer' : 'Modifier' }}</x-button>
                    @if ($item->status !== 'draft')<x-button :href="route('opportunites.responses', $item)" variant="secondary">Réponses ({{ $item->responses_count }})</x-button>@endif
                    @if ($item->status === 'published')<button type="button" wire:click="close('{{ $item->id }}')" wire:confirm="Clôturer cette opportunité ? Elle n’acceptera plus de réponses." class="min-h-11 px-3 text-[13px] font-bold text-link">Clôturer</button>@endif
                    @if ($item->status === 'closed')<button type="button" wire:click="reopen('{{ $item->id }}')" class="min-h-11 px-3 text-[13px] font-bold text-link">Rouvrir</button>@endif
                    @if (in_array($item->status, ['published', 'closed'], true))<button type="button" wire:click="archive('{{ $item->id }}')" wire:confirm="Archiver cette opportunité ? Elle ne sera plus visible du public." class="min-h-11 px-3 text-[13px] font-bold text-muted">Archiver</button>@endif
                    @if ($item->status === 'draft')<button type="button" wire:click="deleteDraft('{{ $item->id }}')" wire:confirm="Supprimer ce brouillon ?" class="min-h-11 px-3 text-[13px] font-bold text-danger">Supprimer</button>@endif
                </div>
            </article>
        @empty
            <div class="rounded-card border border-line bg-card p-6"><h2 class="text-[18px] font-bold">{{ $total === 0 ? 'Vous n’avez encore publié aucune opportunité.' : 'Aucune opportunité dans cet état.' }}</h2>
                <p class="mt-2 text-[14px] text-muted">Publiez un emploi, un stage, une mission ou une collaboration : elle apparaîtra sur votre page et dans le catalogue.</p></div>
        @endforelse
    </section>
</div>
