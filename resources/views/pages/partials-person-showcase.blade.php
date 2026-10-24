@if ($profile->introduction)
<section class="rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">Ce qui me fait avancer</h2><p class="mt-3 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $profile->introduction }}</p></section>
@endif
@if (count($profile->featured_publications ?? []))
<section class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">À la une</h2>
@foreach ($profile->featured_publications as $id)
    @if ($post = $visibleReferences->get($id))<article class="block min-h-11 rounded-button border border-line p-4 text-ink no-underline"><span class="text-[12px] font-bold text-muted">{{ $post->displayLabel() }}</span><p class="mt-2 whitespace-pre-line break-words text-[15px]">{{ $post->body }}</p><x-publication-media :publication="$post" /><a href="{{ route('publications.show', $post) }}" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-bold text-link">Découvrir la publication →</a></article>@endif
@endforeach
</section>
@endif
@if (count($profile->skill_details ?? []))
<section class="flex flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6"><h2 class="text-[20px] font-extrabold">Mon savoir-faire en pratique</h2>
@foreach ($profile->skill_details as $detail)
    @if ($profile->offeredSkills->contains('name', $detail['skill']))
    <article class="border-l-2 border-accent pl-4"><h3 class="text-[16px] font-extrabold">{{ $detail['skill'] }}</h3><p class="mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $detail['description'] }}</p>
    @if ($post = $visibleReferences->get($detail['publication_id'] ?? ''))<a class="inline-flex min-h-11 items-center text-[13px] font-bold" href="{{ route('publications.show', $post) }}">Voir un exemple concret →</a>@endif</article>
    @endif
@endforeach
</section>
@endif
@if (count($profile->experiences ?? []))
<section class="flex flex-col gap-4 rounded-card border border-line bg-card p-5 md:p-6"><div><h2 class="text-[20px] font-extrabold">Mon parcours</h2><p class="text-[12px] text-muted">Expériences déclarées par {{ $user->name }}.</p></div>
@foreach ($profile->experiences as $experience)
    <article class="border-l-2 border-line pl-4"><span class="text-[12px] font-bold text-muted">{{ ['personal' => 'Personnelle', 'professional' => 'Professionnelle', 'volunteer' => 'Associative ou bénévole', 'learning' => 'Apprentissage'][$experience['kind']] }}@if ($experience['period'] ?? '') · {{ $experience['period'] }}@endif</span><h3 class="mt-1 break-words text-[16px] font-extrabold">{{ $experience['title'] }}</h3><p class="mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $experience['description'] ?? '' }}</p></article>
@endforeach
</section>
@endif
<section class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 md:p-6"><div><h2 class="text-[20px] font-extrabold">Publications et réalisations</h2><p class="text-[12px] text-muted">Les 20 publications personnelles les plus récentes visibles pour vous.</p></div>
@forelse ($personalPosts as $post)
<article class="border-t border-line py-3"><span class="text-[12px] font-bold text-muted">{{ $post->displayLabel() }} · {{ $post->created_at->format('d/m/Y') }}</span><p class="mt-2 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $post->body }}</p><x-publication-media :publication="$post" /><a href="{{ route('publications.show', $post) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Voir la publication @unless ($isOwner) et {{ $post->type->gestureLabel() === 'Échanger' ? 'échanger' : 'répondre' }}@endunless →</a></article>
@empty <p class="text-[14px] text-muted">Aucune publication personnelle à afficher pour le moment.</p>@endforelse
</section>
