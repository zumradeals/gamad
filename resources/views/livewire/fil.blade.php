@php
    $filters = ['tout' => 'Tout', 'besoins' => 'Besoins', 'savoirs' => 'Savoirs', 'apprendre' => 'Apprendre', 'aides' => 'Aides', 'zumra' => 'ZUMRA', 'projets' => 'Projets'];
    $shortcuts = [\App\Enums\PublicationType::Need, \App\Enums\PublicationType::Knowledge, \App\Enums\PublicationType::LearningWish];
@endphp

<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
    <h1 class="sr-only">Fil</h1>

    {{-- Colonne de gauche (ordinateur) : mon profil, vu par les autres. --}}
    <aside aria-label="Mon profil" class="hidden w-[280px] shrink-0 flex-col gap-4 lg:flex">
        <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
            <div class="flex items-center gap-2.5">
                <x-avatar :profile="$profile" :size="48" />
                <span class="flex min-w-0 flex-col">
                    <span class="break-words text-[15px] font-extrabold">{{ $viewer->name }}</span>
                    @if ($profile->city)
                        <span class="text-[12px] text-muted">{{ $profile->city->label() }}</span>
                    @endif
                </span>
            </div>

            <div class="flex flex-col gap-1.5">
                <span class="text-[12px] font-extrabold text-type-knowledge-fg">JE PEUX APPORTER</span>
                @forelse ($profile->offeredSkills as $skill)
                    <span class="self-start rounded-chip bg-type-knowledge-bg px-2.5 py-1 text-[13px] font-semibold text-type-knowledge-fg">{{ $skill->name }}</span>
                @empty
                    <a href="{{ route('espace.profil') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Ajouter ce que je peux apporter</a>
                @endforelse
            </div>

            <div class="flex flex-col gap-1.5">
                <span class="text-[12px] font-extrabold text-type-learning-fg">JE VEUX APPRENDRE</span>
                @forelse ($profile->wantedSkills as $skill)
                    <span class="self-start rounded-chip bg-type-learning-bg px-2.5 py-1 text-[13px] font-semibold text-type-learning-fg">{{ $skill->name }}</span>
                @empty
                    <a href="{{ route('espace.profil') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Ajouter ce que je veux apprendre</a>
                @endforelse
            </div>

            <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary">Voir mon profil</x-button>
        </section>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col gap-3 lg:gap-3.5">
        <section aria-label="Publier" class="flex flex-col gap-3 rounded-card border border-line bg-card p-3.5 md:p-4">
            <div class="flex items-center gap-2.5">
                <x-avatar :profile="$profile" :size="40" />
                <a href="{{ route('agir') }}" class="flex min-h-11 flex-1 items-center rounded-chip border border-line bg-ground px-4 text-[14px] text-muted no-underline hover:text-muted">Que voulez-vous faire avancer ?</a>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($shortcuts as $shortcut)
                    <a href="{{ route('agir.publier', $shortcut->slug()) }}"
                       class="inline-flex min-h-11 items-center rounded-chip px-3 text-[13px] font-bold no-underline hover:underline {{ $shortcut->badgeClasses() }}">{{ $shortcut->label() }}</a>
                @endforeach
            </div>
        </section>

        <nav aria-label="Filtrer le Fil" class="-mx-1 flex gap-2 overflow-x-auto px-1 py-0.5">
            @foreach ($filters as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        @class(['min-h-11 shrink-0 rounded-chip px-4 text-[13px]', 'bg-ink font-bold text-white' => $filter === $key, 'border border-chip-line bg-card font-semibold text-ink' => $filter !== $key])>{{ $label }}</button>
            @endforeach
        </nav>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        <div wire:loading.delay wire:target="setFilter, more" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

        @forelse ($posts as $post)
            <x-publication-card wire:key="publication-{{ $post->id }}" :publication="$post" :viewer="$viewer" :gesture="$gestures[$post->id] ?? null" :managed="$managedZumraIds" :member-of="$memberOf" />
        @empty
            <section aria-labelledby="vide" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5">
                @if ($filter === 'tout')
                    <p class="text-[15px]"><strong id="vide" class="text-[18px] font-extrabold">Le Fil se remplira bientôt.</strong> <span class="text-muted"><a href="{{ route('espace.profil') }}" class="font-semibold">Commencez par compléter votre profil.</a></span></p>
                @else
                    <p id="vide" class="text-[18px] font-extrabold">Rien dans cette catégorie pour l’instant.</p>
                @endif
                <p class="text-muted">Soyez la première personne à publier : un besoin, un savoir à partager, ce que vous voulez apprendre.</p>
                <x-button :href="route('agir')" variant="accent">Agir</x-button>
            </section>
        @endforelse

        @if ($hasMore)
            <x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled" wire:target="more">Voir plus de publications</x-button>
        @endif
    </div>

    <livewire:gesture-sheet />
</div>
