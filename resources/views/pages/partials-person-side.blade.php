@if ($profile->wantedSkills->isNotEmpty())
    <section aria-labelledby="apprend" class="rounded-card border border-line bg-card p-5 md:p-6">
        <div class="flex items-start gap-4">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="learn" :size="26" /></span>
            <div class="min-w-0 flex-1"><h2 id="apprend" class="text-[20px] font-extrabold">Je veux apprendre</h2><p class="mt-1 text-[14px] text-muted">Je souhaite développer mes compétences dans les domaines suivants :</p></div>
        </div>
        <ul class="mt-4 flex flex-wrap gap-2">
            @foreach ($profile->wantedSkills as $skill)<li class="max-w-full"><a href="{{ route('personnes.index', ['q' => $skill->name, 'sens' => 'wanted']) }}" class="inline-flex min-h-11 items-center break-words rounded-chip bg-soft-blue px-4 py-2 text-[13px] font-semibold text-link no-underline hover:underline">{{ $skill->name }}</a></li>@endforeach
        </ul>
        <p class="mt-2 text-[12px] text-muted">Touchez une capacité pour trouver d’autres personnes qui veulent l’apprendre.</p>
    </section>
@endif
@if ($currentOffer)
    <section class="rounded-card border border-line bg-card p-5 md:p-6">
        <h2 class="text-[20px] font-extrabold">En ce moment</h2>
        <x-publication-media :publication="$currentOffer" />
        <p class="mt-3 text-[13px] font-bold text-type-offer-fg">{{ $currentOffer->displayLabel() }}</p>
        <p class="mt-2 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ Str::limit($currentOffer->body, 220) }}</p>
        <a href="{{ route('publications.show', $currentOffer) }}" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-bold">Découvrir l’offre →</a>
    </section>
@endif
