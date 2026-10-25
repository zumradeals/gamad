@props(['profile'])

<article {{ $attributes->class(['person-discovery-card min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start">
        <x-avatar :profile="$profile" :size="72" class="shrink-0" />
        <div class="min-w-0 flex-1 space-y-2">
            <div>
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="person-name text-[17px] font-extrabold">{{ $profile->user->name }}</a>
                @if ($profile->city)<p class="mt-1 flex items-center gap-1 text-[13px] text-muted"><x-icon name="pin" :size="16" /> {{ $profile->city->label() }}</p>@endif
            </div>
            @foreach ([['skills' => $profile->offeredSkills, 'label' => 'Peut apporter', 'kind' => 'offered'], ['skills' => $profile->wantedSkills, 'label' => 'Veut apprendre', 'kind' => 'wanted']] as $group)
                @if ($group['skills']->isNotEmpty())
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                        <span class="rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $group['kind'] === 'offered' ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'bg-type-learning-bg text-type-learning-fg' }}">{{ $group['label'] }}</span>
                        @foreach ($group['skills'] as $skill)
                            <span class="max-w-full rounded-chip bg-soft-blue px-2.5 py-1 text-[12px] font-semibold text-link [overflow-wrap:anywhere]">{{ $skill->name }}</span>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
        <div class="flex shrink-0 flex-row gap-2 sm:flex-col">
            <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary">Voir le profil</x-button>
        </div>
    </div>
</article>
