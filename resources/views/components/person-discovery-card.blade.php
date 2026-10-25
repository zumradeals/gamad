@props(['profile', 'followed' => false, 'contact' => false, 'conversation' => null, 'self' => false, 'canAct' => false])

@php
    $presentation = $profile->headline ?: \Illuminate\Support\Str::limit((string) $profile->bio, 120);
    $contactUrl = $conversation ? route('conversations.show', $conversation) : route('personnes.show', $profile->public_id).'#contact';
@endphp
<article {{ $attributes->class(['person-discovery-card min-w-0 rounded-card border border-line bg-card p-4']) }}>
    <div class="flex min-w-0 gap-4">
        <a href="{{ route('personnes.show', $profile->public_id) }}" class="shrink-0" aria-label="Voir le profil de {{ $profile->user->name }}"><x-avatar :profile="$profile" :size="72" /></a>
        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                <a href="{{ route('personnes.show', $profile->public_id) }}" class="person-name break-words text-[17px] font-extrabold">{{ $profile->user->name }}</a>
                @if ($self)<span class="rounded-chip bg-ground px-2 py-0.5 text-[12px] font-bold">Vous</span>@endif
                @if ($followed)<span class="rounded-chip bg-soft-blue px-2 py-0.5 text-[12px] font-bold text-link">Suivie</span>@endif
                @if ($contact)<span class="rounded-chip bg-type-knowledge-bg px-2 py-0.5 text-[12px] font-bold text-type-knowledge-fg">Contact</span>@endif
            </div>
            @if ($presentation !== '')<p class="break-words text-[14px] leading-snug">{{ $presentation }}</p>@endif
            @if ($profile->city)<p class="flex items-center gap-1 text-[13px] text-muted"><x-icon name="pin" :size="16" /> {{ $profile->city->label() }}</p>@endif
            @foreach ([['skills' => $profile->offeredSkills, 'label' => 'Peut apporter', 'kind' => 'offered'], ['skills' => $profile->wantedSkills, 'label' => 'Veut apprendre', 'kind' => 'wanted']] as $group)
                @if ($group['skills']->isNotEmpty())
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                        <span class="text-[12px] font-bold {{ $group['kind'] === 'offered' ? 'text-type-knowledge-fg' : 'text-type-learning-fg' }}">{{ $group['label'] }}</span>
                        @foreach ($group['skills']->take(3) as $skill)
                            <span class="max-w-full rounded-chip px-2.5 py-1 text-[12px] font-semibold [overflow-wrap:anywhere] {{ $group['kind'] === 'offered' ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'bg-type-learning-bg text-type-learning-fg' }}">{{ $skill->name }}</span>
                        @endforeach
                        @if ($group['skills']->count() > 3)<span class="text-[12px] font-semibold text-muted">+{{ $group['skills']->count() - 3 }}</span>@endif
                    </div>
                @endif
            @endforeach
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary">Voir le profil</x-button>
                @unless ($self)<x-button :href="$contactUrl" variant="accent">Contacter</x-button>@endunless
                @if ($canAct && ! $self)
                    <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Autres actions pour {{ $profile->user->name }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-button border border-line text-[18px] font-extrabold">⋯</button>
                        <div x-show="open" x-cloak role="menu" class="absolute right-0 z-20 mt-1 w-56 rounded-card border border-line bg-card p-1 shadow-lg">
                            <button type="button" role="menuitem" wire:click="follow('{{ $profile->id }}', {{ $followed ? 'false' : 'true' }})" x-on:click="open = false" class="flex min-h-11 w-full items-center px-3 text-left text-[14px] font-semibold">{{ $followed ? 'Ne plus suivre' : 'Suivre cette personne' }}</button>
                            <a role="menuitem" href="{{ route('personnes.signaler', $profile->public_id) }}" class="flex min-h-11 items-center px-3 text-[14px] font-semibold">Signaler ce profil</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</article>
