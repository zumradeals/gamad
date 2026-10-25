@props(['profile', 'directory' => false, 'conversationId' => null, 'following' => false])

<article {{ $attributes->class(['person-discovery-card relative min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 flex-wrap gap-3 sm:flex-nowrap sm:items-start">
        <x-avatar :profile="$profile" :size="$directory ? 80 : 72" class="shrink-0" />
        <div class="min-w-0 flex-1 space-y-2">
            <div>
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="person-name text-[17px] font-extrabold">{{ $profile->user->name }}</a>
                @if ($profile->headline)<p class="text-[13px] font-medium text-muted [overflow-wrap:anywhere]">{{ $profile->headline }}</p>@endif
                @if ($profile->city)<p class="mt-1 flex items-center gap-1 text-[13px] text-muted"><x-icon name="pin" :size="16" /> {{ $profile->city->label() }}</p>@endif
            </div>
            @foreach ([['skills' => $profile->offeredSkills, 'label' => 'PEUT APPORTER', 'kind' => 'offered'], ['skills' => $profile->wantedSkills, 'label' => 'VEUT APPRENDRE', 'kind' => 'wanted']] as $group)
                @if ($group['skills']->isNotEmpty())
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                        <span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $group['kind'] === 'offered' ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'bg-type-learning-bg text-type-learning-fg' }}">{{ $group['label'] }}</span>
                        @foreach ($group['skills']->take($directory ? 3 : 10) as $skill)
                            <span class="max-w-full rounded-chip bg-soft-blue px-2.5 py-1 text-[12px] font-semibold text-link [overflow-wrap:anywhere]">{{ $skill->name }}</span>
                        @endforeach
                        @if ($directory && $group['skills']->count() > 3)<span class="text-[11px] text-muted">+{{ $group['skills']->count() - 3 }}</span>@endif
                    </div>
                @endif
            @endforeach
        </div>
        <div class="flex w-full shrink-0 flex-row gap-2 sm:w-auto sm:flex-col sm:pr-6">
            <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary" class="!px-3 !text-[12px]">Voir le profil</x-button>
            @if ($directory && $conversationId && auth()->user()?->isActive())
                <x-button :href="route('conversations.show', $conversationId)" variant="accent" class="!px-3 !text-[12px]">Contacter</x-button>
            @endif
            @if ($directory && auth()->user()?->isActive() && auth()->id() !== $profile->user_id)
                <details class="absolute right-1 top-1">
                    <summary class="flex size-11 cursor-pointer items-center justify-center text-[12px] font-semibold" aria-label="Actions pour {{ $profile->user->name }}">•••</summary>
                    <div class="absolute right-0 z-10 w-40 rounded-button border border-line bg-card p-1 shadow-lg">
                        <button type="button" wire:click="follow('{{ $profile->id }}', {{ $following ? 'false' : 'true' }})" wire:loading.attr="disabled" class="min-h-11 w-full px-2 text-left text-[12px] font-semibold">{{ $following ? 'Ne plus suivre' : 'Suivre' }}</button>
                    </div>
                </details>
            @endif
        </div>
    </div>
</article>
