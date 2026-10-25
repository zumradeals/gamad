<section id="publications" aria-labelledby="publications-title" class="min-w-0 scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6" x-data="{ all: false }">
    <div class="flex items-start gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="document" :size="26" /></span>
        <div class="min-w-0 flex-1"><h2 id="publications-title" class="text-[20px] font-extrabold">Mes publications récentes</h2><p class="mt-1 text-[12px] text-muted">Les publications personnelles visibles pour vous, de la plus récente à la plus ancienne.</p></div>
    </div>
    @forelse ($personalPosts as $post)
        <article @if ($loop->index >= 3) x-show="all" x-cloak @endif class="mt-4 border-t border-line-soft pt-4">
            <div class="flex items-start gap-3">
                <x-avatar :profile="$profile" :size="40" />
                <div class="min-w-0 flex-1"><p class="break-words text-[14px] font-bold">{{ $user->name }}</p><p class="text-[12px] text-muted">{{ $post->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans(['short' => true]) }}</p></div>
                <span class="shrink-0 rounded-chip px-3 py-1 text-[12px] font-bold {{ $post->type->badgeClasses() }}">{{ $post->displayLabel() }}</span>
            </div>
            <p class="mt-3 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ \Illuminate\Support\Str::limit($post->body, 260) }}</p>
            <div class="[&_img]:max-h-48 [&_img]:rounded-button [&_video]:max-h-48"><x-publication-media :publication="$post" /></div>
            @if ($post->skills->isNotEmpty())<ul aria-label="Capacités" class="mt-2 flex flex-wrap gap-1.5">@foreach ($post->skills->take(4) as $skill)<li class="max-w-full break-words rounded-chip bg-soft-blue px-2.5 py-1 text-[11px] font-semibold text-link">{{ $skill->name }}</li>@endforeach</ul>@endif
            <a href="{{ route('publications.show', $post) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Voir la publication @unless ($isOwner) et {{ $post->gestureLabel() === 'Échanger' ? 'échanger' : 'répondre' }}@endunless →</a>
        </article>
    @empty
        <p class="mt-4 text-[14px] text-muted">Aucune publication personnelle à afficher pour le moment.</p>
    @endforelse
    @if ($personalPosts->count() > 3)<button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all.toString()" class="mt-2 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link"><span x-text="all ? 'Réduire' : 'Voir toutes les publications ({{ $personalPosts->count() }})'">Voir toutes les publications ({{ $personalPosts->count() }})</span></button>@endif
</section>
