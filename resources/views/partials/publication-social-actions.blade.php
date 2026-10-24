{{-- D-193 : mémorisation privée et partage du seul lien, sans changement des permissions. --}}

@if (! $publication->zumra_id && ! $publication->project_id && ! $publication->isHidden())
    <div class="flex flex-wrap items-start gap-3">
        @if ($viewer?->isActive())
            <form method="POST" action="{{ route('publications.bookmark', $publication) }}">
                @csrf
                <input type="hidden" name="saved" value="{{ $saved ? '0' : '1' }}">
                <button type="submit" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link">
                    <x-icon name="book" :size="17" />{{ $saved ? 'Enregistré · Retirer' : 'Enregistrer' }}
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Se connecter pour enregistrer</a>
        @endif

        <div x-data="{ opened: false, copied: false }" class="min-w-0 flex-1">
            <button type="button" x-on:click="opened = !opened" x-bind:aria-expanded="opened.toString()" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link">
                <x-icon name="external" :size="17" />Partager
            </button>
            <div x-show="opened" x-cloak class="mt-1 flex flex-col gap-2 rounded-button border border-line bg-ground p-3">
                <label class="text-[12px] font-bold" for="share-{{ $publication->id }}">Lien de cette publication</label>
                <input id="share-{{ $publication->id }}" x-ref="link" type="text" readonly onclick="this.select()" value="{{ route('publications.show', $publication) }}" class="min-h-11 w-full min-w-0 rounded-button border border-chip-line bg-card px-3 text-[12px]">
                <button type="button" class="min-h-11 self-start rounded-button border border-ink px-3 text-[12px] font-bold"
                    x-on:click="if (navigator.share) { navigator.share({ url: $refs.link.value }).catch(() => {}) } else if (navigator.clipboard) { navigator.clipboard.writeText($refs.link.value).then(() => copied = true).catch(() => $refs.link.select()) } else { $refs.link.select() }">Copier ou partager le lien</button>
                <span x-show="copied" x-cloak role="status" class="text-[12px] font-semibold text-type-knowledge-fg">Lien copié.</span>
                <p class="text-[12px] text-muted">{{ $publication->isPublic() ? 'Cette publication est publique. Le lien ne change pas sa visibilité.' : 'Accès réservé aux inscrits autorisés : ce lien ne donne aucun droit supplémentaire.' }}</p>
            </div>
        </div>
    </div>
@endif
