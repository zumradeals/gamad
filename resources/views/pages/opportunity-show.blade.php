@php
    $company = $opportunity->company;
    $verified = $company->verified();
    $deadline = $opportunity->deadline?->locale('fr')->translatedFormat('j M Y');
    $published = $opportunity->published_at?->copy()->locale('fr')->timezone('Africa/Abidjan');
    $open = $opportunity->isOpen();
    $missions = \App\Models\Opportunity::bullets($opportunity->missions);
    $profileLines = \App\Models\Opportunity::bullets($opportunity->profile);
    $guest = ! auth()->check();
    $chips = array_filter([$opportunity->workloadLabel(), $opportunity->workModeLabel()]);
@endphp
<x-layouts.app :title="$opportunity->title" extra-wide>
<div class="flex min-w-0 flex-col gap-4">
    <a href="{{ route('opportunites.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink"><x-icon name="back" :size="18" /> Retour aux opportunités</a>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @if ($isOwner && $opportunity->status !== 'published')
        <p role="status" class="rounded-button bg-type-call-bg px-4 py-3 text-[14px] font-semibold text-type-call-fg">{{ $opportunity->status === 'draft' ? 'Brouillon : seule votre entreprise voit cette opportunité.' : 'Cette opportunité est '.mb_strtolower($opportunity->statusLabel()).'.' }}</p>
    @endif

    <div class="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_330px]">
        <article class="min-w-0 overflow-hidden rounded-card border border-line bg-card">
            <div class="relative h-40 overflow-hidden bg-ink sm:h-56" aria-hidden="true">
                @if ($company->banner_path && $verified)
                    <img src="{{ route('entreprises.visual', [$company, 'banner']) }}" alt="" class="absolute inset-0 size-full object-cover" fetchpriority="high">
                @else
                    <div class="absolute inset-0 bg-gradient-to-r from-ink via-[#123a57] to-[#1c5a82]"></div>
                    <div class="absolute bottom-0 right-24 h-2 w-32 rounded-t-button bg-accent"></div>
                @endif
            </div>
            <div class="relative min-w-0 px-5 pb-6 md:px-8">
                <div class="-mt-10 flex min-w-0 flex-wrap items-end gap-4">
                    <x-company-logo :company="$company" :size="84" class="border-4 border-card bg-card shadow-sm" />
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1 pb-1">
                        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center break-words text-[17px] font-extrabold text-ink no-underline hover:underline">{{ $company->name }}</a>
                        @if ($verified)<span class="inline-flex size-5 items-center justify-center rounded-full bg-link text-white" title="Immatriculation vérifiée par GAMAD"><x-icon name="check" :size="13" :stroke="3" /><span class="sr-only">Immatriculation vérifiée par GAMAD</span></span>@endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pb-1">
                        @if ($canRespond)
                            <x-button :href="route('opportunites.respond', $opportunity)" variant="accent">{{ $opportunity->kind->applyLabel() }}</x-button>
                        @elseif ($open && $opportunity->apply_mode === 'external' && $opportunity->external_url)
                            <x-button :href="$opportunity->external_url" variant="accent" rel="noopener noreferrer nofollow" target="_blank">{{ $opportunity->kind->applyLabel() }} sur le site de l’entreprise <x-icon name="external" :size="16" class="ml-1.5" /></x-button>
                        @elseif ($open && $guest)
                            <x-button :href="route('login')" variant="accent">Se connecter pour répondre</x-button>
                        @endif
                        @auth
                            @if ($opportunity->status === 'published' || $opportunity->status === 'closed')
                                <form method="POST" action="{{ route('opportunites.save', $opportunity) }}">@csrf
                                    <button type="submit" aria-pressed="{{ $saved ? 'true' : 'false' }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-button border border-ink bg-card px-4 text-[14px] font-bold"><x-icon name="book" :size="16" />{{ $saved ? 'Sauvegardée' : 'Sauvegarder' }}</button>
                                </form>
                            @endif
                        @endauth
                        <span x-data="{ copied: false, url: @js(route('opportunites.show', $opportunity)) }" class="inline-flex items-center">
                            <button type="button" x-on:click="if (navigator.share) { navigator.share({ url }).catch(() => {}); } else if (navigator.clipboard) { navigator.clipboard.writeText(url).then(() => copied = true).catch(() => window.prompt('Copiez ce lien', url)); } else { window.prompt('Copiez ce lien', url); }" class="inline-flex min-h-11 items-center gap-1.5 rounded-button px-3 text-[14px] font-bold text-link"><x-icon name="send" :size="16" />Partager</button>
                            <span x-show="copied" x-cloak role="status" class="text-[12px] font-semibold text-type-knowledge-fg">Lien copié.</span>
                        </span>
                    </div>
                </div>

                @if ($response && $response->state !== 'withdrawn')
                    <div role="status" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-button bg-soft-blue px-4 py-3 text-[14px]">
                        <span><strong>Votre réponse :</strong> {{ $response->stateLabel() }}.</span>
                        <form method="POST" action="{{ route('opportunites.withdraw', $response) }}" x-data x-on:submit="if (! confirm('Retirer votre réponse ?')) $event.preventDefault()">@csrf<button type="submit" class="min-h-11 text-[13px] font-bold text-danger">Retirer ma réponse</button></form>
                    </div>
                @endif
                @unless ($open)
                    <p role="status" class="mt-4 rounded-button bg-type-need-bg px-4 py-3 text-[14px] font-bold text-type-need-fg">{{ $opportunity->statusLabel() }} : cette opportunité n’accepte plus de réponses.</p>
                @endunless

                <h1 class="mt-5 break-words text-[28px] font-extrabold leading-tight">{{ $opportunity->title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-chip px-3 py-1 text-[13px] font-bold {{ $opportunity->kind->badgeClasses() }}">{{ $opportunity->kind->label() }}</span>
                    @foreach ($chips as $chip)<span class="rounded-chip border border-line px-3 py-1 text-[13px] font-semibold">{{ $chip }}</span>@endforeach
                </div>
                <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[14px] text-muted">
                    @if ($opportunity->city)<span class="inline-flex items-center gap-1.5"><x-icon name="pin" :size="16" />{{ $opportunity->city->label() }}</span>@endif
                    @if ($published)<span>Publiée {{ $published->diffForHumans() }}</span>@endif
                </p>

                <section class="mt-6" aria-labelledby="desc-titre"><h2 id="desc-titre" class="text-[18px] font-extrabold">Description {{ $opportunity->kind->value === 'job' ? 'du poste' : 'de l’opportunité' }}</h2>
                    <p class="mt-2 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $opportunity->description }}</p></section>
                @if ($missions)<section class="mt-6" aria-labelledby="missions-titre"><h2 id="missions-titre" class="text-[18px] font-extrabold">Vos missions</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-[15px] leading-relaxed">@foreach ($missions as $line)<li class="break-words">{{ $line }}</li>@endforeach</ul></section>@endif
                @if ($profileLines || $opportunity->skills || $opportunity->experience || $opportunity->qualifications)
                    <section class="mt-6" aria-labelledby="profil-titre"><h2 id="profil-titre" class="text-[18px] font-extrabold">Profil recherché</h2>
                        @if ($profileLines)<ul class="mt-2 list-disc space-y-1 pl-5 text-[15px] leading-relaxed">@foreach ($profileLines as $line)<li class="break-words">{{ $line }}</li>@endforeach</ul>@endif
                        @if ($opportunity->experience)<p class="mt-2 text-[15px]"><strong>Expérience :</strong> {{ $opportunity->experience }}</p>@endif
                        @if ($opportunity->qualifications)<p class="mt-2 whitespace-pre-line break-words text-[15px]"><strong>Qualifications nécessaires :</strong> {{ $opportunity->qualifications }}</p>@endif
                        @if ($opportunity->skills)<ul aria-label="Compétences recherchées" class="mt-3 flex flex-wrap gap-2">@foreach ($opportunity->skills as $skill)<li class="max-w-full break-words rounded-chip bg-soft-blue px-3 py-1.5 text-[12px] font-semibold text-link">{{ $skill }}</li>@endforeach</ul>@endif
                    </section>
                @endif
                @if ($opportunity->conditions)<section class="mt-6" aria-labelledby="cond-titre"><h2 id="cond-titre" class="text-[18px] font-extrabold">Conditions</h2>
                    <p class="mt-2 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $opportunity->conditions }}</p></section>@endif

                <section class="mt-6 rounded-card border border-line p-4" aria-labelledby="apropos-titre">
                    <h2 id="apropos-titre" class="text-[18px] font-extrabold">À propos de l’entreprise</h2>
                    <div class="mt-3 flex min-w-0 items-center gap-3"><x-company-logo :company="$company" :size="52" />
                        <div class="min-w-0 flex-1"><p class="break-words text-[15px] font-extrabold">{{ $company->name }}</p><p class="text-[13px] text-muted">{{ $company->sector }}@if ($company->city) · {{ $company->city }}@endif</p></div>
                        <x-button :href="route('entreprises.show', $company)" variant="secondary">Voir le profil de l’entreprise</x-button></div>
                    @if ($company->summary)<p class="mt-3 whitespace-pre-line break-words text-[14px] leading-relaxed text-muted">{{ $company->summary }}</p>@endif
                </section>
            </div>
        </article>

        <aside class="min-w-0 space-y-4" aria-label="Informations">
            <section class="rounded-card border border-line bg-card p-5" aria-labelledby="infos-titre">
                <h2 id="infos-titre" class="text-[17px] font-extrabold">Informations</h2>
                <dl class="mt-3 divide-y divide-line-soft text-[14px]">
                    <div class="py-2.5"><dt class="text-[12px] text-muted">Type d’opportunité</dt><dd class="font-semibold">{{ $opportunity->kind->label() }}</dd></div>
                    @if ($opportunity->domain)<div class="py-2.5"><dt class="text-[12px] text-muted">Domaine</dt><dd class="break-words font-semibold">{{ $opportunity->domain->name }}</dd></div>@endif
                    @if ($opportunity->city)<div class="py-2.5"><dt class="text-[12px] text-muted">Localité</dt><dd class="font-semibold">{{ $opportunity->city->label() }}</dd></div>@endif
                    @if ($published)<div class="py-2.5"><dt class="text-[12px] text-muted">Date de publication</dt><dd class="font-semibold">{{ $published->diffForHumans() }}</dd></div>@endif
                    @if ($deadline)<div class="py-2.5"><dt class="text-[12px] text-muted">Date limite</dt><dd class="font-semibold">{{ $deadline }}</dd></div>@endif
                    <div class="py-2.5"><dt class="text-[12px] text-muted">Statut</dt><dd class="font-semibold">{{ $opportunity->statusLabel() }}</dd></div>
                    <div class="py-2.5"><dt class="text-[12px] text-muted">Référence</dt><dd class="font-semibold">{{ $opportunity->reference }}</dd></div>
                </dl>
                @if ($opportunity->apply_mode === 'external' && $open)<p class="mt-3 text-[12px] leading-normal text-muted">La réponse se fait sur le site de l’entreprise : GAMAD n’y a pas accès.</p>@endif
            </section>
            @if ($isOwner)
                <section class="rounded-card border border-line bg-card p-5"><h2 class="text-[17px] font-extrabold">Gestion</h2>
                    <div class="mt-3 flex flex-col gap-2"><x-button :href="route('opportunites.edit', $opportunity)" variant="secondary">Modifier</x-button><x-button :href="route('opportunites.responses', $opportunity)" variant="secondary">Voir les réponses</x-button></div></section>
            @endif
            @if ($similar->isNotEmpty())
                <section class="rounded-card border border-line bg-card p-5"><h2 class="text-[17px] font-extrabold">Autres opportunités de {{ $company->name }}</h2>
                    <ul class="mt-3 space-y-2">@foreach ($similar as $item)<li><a href="{{ route('opportunites.show', $item) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $item->title }}</a></li>@endforeach</ul></section>
            @endif
        </aside>
    </div>
</div>
</x-layouts.app>
