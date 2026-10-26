<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Espace" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Notifications</h1>
        @if ($unread > 0)
            <button type="button" wire:click="markAllRead" wire:loading.attr="disabled" wire:target="markAllRead"
                    class="inline-flex min-h-11 items-center px-3 text-[14px] font-bold text-link underline">Tout marquer lu</button>
        @endif
    </div>

    <div wire:poll.30s class="flex flex-col gap-2.5">
        @forelse ($notifications as $notification)
            @php
                $actor = $notification->actor;
                $about = $notification->kind->isAboutProject()
                    ? $notification->project?->name
                    : ($notification->kind->isAboutZumra()
                    ? $notification->zumra?->name
                    : ($notification->opportunity?->title ?? $notification->company?->name ?? ($notification->publication ? \Illuminate\Support\Str::limit($notification->publication->body, 40) : null)));
                $isUnread = $notification->read_at === null;
            @endphp
            <a wire:key="notification-{{ $notification->id }}" href="{{ route('notifications.open', $notification) }}"
               @class(['flex min-h-14 items-center gap-3 rounded-card border p-3.5 text-ink no-underline hover:text-ink', 'border-line bg-card' => ! $isUnread, 'border-chip-line bg-soft-blue' => $isUnread])>
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-type-learning-bg text-[15px] font-extrabold text-type-learning-fg" aria-hidden="true">{{ $notification->kind->showsActor() ? ($actor?->initials() ?: '?') : (in_array($notification->kind, [\App\Enums\NotificationKind::MatchFound, \App\Enums\NotificationKind::TrustUpdated, \App\Enums\NotificationKind::TrustReview], true) || str_starts_with($notification->kind->value, 'company_') ? 'GA' : 'ZU') }}</span>
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="break-words text-[14px] leading-snug">
                        @if ($notification->kind->showsActor())
                            <strong class="font-extrabold">{{ $actor?->name ?? 'Personne supprimée' }}</strong>
                        @endif
                        {{ $notification->kind->sentence($about) }}
                        @if ($isUnread)<span class="sr-only"> (non lue)</span>@endif
                    </span>
                    <span class="text-[12px] text-muted">{{ $notification->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</span>
                </span>
                @if ($isUnread)
                    <span class="size-2.5 shrink-0 rounded-full bg-link" aria-hidden="true"></span>
                @endif
            </a>
        @empty
            <section class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Rien de nouveau.</p>
                <p class="mt-1 text-muted">Vous serez prévenu ici quand quelqu’un répondra à une publication, vous écrira ou vous remerciera.</p>
            </section>
        @endforelse
    </div>

    <p class="text-[12px] leading-normal text-muted">Les notifications restent dans l’application. Les alertes par e-mail ou SMS ne font pas partie de cette étape.</p>
</div>
