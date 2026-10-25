@if ($profile->domains->isNotEmpty())
    <section aria-labelledby="domaines" class="rounded-card border border-line bg-card p-5 md:p-6">
        <div class="flex items-start gap-4">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="compass" :size="26" /></span>
            <div class="min-w-0 flex-1"><h2 id="domaines" class="text-[20px] font-extrabold">Domaines qui m’intéressent</h2><p class="mt-1 text-[14px] text-muted">Je m’intéresse particulièrement aux domaines suivants :</p></div>
        </div>
        <ul class="mt-4 flex flex-wrap gap-2">@foreach ($profile->domains as $domain)<li class="max-w-full break-words rounded-chip px-4 py-2 text-[13px] font-semibold" style="background-color: #fbf3df">{{ $domain->name }}</li>@endforeach</ul>
    </section>
@endif

@if ($common)
    <section aria-labelledby="communes" class="rounded-card border border-line bg-card p-5 md:p-6">
        <h2 id="communes" class="flex items-center gap-2 text-[20px] font-extrabold"><x-icon name="chat" :size="22" />Entrer en relation</h2>
        <p class="mt-2 text-[14px] text-muted">Des capacités en commun avec {{ $user->name }}, d’après vos deux profils :</p>
        <dl class="mt-3 space-y-3 text-[14px]">
            @if ($common['theyBring'] ?? [])<div><dt class="font-bold">Peut vous apporter ce que vous voulez apprendre</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach ($common['theyBring'] as $name)<span class="rounded-chip bg-type-knowledge-bg px-3 py-1 text-[12px] font-semibold text-type-knowledge-fg">{{ $name }}</span>@endforeach</dd></div>@endif
            @if ($common['youBring'] ?? [])<div><dt class="font-bold">Veut apprendre ce que vous pouvez apporter</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach ($common['youBring'] as $name)<span class="rounded-chip bg-soft-blue px-3 py-1 text-[12px] font-semibold text-link">{{ $name }}</span>@endforeach</dd></div>@endif
            @if ($common['shared'] ?? [])<div><dt class="font-bold">Capacités que vous partagez</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach ($common['shared'] as $name)<span class="rounded-chip bg-ground px-3 py-1 text-[12px] font-semibold">{{ $name }}</span>@endforeach</dd></div>@endif
        </dl>
        <a href="#contact" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[14px] font-bold">Voir les possibilités de contact<x-icon name="arrow-right" :size="16" /></a>
        <p class="mt-1 text-[12px] text-muted">Une nouvelle relation commence par un accord mutuel.</p>
    </section>
@endif

<livewire:profile-gratitudes :profile="$profile" />
