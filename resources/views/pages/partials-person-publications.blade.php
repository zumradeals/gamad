<section id="publications" aria-labelledby="publications-title" class="min-w-0 scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
    <h2 id="publications-title" class="text-[20px] font-extrabold">Publications & échanges</h2>
    <p class="mt-1 text-[12px] text-muted">Les 20 publications personnelles les plus récentes visibles pour vous.</p>
    @forelse ($personalPosts as $post)
        <article class="mt-4 border-t border-line pt-4">
            <div class="flex items-center gap-3"><x-avatar :profile="$profile" :size="36" /><div class="min-w-0"><p class="break-words text-[13px] font-bold">{{ $user->name }}</p><p class="text-[12px] text-muted">{{ $post->displayLabel() }} · {{ $post->created_at->format('d/m/Y') }}</p></div></div>
            <p class="mt-3 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $post->body }}</p>
            <x-publication-media :publication="$post" />
            <a href="{{ route('publications.show', $post) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Voir la publication @unless ($isOwner) et {{ $post->gestureLabel() === 'Échanger' ? 'échanger' : 'répondre' }}@endunless →</a>
        </article>
    @empty
        <p class="mt-3 text-[14px] text-muted">Aucune publication personnelle à afficher pour le moment.</p>
    @endforelse
</section>
