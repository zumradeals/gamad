<div>
@if ($gratitudes->isNotEmpty() || $isOwner)
    <section aria-labelledby="remerciements" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <div class="flex items-center gap-2">
            <span class="flex size-8 items-center justify-center rounded-[10px] bg-type-call-bg text-type-call-fg"><x-icon name="heart" :size="18" /></span>
            <h2 id="remerciements" class="text-[17px] font-extrabold">Remerciements reçus</h2>
        </div>

        @forelse ($gratitudes as $gratitude)
            @php
                $thanker = $gratitude->author;
                $thankerProfile = $thanker->profile;
                $thankerProfile?->setRelation('user', $thanker);
                $canSeeThanker = $thankerProfile && Gate::forUser($viewer)->allows('view', $thankerProfile);
                $publication = $gratitude->publication;
                $canSeePublication = Gate::forUser($viewer)->allows('view', $publication);
            @endphp
            <figure wire:key="gratitude-{{ $gratitude->id }}" @class(['flex flex-col gap-1.5 border-t border-line-soft pt-3 first:border-0 first:pt-0', 'opacity-70' => $gratitude->isHidden()])>
                <blockquote class="whitespace-pre-line break-words text-[15px] leading-normal">« {{ $gratitude->body }} »</blockquote>
                <figcaption class="text-[13px] text-muted">
                    —
                    @if ($canSeeThanker)
                        <a href="{{ route('personnes.show', $thankerProfile->public_id) }}" class="font-semibold">{{ $thanker->name }}</a>
                    @else
                        <span class="font-semibold">{{ $thanker->name }}</span>
                    @endif
                    @if ($canSeePublication)
                        · pour le besoin « <a href="{{ route('publications.show', $publication) }}">{{ \Illuminate\Support\Str::limit($publication->body, 50) }}</a> »
                    @endif
                </figcaption>
                @if ($isOwner)
                    @if ($gratitude->isHidden())
                        <p class="text-[12px] font-semibold text-muted">Masqué : les autres personnes ne le voient pas.</p>
                        <x-button type="button" variant="secondary" wire:click="show('{{ $gratitude->id }}')" wire:loading.attr="disabled" wire:target="show, hide" class="self-start !text-[14px]">Afficher à nouveau</x-button>
                    @else
                        <x-button type="button" variant="secondary" wire:click="hide('{{ $gratitude->id }}')" wire:loading.attr="disabled" wire:target="show, hide" class="self-start !text-[14px]">Masquer de mon profil</x-button>
                    @endif
                @endif
            </figure>
        @empty
            <p class="text-[14px] text-muted">Aucun remerciement pour l’instant. Ils apparaîtront ici quand une personne que vous avez aidée vous remerciera.</p>
        @endforelse
    </section>
@endif
</div>
