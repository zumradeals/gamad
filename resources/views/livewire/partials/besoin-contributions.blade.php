{{-- Besoin d'entraide (C1) : forme d'aide, bénéficiaire, état, échéance ; proposer une contribution ; propositions reçues pour son représentant. --}}
@php
    $help = $need->help_kind ? \App\Enums\HelpKind::tryFrom($need->help_kind) : null;
    [$beneficiaryKind, $beneficiary] = app(\App\Services\ContributionAccess::class)->beneficiary($need);
    $beneficiaryName = match ($beneficiaryKind) {
        \App\Enums\BeneficiaryKind::Gamad => 'GAMAD',
        \App\Enums\BeneficiaryKind::Person => $need->author->name,
        default => $beneficiary?->name,
    };
    $box = 'flex flex-col gap-2.5 rounded-card border border-line bg-card p-4';
@endphp
<section class="{{ $box }}" aria-labelledby="entraide-besoin">
    <h2 id="entraide-besoin" class="text-[17px] font-extrabold">Entraide</h2>
    <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[14px]">
        @if ($need->title)<dt class="font-semibold text-muted">Besoin</dt><dd class="m-0 break-words font-extrabold">{{ $need->title }}</dd>@endif
        @if ($help)<dt class="font-semibold text-muted">Type d’aide</dt><dd class="m-0">{{ $help->label() }}</dd>@endif
        <dt class="font-semibold text-muted">Pour</dt><dd class="m-0">{{ $beneficiaryKind->label() }} · {{ $beneficiaryName }}</dd>
        <dt class="font-semibold text-muted">État</dt><dd class="m-0 font-bold">{{ $need->need_state?->contributionLabel() }}</dd>
        @if ($need->needed_by)<dt class="font-semibold text-muted">À obtenir avant le</dt><dd class="m-0">{{ $need->needed_by->locale('fr')->isoFormat('D MMMM YYYY') }}</dd>@endif
        @if ($c1['count'] > 0)<dt class="font-semibold text-muted">Propositions</dt><dd class="m-0">{{ $c1['count'] }}</dd>@endif
    </dl>
    @if ($help?->disclaimer())<p class="rounded-button bg-type-call-bg px-3 py-2 text-[13px] font-semibold text-type-call-fg">Soutien financier : intention seulement. Rien n’est payé ni encaissé dans GAMAD.</p>@endif

    @if ($c1['canContribute'])
        <x-button :href="route('contributions.propose', ['besoin' => $need->id])" variant="accent" class="gap-2"><x-icon name="heart" :size="18" />Proposer une contribution</x-button>
        <p class="text-[12px] text-muted">Votre proposition est volontaire : elle ne devient une contribution qu’une fois acceptée, puis confirmée comme réalisée.</p>
    @elseif ($c1['mine'])
        <a href="{{ route('contributions.show', $c1['mine']) }}" class="inline-flex min-h-11 items-center self-start text-[14px] font-bold">Votre proposition : {{ $c1['mine']->state->label() }}</a>
    @elseif (! $isAuthor && $c1['reason'])
        <p class="text-[13px] font-semibold text-muted">{{ $c1['reason'] }}</p>
    @endif
</section>

@if ($isAuthor)
    <section class="{{ $box }}" aria-labelledby="propositions-contribution">
        <h2 id="propositions-contribution" class="text-[17px] font-extrabold">Propositions de contribution</h2>
        @forelse ($c1['proposals'] as $proposal)
            <a wire:key="prop-{{ $proposal->id }}" href="{{ route('contributions.show', $proposal) }}" class="flex flex-col gap-1 border-t border-line-soft pt-2.5 text-ink no-underline first:border-0 first:pt-0 hover:text-ink">
                <span class="flex flex-wrap items-center gap-2"><span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->kind->badgeClasses() }}">{{ $proposal->kind->label() }}</span><span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $proposal->state->badgeClasses() }}">{{ $proposal->state->label() }}</span></span>
                <span class="break-words text-[15px] font-extrabold">{{ $proposal->author?->name ?? 'Personne supprimée' }}</span>
                <span class="break-words text-[13px] text-muted">{{ \Illuminate\Support\Str::limit($proposal->offer, 110) }}</span>
            </a>
        @empty
            <p class="text-[14px] text-muted">Aucune proposition pour l’instant.</p>
        @endforelse
    </section>
@endif
