<div class="flex flex-col gap-4">
    <header class="flex items-center gap-3 rounded-card border border-line bg-card p-4">
        <a href="{{ route('espace') }}" aria-label="Retour à Mon espace" class="inline-flex size-11 items-center justify-center text-ink"><x-icon name="back" /></a>
        <div><h1 class="text-[20px] font-extrabold">Mes enregistrements</h1><p class="text-[12px] text-muted">Vos publications utiles, visibles uniquement par vous.</p></div>
    </header>
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @forelse ($bookmarks as $bookmark)
        @php $post = $bookmark->publication; @endphp
        <article class="rounded-card border border-line bg-card p-4">
            <div class="flex flex-wrap items-center gap-2"><strong class="text-[13px]">{{ $post->author->name }}</strong><span class="rounded-chip px-2 py-1 text-[11px] font-bold {{ $post->type->badgeClasses() }}">{{ $post->displayLabel() }}</span></div>
            <p class="mt-2 whitespace-pre-line break-words text-[14px]">{{ $post->body }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-button :href="route('publications.show', $post)" variant="secondary">Relire</x-button>
                <form method="POST" action="{{ route('publications.bookmark', $post) }}">
                    @csrf<input type="hidden" name="saved" value="0">
                    <x-button type="submit" variant="secondary">Retirer</x-button>
                </form>
            </div>
        </article>
    @empty
        <section class="rounded-card border border-line bg-card p-5"><h2 class="text-[17px] font-extrabold">Aucun enregistrement accessible</h2><p class="mt-2 text-[14px] text-muted">Depuis l'Accueil, enregistrez un partage ou un savoir pour le retrouver ici. Les publications devenues inaccessibles ne s'affichent pas.</p><x-button :href="route('fil')" variant="secondary" class="mt-3">Retour à l’Accueil</x-button></section>
    @endforelse
    {{ $bookmarks->links() }}
</div>
