@props(['publication', 'viewer' => null, 'managed' => []])

@php
    $author = $publication->author;
    $profile = $author->profile;
    $profile?->setRelation('user', $author);
    $canSeeAuthor = $profile && Gate::forUser($viewer)->allows('view', $profile);
    $project = $publication->project;
    $zumra = $publication->zumra;
    $emitterLabel = $project ? 'Projet' : ($zumra ? 'ZUMRA' : 'Personne');
    $emitterName = $project?->name ?? $zumra?->name ?? $author->name;
    $isOwner = $publication->isManagedBy($viewer, $managed);
    $state = $publication->need_state;
    $stateClasses = match ($state) {
        \App\Enums\NeedState::Open => 'border-type-knowledge-fg text-type-knowledge-fg',
        \App\Enums\NeedState::InProgress => 'border-type-learning-fg text-type-learning-fg',
        default => 'border-line text-muted',
    };
    $meta = collect([$publication->domain?->name, $publication->city?->label()])->filter()->implode(' · ');
@endphp

<article {{ $attributes->class('need-discovery-card flex min-w-0 flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6') }} aria-label="Besoin de {{ $emitterName }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
    <span class="inline-flex items-center gap-2 self-start rounded-chip bg-soft-blue px-3 py-1 text-[12px] font-bold"><x-icon :name="$project ? 'project' : ($zumra ? 'community' : 'user')" :size="16" />{{ $emitterLabel }}</span>
        <span class="rounded-button border px-2.5 py-1 text-[12px] font-bold {{ $stateClasses }}">{{ $state->label() }}</span>
    </div>
    <div class="flex flex-wrap items-start gap-3">
        @if ($project)
            <span aria-hidden="true" class="flex size-12 shrink-0 items-center justify-center rounded-card bg-type-call-bg text-type-call-fg"><x-icon name="project" :size="28" /></span>
        @elseif ($zumra)
            <x-zumra-initials :zumra="$zumra" :size="48" />
        @elseif ($canSeeAuthor)
            <x-avatar :profile="$profile" :size="48" />
        @else
            <span aria-hidden="true" class="flex size-12 shrink-0 items-center justify-center rounded-full bg-type-need-bg text-[16px] font-bold text-type-need-fg">{{ $author->initials() }}</span>
        @endif
        <div class="min-w-0 flex-1 basis-36">
            @if ($project)
                <a href="{{ route('projets.show', $project) }}" class="need-emitter-link">{{ $project->name }}</a>
            @elseif ($zumra && \App\Support\ZumraModule::accessibleTo(auth()->user()))
                <a href="{{ route('zumras.show', $zumra) }}" class="need-emitter-link">{{ $zumra->name }}</a>
            @elseif ($zumra)
                <span class="need-emitter-link">{{ $zumra->name }}</span>
            @elseif ($canSeeAuthor)
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="need-emitter-link">{{ $author->name }}</a>
            @else
                <span class="block min-h-11 content-center text-[16px] font-extrabold [overflow-wrap:anywhere]">{{ $author->name }}</span>
            @endif
            @if ($project && $zumra)<p class="text-[13px] text-muted">Porté par <a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 max-w-full items-center font-semibold [overflow-wrap:anywhere]">{{ $zumra->name }}</a></p>@endif
            @if ($zumra)<p class="text-[13px] text-muted">par {{ $author->name }}</p>@endif
            @if ($meta !== '')<p class="mt-1 text-[13px] leading-relaxed text-muted [overflow-wrap:anywhere]">{{ $meta }}</p>@endif
        </div>
    </div>
    <p class="need-request whitespace-pre-line text-[16px] leading-relaxed [overflow-wrap:anywhere]">{{ $publication->body }}</p>
    @if ($publication->skills->isNotEmpty())
        <div><p class="mb-2 text-[13px] font-semibold text-muted">Capacités demandées</p>
            <ul class="flex flex-wrap gap-2" aria-label="Capacités demandées">
                @foreach ($publication->skills as $skill)
                    <li class="min-w-0 max-w-full"><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}" class="inline-flex min-h-11 max-w-full items-center rounded-chip bg-type-need-bg px-3 text-[13px] font-semibold text-type-need-fg no-underline hover:underline [overflow-wrap:anywhere]">{{ $skill->name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
        <time datetime="{{ $publication->created_at->toIso8601String() }}" class="text-[13px] text-muted">{{ $publication->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</time>
        <x-button :href="route('publications.show', $publication)" variant="secondary" class="gap-2 !px-4 !text-[14px]">{{ $isOwner ? 'Voir mon besoin' : 'Voir le besoin' }}<x-icon name="arrow-right" :size="18" /></x-button>
    </div>
</article>
