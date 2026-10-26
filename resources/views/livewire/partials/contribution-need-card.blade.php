{{-- Carte d'un besoin (C1) : titre, résumé, forme d'aide, bénéficiaire, localité, état et échéance réels. Aucun pourcentage ni montant inventé. --}}
@php
    $access = app(\App\Services\ContributionAccess::class);
    [$beneficiaryKind, $beneficiary] = $access->beneficiary($need);
    $profile = $need->author?->profile;
    $profile?->setRelation('user', $need->author);
    $canSeeAuthor = $profile && \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('view', $profile);
    $name = match ($beneficiaryKind) {
        \App\Enums\BeneficiaryKind::Gamad => 'GAMAD',
        \App\Enums\BeneficiaryKind::Person => $canSeeAuthor ? $need->author->name : 'Un membre de GAMAD',
        default => $beneficiary?->name ?? $beneficiaryKind->label(),
    };
    $help = $need->help_kind ? \App\Enums\HelpKind::tryFrom($need->help_kind) : null;
    $title = $need->title ?: \Illuminate\Support\Str::limit($need->body, 60);
    $summary = $need->title && $need->body !== $need->title ? \Illuminate\Support\Str::limit($need->body, 110) : null;
    $state = $need->need_state;
    $stateClasses = match ($state) {
        \App\Enums\NeedState::Open => 'border-type-knowledge-fg text-type-knowledge-fg',
        \App\Enums\NeedState::InProgress => 'border-type-learning-fg text-type-learning-fg',
        default => 'border-line text-muted',
    };
@endphp
<article wire:key="{{ $prefix ?? 'besoin' }}-{{ $need->id }}" class="flex min-w-0 flex-col gap-2.5 rounded-card border border-line bg-card p-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-chip bg-type-need-bg px-2.5 py-1 text-[12px] font-bold text-type-need-fg"><x-icon name="heart" :size="14" />Besoin</span>
        @if ($help)<span class="inline-flex items-center gap-1.5 rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $help->badgeClasses() }}"><x-icon :name="$help->icon()" :size="14" />{{ $help->label() }}</span>@endif
    </div>
    <h3 class="text-[17px] font-extrabold leading-tight"><a href="{{ route('publications.show', $need) }}" class="inline-flex min-h-11 items-center break-words text-ink no-underline hover:underline">{{ $title }}</a></h3>
    @if ($summary)<p class="break-words text-[14px] leading-relaxed text-muted">{{ $summary }}</p>@endif
    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-muted">
        <span class="inline-flex items-center gap-1.5"><x-icon :name="$beneficiaryKind->icon()" :size="16" /><span class="font-semibold text-ink">{{ $beneficiaryKind->label() }}</span> · {{ $name }}</span>
        @if ($need->city)<span class="inline-flex items-center gap-1.5"><x-icon name="pin" :size="16" />{{ $need->city->label() }}</span>@endif
    </p>
    <p class="mt-auto flex flex-wrap items-center gap-2 pt-1 text-[13px]">
        <span class="rounded-button border px-2.5 py-1 font-bold {{ $stateClasses }}">{{ $state->contributionLabel() }}</span>
        @if ($need->needed_by && $state->isActive())<span class="inline-flex items-center gap-1.5 text-muted"><x-icon name="clock" :size="16" />Avant le {{ $need->needed_by->locale('fr')->isoFormat('D MMM YYYY') }}</span>@endif
        @if (($need->proposals_count ?? 0) > 0)<span class="text-muted">{{ $need->proposals_count }} {{ $need->proposals_count > 1 ? 'propositions' : 'proposition' }}</span>@endif
    </p>
</article>
