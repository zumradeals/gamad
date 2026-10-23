@props(['profile'])

<article {{ $attributes->class(['person-discovery-card flex min-w-0 flex-col rounded-card border border-line bg-card']) }}>
    <div aria-hidden="true" class="person-discovery-cover"></div>
    <div class="relative flex min-w-0 flex-1 flex-col gap-5 px-5 pb-5">
        <div class="person-discovery-identity">
            <x-avatar :profile="$profile" :size="88" class="border-4 border-white" />
            <div class="min-w-0 pt-6">
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="person-name">{{ $profile->user->name }}</a>
                @if ($profile->city)<p class="flex items-start gap-1 text-[13px] text-muted"><x-icon name="pin" :size="16" class="mt-0.5 shrink-0" /><span class="min-w-0 [overflow-wrap:anywhere]">{{ $profile->city->label() }}</span></p>@endif
            </div>
        </div>
        @foreach ([['skills' => $profile->offeredSkills, 'label' => 'PEUT APPORTER', 'kind' => 'offered'], ['skills' => $profile->wantedSkills, 'label' => 'VEUT APPRENDRE', 'kind' => 'wanted']] as $group)
            @if ($group['skills']->isNotEmpty())
                <div class="min-w-0">
                    <h3 class="mb-2 text-[11px] font-extrabold tracking-[0.8px] {{ $group['kind'] === 'offered' ? 'text-type-knowledge-fg' : 'text-type-learning-fg' }}">{{ $group['label'] }}</h3>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($group['skills'] as $skill)<li class="max-w-full rounded-chip px-3 py-1.5 text-[13px] font-bold [overflow-wrap:anywhere] {{ $group['kind'] === 'offered' ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'bg-type-learning-bg text-type-learning-fg' }}">{{ $skill->name }}</li>@endforeach
                    </ul>
                </div>
            @endif
        @endforeach
        <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary" class="mt-auto gap-2">Voir le profil<x-icon name="arrow-right" :size="18" /></x-button>
    </div>
</article>
