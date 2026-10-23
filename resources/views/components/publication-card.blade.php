{{--
    Carte de publication (écrans 1, 4 et 10). Props :
    - publication : avec author.profile, domain, city, skills chargés, et live_gestures_count
    - viewer      : la personne qui regarde (ou null)
    - gesture     : son geste sur cette publication (ou null)
    - link        : vrai pour ajouter un lien vers la page de la publication (Fil)
    Pas de « Commenter », de « Partager » ni de « Signaler » : ils ne font pas partie de ce lot (D-090).
--}}
@props(['publication', 'viewer' => null, 'gesture' => null, 'link' => true])

@php
    use App\Enums\GestureState;
    use App\Enums\NeedState;

    $type = $publication->type;
    $author = $publication->author;
    $profile = $author->profile;
    $isOwner = $viewer && $viewer->getKey() === $publication->author_id;
    // La règle de visibilité du profil lit son propriétaire : on lui donne celui qu'on a déjà, sans requête de plus.
    $profile?->setRelation('user', $author);
    $canSeeAuthor = $profile && Gate::forUser($viewer)->allows('view', $profile);
    $meta = collect([
        $publication->domain?->name,
        $publication->city?->label(),
        $publication->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans(['short' => true]),
    ])->filter()->implode(' · ');
    $live = (int) ($publication->live_gestures_count ?? 0);
    $state = $publication->need_state;
    $stateClasses = match ($state) {
        NeedState::InProgress => 'bg-type-call-bg text-type-call-fg',
        NeedState::Resolved => 'bg-type-knowledge-bg text-type-knowledge-fg',
        default => 'border border-line bg-ground text-muted',
    };
@endphp

<article {{ $attributes->class('flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]') }} aria-label="{{ $type->label() }} de {{ $author->name }}">
    <div class="flex items-center justify-between gap-2">
        <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $type->badgeClasses() }}">{{ $type->label() }}</span>
        @if ($state && $state !== NeedState::Open)
            <span class="rounded-chip px-2.5 py-1 text-[12px] font-extrabold {{ $stateClasses }}">{{ $state->label() }}</span>
        @endif
    </div>

    <div class="flex items-center gap-2.5">
        @if ($profile)
            <x-avatar :profile="$profile" :size="40" />
        @endif
        <div class="flex min-w-0 flex-col gap-0.5">
            @if ($canSeeAuthor)
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-bold text-ink no-underline hover:text-ink hover:underline">{{ $author->name }}</a>
            @else
                <span class="break-words text-[15px] font-bold">{{ $author->name }}</span>
            @endif
            @if ($meta !== '')
                <span class="-mt-2 text-[12px] text-muted">{{ $meta }}</span>
            @endif
        </div>
    </div>

    <p class="whitespace-pre-line break-words text-[15px] leading-normal">{{ $publication->body }}</p>

    @if ($publication->skills->isNotEmpty())
        <ul class="flex flex-wrap gap-1.5" aria-label="Capacités">
            @foreach ($publication->skills as $skill)
                <li><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}"
                       class="inline-flex min-h-11 items-center rounded-chip px-3 text-[13px] font-bold no-underline hover:underline {{ $type->badgeClasses() }}">{{ $skill->name }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($live > 0)
        <span class="text-[13px] font-semibold text-muted">{{ $type->factLabel($live) }}</span>
    @endif

    @if ($isOwner)
        @if ($link)
            <x-button :href="route('publications.show', $publication)" variant="secondary">{{ $live > 0 ? 'Voir les réponses' : 'Voir ma publication' }}</x-button>
        @endif
    @elseif ($viewer)
        @if ($gesture && $gesture->state === GestureState::Accepted)
            <span class="inline-flex min-h-11 items-center gap-1.5 text-[14px] font-extrabold text-type-knowledge-fg"><x-icon name="check" :size="16" :stroke="3" />Acceptée</span>
        @elseif ($gesture && $gesture->state === GestureState::Declined)
            <span class="inline-flex min-h-11 items-center text-[14px] font-semibold text-muted">Non retenue</span>
        @elseif ($gesture && $gesture->state === GestureState::Sent)
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <span class="inline-flex min-h-11 flex-1 items-center gap-1.5 text-[14px] font-extrabold text-type-learning-fg"><x-icon name="check" :size="16" :stroke="3" />{{ $type->isNeed() ? 'Proposition envoyée' : 'Réponse envoyée' }}</span>
                <x-button type="button" variant="secondary" wire:click="$dispatch('withdraw-gesture', { id: '{{ $publication->id }}' })">{{ $type->isNeed() ? 'Retirer ma proposition' : 'Retirer ma réponse' }}</x-button>
            </div>
        @elseif (! $publication->acceptsGestures())
            <span class="inline-flex min-h-11 items-center text-[14px] font-semibold text-muted">{{ $state === NeedState::Resolved ? 'Ce besoin est résolu.' : 'Ce besoin est fermé.' }}</span>
        @else
            <x-button type="button" wire:click="$dispatch('open-gesture', { id: '{{ $publication->id }}' })">{{ $type->gestureLabel() }}</x-button>
        @endif
    @else
        @if ($publication->acceptsGestures())
            <x-button :href="route('login')">Se connecter pour répondre</x-button>
        @endif
    @endif
</article>
