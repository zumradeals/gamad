{{-- Carte d'une contribution proposée publiquement (C1) : jamais le message, jamais de coordonnées. Une intention financière est dite non payée. --}}
@php
    $kind = $proposal->kind;
    $author = $proposal->author;
    $profile = $author?->profile;
    $profile?->setRelation('user', $author);
    $canSeeAuthor = $profile && \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('view', $profile);
    $beneficiaryKind = $proposal->beneficiaryKind();
    $place = $proposal->city?->label();
@endphp
<article wire:key="{{ $prefix ?? 'contribution' }}-{{ $proposal->id }}" class="flex min-w-0 flex-col gap-2.5 rounded-card border border-line bg-card p-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-chip bg-type-offer-bg px-2.5 py-1 text-[12px] font-bold text-type-offer-fg"><x-icon name="heart" :size="14" />Contribution</span>
        <span class="inline-flex items-center gap-1.5 rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $kind->badgeClasses() }}"><x-icon :name="$kind->icon()" :size="14" />{{ $kind->label() }}</span>
    </div>
    <h3 class="text-[17px] font-extrabold leading-tight"><a href="{{ route('contributions.show', $proposal) }}" class="inline-flex min-h-11 items-center break-words text-ink no-underline hover:underline">{{ $kind->label() }} pour {{ $proposal->beneficiaryName() }}</a></h3>
    <p class="break-words text-[14px] leading-relaxed text-muted">{{ \Illuminate\Support\Str::limit($proposal->offer, 110) }}</p>
    @if ($kind->disclaimer())<p class="text-[12px] font-semibold text-type-call-fg">{{ $kind->disclaimer() }}</p>@endif
    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-muted">
        <span class="inline-flex items-center gap-1.5"><x-icon :name="$beneficiaryKind->icon()" :size="16" /><span class="font-semibold text-ink">{{ $beneficiaryKind->label() }}</span></span>
        @if ($place)<span class="inline-flex items-center gap-1.5"><x-icon name="pin" :size="16" />{{ $place }}</span>@endif
    </p>
    <p class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-1 text-[13px] text-muted">
        <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="flex size-7 items-center justify-center rounded-full bg-type-learning-bg text-[11px] font-bold text-type-learning-fg">{{ $canSeeAuthor ? $author->initials() : '?' }}</span><span class="font-semibold text-ink">{{ $canSeeAuthor ? $author->name : 'Un membre de GAMAD' }}</span></span>
        <span class="inline-flex items-center gap-2"><span class="rounded-chip px-2 py-0.5 text-[12px] font-bold {{ $proposal->state->badgeClasses() }}">{{ $proposal->state->label() }}</span><time datetime="{{ $proposal->created_at->toIso8601String() }}">{{ $proposal->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</time></span>
    </p>
</article>
