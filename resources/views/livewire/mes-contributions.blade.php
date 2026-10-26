@php
    $box = 'flex flex-col gap-2 rounded-card border border-line bg-card p-4';
    $chip = 'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-[14px] font-semibold';
    $tabs = ['contributions' => 'Mes contributions', 'besoins' => 'Mes besoins', 'recues' => 'Propositions reçues'];
@endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Mon espace</a>
        <h1 class="text-[26px] font-extrabold leading-tight">Mes contributions</h1>
        <p class="text-muted">Ce que vous proposez, ce que vous demandez et ce que vous recevez. Tout est volontaire, rien n’est public sans votre choix.</p>
    </div>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif

    <div class="flex flex-col gap-2 sm:flex-row">
        <x-button class="gap-2" :href="route('contributions.propose')" variant="accent"><x-icon name="plus" :size="18" />Proposer une contribution</x-button>
        <x-button :href="route('contributions.need')" variant="secondary">Publier un besoin</x-button>
    </div>

    <div class="-mx-1 flex gap-1 overflow-x-auto border-b border-line px-1" role="tablist" aria-label="Mes contributions">
        @foreach ($tabs as $value => $label)
            <button type="button" role="tab" wire:click="showTab('{{ $value }}')" aria-selected="{{ $tab === $value ? 'true' : 'false' }}" @class([$chip, 'border-ink font-extrabold text-ink' => $tab === $value, 'border-transparent text-muted' => $tab !== $value])>{{ $label }}@if ($value === 'recues' && $stats['to_handle'] > 0)<span class="rounded-chip bg-accent px-2 py-0.5 text-[12px] font-extrabold text-ink">{{ $stats['to_handle'] }}</span>@endif</button>
        @endforeach
    </div>

    @if ($tab === 'contributions')
        <section aria-labelledby="mes-propositions" class="flex flex-col gap-3">
            <h2 id="mes-propositions" class="sr-only">Mes propositions</h2>
            @forelse ($mine as $proposal)
                <a wire:key="mine-{{ $proposal->id }}" href="{{ route('contributions.show', $proposal) }}" class="{{ $box }} text-ink no-underline hover:text-ink">
                    <span class="flex flex-wrap items-center gap-2"><span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->kind->badgeClasses() }}">{{ $proposal->kind->label() }}</span><span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->state->badgeClasses() }}">{{ $proposal->state->label() }}</span></span>
                    <span class="break-words text-[16px] font-extrabold">Pour {{ $proposal->beneficiaryName() }}</span>
                    <span class="break-words text-[14px] text-muted">{{ \Illuminate\Support\Str::limit($proposal->offer, 120) }}</span>
                </a>
            @empty
                <div class="{{ $box }}"><p class="text-[16px] font-extrabold">Vous n’avez encore rien proposé.</p><p class="text-muted">Dites simplement ce que vous pouvez apporter : du temps, un savoir-faire, du matériel.</p><x-button :href="route('contributions.propose')" variant="accent" class="self-start">Proposer une contribution</x-button></div>
            @endforelse
        </section>
    @elseif ($tab === 'besoins')
        <section aria-labelledby="mes-besoins" class="flex flex-col gap-3">
            <h2 id="mes-besoins" class="sr-only">Mes besoins</h2>
            @forelse ($needs as $need)
                <a wire:key="need-{{ $need->id }}" href="{{ route('publications.show', $need) }}" class="{{ $box }} text-ink no-underline hover:text-ink">
                    <span class="flex flex-wrap items-center gap-2">@if ($need->help_kind)<span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ \App\Enums\HelpKind::from($need->help_kind)->badgeClasses() }}">{{ \App\Enums\HelpKind::from($need->help_kind)->label() }}</span>@endif<span class="rounded-chip bg-ground px-2.5 py-1 text-[12px] font-bold text-muted">{{ $need->need_state->contributionLabel() }}</span></span>
                    <span class="break-words text-[16px] font-extrabold">{{ $need->title ?: \Illuminate\Support\Str::limit($need->body, 60) }}</span>
                    <span class="text-[13px] text-muted">{{ $need->proposals_count }} {{ $need->proposals_count > 1 ? 'propositions' : 'proposition' }}</span>
                </a>
            @empty
                <div class="{{ $box }}"><p class="text-[16px] font-extrabold">Vous n’avez publié aucun besoin.</p><p class="text-muted">Dites de quoi vous avez besoin : la communauté pourra vous répondre.</p><x-button :href="route('contributions.need')" variant="accent" class="self-start">Publier un besoin</x-button></div>
            @endforelse
        </section>
    @else
        <section aria-labelledby="propositions-recues" class="flex flex-col gap-3">
            <h2 id="propositions-recues" class="sr-only">Propositions reçues</h2>
            @forelse ($received as $proposal)
                <a wire:key="recue-{{ $proposal->id }}" href="{{ route('contributions.show', $proposal) }}" class="{{ $box }} text-ink no-underline hover:text-ink">
                    <span class="flex flex-wrap items-center gap-2"><span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->kind->badgeClasses() }}">{{ $proposal->kind->label() }}</span><span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->state->badgeClasses() }}">{{ $proposal->state->label() }}</span></span>
                    <span class="break-words text-[16px] font-extrabold">{{ $proposal->author?->name ?? 'Personne supprimée' }} → {{ $proposal->beneficiaryName() }}</span>
                    <span class="break-words text-[14px] text-muted">{{ \Illuminate\Support\Str::limit($proposal->offer, 120) }}</span>
                </a>
            @empty
                <div class="{{ $box }}"><p class="text-[16px] font-extrabold">Aucune proposition reçue.</p><p class="text-muted">Les propositions adressées à vous, ou à ce que vous représentez, apparaîtront ici.</p></div>
            @endforelse
        </section>
    @endif
</div>
