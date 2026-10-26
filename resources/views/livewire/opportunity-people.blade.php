<div class="flex min-w-0 flex-col gap-4">
    <a href="{{ route('entreprises.opportunities', $opportunity->company) }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink"><x-icon name="back" :size="16" />Nos opportunités</a>
    <header class="rounded-card border border-line bg-card p-5">
        <h1 class="text-[24px] font-extrabold">Découvrir des personnes</h1>
        <p class="mt-1 break-words text-[14px] text-muted">Pour l’opportunité « {{ $opportunity->title }} »</p>
        <p class="mt-3 text-[13px] leading-relaxed text-muted">Ces personnes ont choisi d’être découvertes par les entreprises vérifiées et proposent une capacité que vous recherchez. Elles ne sont pas prévenues de cette recherche : vous ne voyez que ce que leur profil rend visible.</p>
    </header>

    @if ($state === 'closed')
        <p role="status" class="rounded-button bg-type-call-bg px-4 py-3 text-[14px] font-semibold text-type-call-fg">Cette opportunité n’est plus ouverte : la découverte de personnes n’est plus disponible.</p>
    @elseif ($state === 'limit')
        <p role="status" class="rounded-button bg-type-call-bg px-4 py-3 text-[14px] font-semibold text-type-call-fg">Vous avez consulté {{ $pages }} pages aujourd’hui : c’est la limite quotidienne. Vous pourrez reprendre demain.</p>
    @else
        <section class="space-y-3" aria-label="Personnes dont une capacité correspond">
            @forelse ($people as $profile)
                <article wire:key="p-{{ $profile->id }}" class="rounded-card border border-line bg-card p-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <x-avatar :profile="$profile" :size="52" />
                        <div class="min-w-0 flex-1">
                            <h2 class="break-words text-[17px] font-extrabold">{{ $profile->user->name }}</h2>
                            @if ($profile->headline)<p class="break-words text-[14px] text-muted">{{ $profile->headline }}</p>@endif
                            @if ($profile->city?->active)<p class="mt-0.5 text-[13px] text-muted">{{ $profile->city->label() }}</p>@endif
                        </div>
                    </div>
                    <ul aria-label="Capacités proposées en commun" class="mt-3 flex flex-wrap gap-2">@foreach ($profile->offeredSkills->whereIn('id', $requiredIds) as $skill)<li class="max-w-full break-words rounded-chip bg-soft-blue px-3 py-1.5 text-[12px] font-semibold text-link">{{ $skill->name }}</li>@endforeach</ul>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-[13px] text-muted">@foreach ($profile->reasons as $reason)<li class="[overflow-wrap:anywhere]">{{ $reason }}</li>@endforeach</ul>
                    <div class="mt-3"><x-button :href="route('personnes.show', $profile->public_id)" variant="secondary">Voir le profil</x-button></div>
                </article>
            @empty
                <div class="rounded-card border border-line bg-card p-6"><h2 class="text-[18px] font-bold">Aucune personne visible pour le moment.</h2><p class="mt-2 text-[14px] text-muted">Revenez plus tard : seules les personnes qui l’ont accepté et dont le profil vous est visible apparaissent ici.</p></div>
            @endforelse
        </section>
        @if ($index > 0 || $next)
            <nav aria-label="Pages" class="flex flex-wrap items-center gap-2">
                @if ($index > 0)<x-button type="button" variant="secondary" wire:click="previousPage" wire:loading.attr="disabled">Page précédente</x-button>@endif
                @if ($next)<x-button type="button" variant="secondary" wire:click="nextPage" wire:loading.attr="disabled">Page suivante</x-button>@endif
            </nav>
        @endif
    @endif
</div>
