{{--
    Carte de publication (écrans 1, 4 et 10). Props :
    - publication : avec author.profile, domain, city, skills chargés, et live_gestures_count
    - viewer      : la personne qui regarde (ou null)
    - gesture     : son geste sur cette publication (ou null)
    - link        : vrai pour ajouter un lien vers la page de la publication (Fil)
    - browse      : vrai dans un Carrefour (D-161) : un seul bouton « Voir … » mène à la publication, où se trouvent le geste et « Signaler »
    Pas de « Commenter » ni de « Partager » : ils ne font pas partie de ce lot (D-090). « Signaler » s'ajoute pour qui est connecté.
--}}
@props(['publication', 'viewer' => null, 'gesture' => null, 'link' => true, 'managed' => null, 'memberOf' => null, 'supported' => null, 'compact' => false, 'browse' => false])

@php
    use App\Enums\GestureState;
    use App\Enums\NeedState;

    $type = $publication->type;
    $author = $publication->author;
    $profile = $author->profile;
    // Gérée par la personne qui regarde : la sienne, ou celle d'une ZUMRA qu'elle gère (D-116).
    $isOwner = $publication->isManagedBy($viewer, $managed);
    $zumra = $publication->zumra;
    // La règle de visibilité du profil lit son propriétaire : on lui donne celui qu'on a déjà, sans requête de plus.
    $profile?->setRelation('user', $author);
    $canSeeAuthor = $profile && Gate::forUser($viewer)->allows('view', $profile);
    $meta = collect([
        $zumra ? 'par '.$author->name : null,
        $publication->domain?->name,
        $publication->city?->label(),
        $publication->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans(['short' => true]),
    ])->filter()->implode(' · ');
    $live = (int) ($publication->live_gestures_count ?? 0);
    $supports = (int) ($publication->supports_count ?? 0);
    $state = $publication->need_state;
    $stateClasses = match ($state) {
        NeedState::InProgress => 'bg-type-call-bg text-type-call-fg',
        NeedState::Resolved => 'bg-type-knowledge-bg text-type-knowledge-fg',
        default => 'border border-line bg-ground text-muted',
    };
@endphp

<article {{ $attributes->class(['flex flex-col gap-3 border border-line bg-card p-4 md:p-[18px]', 'feed-publication feed-panel' => $compact, 'rounded-card' => ! $compact]) }} aria-label="{{ $type->label() }} de {{ $zumra?->name ?? $author->name }}">
    @unless ($compact)
        <div class="flex items-center justify-between gap-2">
            <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $type->badgeClasses() }}">{{ $type->label() }}</span>
            @if ($state && $state !== NeedState::Open)
                <span class="rounded-chip px-2.5 py-1 text-[12px] font-extrabold {{ $stateClasses }}">{{ $state->label() }}</span>
            @endif
        </div>
    @endunless

    <div class="feed-post-author flex items-center gap-2.5">
        @if ($zumra)
            <x-zumra-initials :zumra="$zumra" :size="40" />
        @elseif ($profile)
            <x-avatar :profile="$profile" :size="40" />
        @endif
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            @if ($zumra)
                <a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-bold text-ink no-underline hover:text-ink hover:underline">{{ $zumra->name }}</a>
            @elseif ($canSeeAuthor)
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-bold text-ink no-underline hover:text-ink hover:underline">{{ $author->name }}</a>
            @else
                <span class="break-words text-[15px] font-bold">{{ $author->name }}</span>
            @endif
            @if ($compact && $zumra?->isRecognized())
                <span class="inline-flex items-center gap-1 self-start text-[11px] font-semibold text-type-knowledge-fg"><x-icon name="check" :size="12" />Reconnue par GAMAD</span>
            @endif
            @if ($meta !== '')
                <span class="feed-post-meta -mt-2 text-[12px] text-muted">{{ $meta }}</span>
            @endif
        </div>
        @if ($compact && ! $browse && $viewer && ! $isOwner)
            <a href="{{ route('publications.signaler', $publication) }}" class="inline-flex min-h-11 shrink-0 items-center self-start text-[11px] font-semibold text-muted underline-offset-4 hover:text-ink hover:underline">Signaler</a>
        @endif
    </div>

    @if ($compact)
        <div class="flex items-center justify-between gap-2">
            <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $type->badgeClasses() }}">{{ $type->label() }}</span>
            @if ($state && $state !== NeedState::Open)
                <span class="rounded-chip px-2.5 py-1 text-[12px] font-extrabold {{ $stateClasses }}">{{ $state->label() }}</span>
            @endif
        </div>
    @endif

    <p class="whitespace-pre-line break-words text-[15px] leading-normal">{{ $publication->body }}</p>

    @if ($publication->skills->isNotEmpty())
        <ul class="flex flex-wrap gap-1.5" aria-label="Capacités">
            @foreach ($publication->skills as $skill)
                <li><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'offered']) }}"
                       class="inline-flex min-h-11 items-center rounded-chip px-3 text-[13px] font-bold no-underline hover:underline {{ $type->badgeClasses() }}">{{ $skill->name }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($publication->project)
        <a href="{{ route('projets.show', $publication->project) }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-bold text-link">Projet : {{ $publication->project->name }}</a>
    @endif

    @if ($live > 0)
        <span class="text-[13px] font-semibold text-muted">{{ $type->factLabel($live) }}</span>
    @endif

    @if ($type->canBeSupported() && $supports > 0)
        <span class="text-[13px] font-semibold text-muted">{{ \App\Enums\PublicationType::supportFact($supports) }}</span>
    @endif

    @if (! $compact && ! $browse && $viewer && ! $isOwner)
        <a href="{{ route('publications.signaler', $publication) }}" class="inline-flex min-h-11 items-center self-start text-[13px] font-semibold text-muted underline hover:text-ink">Signaler</a>
    @endif

    <div @class(['feed-post-actions flex flex-col items-start gap-2' => $compact, 'contents' => ! $compact])>
        @if ($browse)
            <x-button :href="route('publications.show', $publication)" variant="secondary">{{ $isOwner ? 'Voir mon besoin' : 'Voir le besoin' }}</x-button>
        @elseif ($isOwner)
            @if ($link)
                <x-button :href="route('publications.show', $publication)" variant="secondary">{{ $live > 0 ? 'Voir les réponses' : 'Voir ma publication' }}</x-button>
            @endif
        @elseif ($type->canBeSupported())
            {{-- « Je soutiens » (D-139) : un engagement compté, retirable ; pas de geste, pas de conversation. --}}
            @if ($viewer && $viewer->hasVerifiedEmail())
                @if (in_array($publication->id, $supported ?? app(\App\Services\Supports::class)->supportedBy($viewer, [$publication->id]), true))
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <span class="inline-flex min-h-11 flex-1 items-center gap-1.5 text-[14px] font-extrabold text-type-knowledge-fg"><x-icon name="check" :size="16" :stroke="3" />Vous soutenez cette avancée</span>
                        <x-button type="button" variant="secondary" wire:click="$dispatch('unsupport', { id: '{{ $publication->id }}' })">Retirer mon soutien</x-button>
                    </div>
                @else
                    <x-button type="button" :variant="$compact ? 'secondary' : 'primary'" wire:click="$dispatch('support', { id: '{{ $publication->id }}' })">Je soutiens</x-button>
                @endif
            @elseif (! $viewer)
                <x-button :href="route('login')" :variant="$compact ? 'secondary' : 'primary'">Se connecter pour soutenir</x-button>
            @endif
        @elseif ($type->isProject())
            {{-- Une avancée ou une réalisation n'a pas de geste pour l'instant : rien à répondre, sans bouton sans effet. --}}
        @elseif ($type->isCall())
            {{-- Un Appel à rejoindre n'a pas de geste : « Je veux rejoindre » est une demande d'adhésion (D-116). --}}
            @if ($viewer && in_array($zumra->id, $memberOf ?? $viewer->zumraMemberships()->pluck('zumra_id')->all(), true))
                <span class="inline-flex min-h-11 items-center gap-1.5 text-[14px] font-extrabold text-type-knowledge-fg"><x-icon name="check" :size="16" :stroke="3" />Vous êtes dans cette ZUMRA</span>
            @else
                <x-button :href="route('zumras.show', $zumra).'#rejoindre'">{{ $type->gestureLabel() }}</x-button>
            @endif
        @elseif ($viewer)
            @if ($gesture && $gesture->state === GestureState::Accepted)
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <span class="inline-flex min-h-11 flex-1 items-center gap-1.5 text-[14px] font-extrabold text-type-knowledge-fg"><x-icon name="check" :size="16" :stroke="3" />Acceptée</span>
                    @if ($gesture->conversation && Gate::forUser($viewer)->allows('view', $gesture->conversation))
                        <x-button :href="route('conversations.show', $gesture->conversation)">Ouvrir la conversation</x-button>
                    @endif
                </div>
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
                <x-button type="button" :variant="$compact ? 'secondary' : 'primary'" wire:click="$dispatch('open-gesture', { id: '{{ $publication->id }}' })">{{ $type->gestureLabel() }}</x-button>
            @endif
        @else
            @if ($publication->acceptsGestures())
                <x-button :href="route('login')" :variant="$compact ? 'secondary' : 'primary'">Se connecter pour répondre</x-button>
            @endif
        @endif
    </div>
</article>
