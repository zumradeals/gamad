@php
    $verified = $company->verified();
    $since = $company->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('F Y');
    $place = collect([$company->city, $company->registration_country])->filter()->implode(', ');
    $about = $company->description ?: $company->summary;
    $aboutLong = mb_strlen((string) $about) > 320;
    $firstLine = fn ($post) => \Illuminate\Support\Str::limit(trim(strtok(trim($post->body), "\n")), 70);
    $restOf = fn ($post) => \Illuminate\Support\Str::limit(trim(preg_replace('/^[^\n]*\n?/', '', trim($post->body)) ?: trim($post->body)), 110);
    $tabs = ['presentation' => 'Présentation'];
    if ($verified) {
        $tabs += ['realisations' => 'Réalisations', 'produits-services' => 'Produits et services', 'offres' => 'Offres', 'publications' => 'Publications'];
        if ($confirmedPeople->isNotEmpty()) {
            $tabs['equipe'] = 'Équipe';
        }
    }
    $shareUrl = route('entreprises.show', $company);
@endphp

<x-layouts.app :title="$company->name" extra-wide>
<div class="flex min-w-0 flex-col gap-5">
    <a href="{{ route('entreprises.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink"><x-icon name="back" :size="18"/> Retour aux entreprises</a>

    <article class="overflow-hidden rounded-card border border-line bg-card">
        <div class="relative h-40 overflow-hidden bg-ink sm:h-52 lg:h-60" aria-label="Bannière de l'entreprise">
            @if ($company->banner_path)
                <img src="{{ route('entreprises.visual', [$company, 'banner']) }}" alt="Bannière de {{ $company->name }}" class="absolute inset-0 size-full object-cover" fetchpriority="high">
                <div class="absolute inset-0 bg-gradient-to-t from-ink/40 to-transparent" aria-hidden="true"></div>
            @else
                <div class="absolute inset-0 bg-gradient-to-r from-ink via-[#123a57] to-[#1c5a82]" aria-hidden="true"></div>
                <div class="absolute -right-12 -top-24 size-96 rounded-full border-[36px] border-white/10" aria-hidden="true"></div>
                <div class="absolute bottom-0 right-24 h-2 w-32 rounded-t-button bg-accent" aria-hidden="true"></div>
                <div class="absolute -bottom-32 right-72 size-72 rounded-full border border-white/10" aria-hidden="true"></div>
            @endif
        </div>

        <div class="relative px-5 md:px-8">
            <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:gap-8">
                <div class="-mt-14 flex size-[104px] shrink-0 items-center justify-center overflow-hidden self-start rounded-card border-4 border-card bg-soft-blue text-[34px] font-extrabold text-ink shadow-sm lg:-mt-20 lg:size-[148px] lg:text-[44px]" aria-label="Logo de l'entreprise">
                    @if ($company->logo_path)
                        <img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="Logo de {{ $company->name }}" class="size-full bg-white object-contain">
                    @else
                        {{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}
                    @endif
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2 pb-2 lg:pt-4">
                    <h1 class="flex flex-wrap items-center gap-x-3 break-words text-[28px] font-extrabold leading-tight tracking-[-0.5px] md:text-[32px]">{{ $company->name }}
                        @if ($verified)<span class="inline-flex size-6 items-center justify-center rounded-full bg-link text-white" title="Immatriculation vérifiée par GAMAD"><x-icon name="check" :size="15" :stroke="3"/><span class="sr-only">Immatriculation vérifiée par GAMAD</span></span>@endif
                    </h1>
                    <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[14px] text-muted">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="project" :size="16" class="shrink-0"/>{{ $company->sector }}</span>
                        @if ($place !== '')<span class="inline-flex items-center gap-1.5"><x-icon name="pin" :size="16" class="shrink-0"/>{{ $place }}</span>@endif
                        <span class="inline-flex items-center gap-1.5"><x-icon name="tasks" :size="16" class="shrink-0"/>Membre depuis {{ $since }}</span>
                    </p>
                    @unless ($verified)
                        <span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-call-bg px-3 py-1.5 text-[12px] font-bold text-type-call-fg"><x-icon name="shield" :size="16"/> {{ $company->status === 'pending' ? 'Contrôle administratif en attente' : 'Page non publique' }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 self-start text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="15"/> Immatriculation vérifiée par GAMAD</span>
                    @endunless
                    @if ($company->summary)<p class="max-w-[720px] whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $company->summary }}</p>@endif
                    <ul aria-label="Domaine d'intervention" class="mt-1 flex flex-wrap gap-2"><li class="max-w-full break-words rounded-chip bg-soft-blue px-3 py-1.5 text-[12px] font-semibold text-link">{{ $company->sector }}</li></ul>
                </div>
                <div class="flex min-w-0 flex-col gap-4 pb-2 lg:w-[400px] lg:shrink-0 lg:pt-5">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isOwner)
                            <x-button :href="route('entreprises.branding.edit', $company)" variant="accent"><x-icon name="edit" :size="18" class="mr-2"/>Modifier la page</x-button>
                            @if ($verified)
                                <span x-data="{ copied: false }" class="inline-flex items-center"><button type="button" x-on:click="if (navigator.share) { navigator.share({ title: @js($company->name), url: @js($shareUrl) }).catch(() => {}); } else if (navigator.clipboard) { navigator.clipboard.writeText(@js($shareUrl)).then(() => copied = true); } else { window.prompt('Copiez ce lien', @js($shareUrl)); }" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-button border border-ink bg-card px-5 text-[15px] font-bold"><x-icon name="external" :size="18"/>Partager la page</button><span x-show="copied" x-cloak role="status" class="ml-2 text-[12px] font-semibold text-type-knowledge-fg">Lien copié.</span></span>
                            @endif
                        @elseif ($verified)
                            <a href="{{ auth()->check() ? route('entreprises.contact', $company) : route('login') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-button bg-accent px-5 text-[15px] font-bold text-ink no-underline"><x-icon name="chat" :size="18"/>Contacter</a>
                            @auth
                                <form method="POST" action="{{ route('entreprises.follow', $company) }}">@csrf
                                    <x-button type="submit" variant="secondary"><x-icon :name="$following ? 'check' : 'plus'" :size="17" class="mr-1.5"/>{{ $following ? 'Abonné à la page' : 'Suivre' }}</x-button>
                                </form>
                            @else
                                <x-button :href="route('login')" variant="secondary"><x-icon name="plus" :size="17" class="mr-1.5"/>Suivre</x-button>
                            @endauth
                            <div x-data="{ menu: false, copied: false }" class="relative" x-on:click.outside="menu = false" x-on:keydown.escape.window="menu = false">
                                <button type="button" x-on:click="menu = ! menu" x-bind:aria-expanded="menu" aria-haspopup="menu" aria-label="Autres actions pour cette page" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-button border border-line text-[18px] font-extrabold">⋯</button>
                                <div x-show="menu" x-cloak role="menu" class="absolute right-0 z-20 mt-1 w-64 rounded-card border border-line bg-card p-1 shadow-lg">
                                    <button type="button" role="menuitem" x-on:click="if (navigator.share) { navigator.share({ title: @js($company->name), url: @js($shareUrl) }).catch(() => {}); } else if (navigator.clipboard) { navigator.clipboard.writeText(@js($shareUrl)).then(() => copied = true); } else { window.prompt('Copiez ce lien', @js($shareUrl)); }" class="flex min-h-11 w-full items-center gap-2 px-3 text-left text-[14px] font-semibold"><x-icon name="external" :size="18"/>Partager la page</button>
                                    <span x-show="copied" x-cloak role="status" class="block px-3 pb-1 text-[12px] font-semibold text-type-knowledge-fg">Lien copié.</span>
                                    <a role="menuitem" href="{{ auth()->check() ? route('entreprises.reports.create', $company) : route('login') }}" class="flex min-h-11 items-center gap-2 px-3 text-[14px] font-semibold text-ink no-underline"><x-icon name="shield" :size="18"/>Signaler cette page entreprise</a>
                                </div>
                            </div>
                        @endif
                    </div>
                    @if ($stats !== [])
                        <dl class="grid divide-x divide-line-soft text-center" style="grid-template-columns: repeat({{ count($stats) }}, minmax(0, 1fr))">
                            @foreach ($stats as $label => $total)
                                <div class="px-2"><dd class="text-[22px] font-extrabold leading-none">{{ $total }}</dd><dt class="mt-1 text-[13px] text-muted">{{ $label }}</dt></div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </div>
            <nav aria-label="Rubriques de l'entreprise" class="mt-3 flex min-w-0 overflow-x-auto border-t border-line text-center"
                x-data="{ active: 'presentation' }"
                x-init="const obs = new IntersectionObserver((entries) => { entries.forEach((entry) => { if (entry.isIntersecting) { active = entry.target.id; } }); }, { rootMargin: '-25% 0px -65% 0px' });
                    @js(array_keys($tabs)).forEach((id) => { const el = document.getElementById(id); if (el) { obs.observe(el); } });">
                @foreach ($tabs as $anchor => $label)
                    <a href="#{{ $anchor }}" x-on:click="active = '{{ $anchor }}'" x-bind:aria-current="active === '{{ $anchor }}' ? 'true' : null"
                       x-bind:class="active === '{{ $anchor }}' ? 'border-link text-link' : 'border-transparent text-ink'"
                       class="flex min-h-14 flex-1 shrink-0 items-center justify-center whitespace-nowrap border-b-[3px] px-4 text-[13px] font-bold no-underline hover:text-link sm:text-[14px] {{ $loop->first ? 'border-link text-link' : 'border-transparent text-ink' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </article>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif

    @if ($isOwner && $verified)
        <div class="grid gap-3 md:grid-cols-2">
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-card p-4 md:p-5">
                <div><h2 class="text-[17px] font-extrabold">Faire vivre la page entreprise</h2>
                    <p class="mt-1 text-[13px] text-muted">Actualités, réalisations et opportunités rejoignent l'Accueil social de GAMAD.</p></div>
                <x-button :href="route('entreprises.posts.create', $company)" variant="accent">Publier sur GAMAD</x-button>
            </section>
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-card p-4 md:p-5">
                <div><h2 class="text-[17px] font-extrabold">Confirmer des expériences professionnelles</h2>
                    <p class="mt-1 text-[13px] text-muted">Consultez les déclarations reçues et confirmez uniquement celles qui sont exactes.</p></div>
                <x-button :href="route('entreprises.experiences.review', $company)" variant="secondary">Gérer les confirmations</x-button>
            </section>
        </div>
    @endif

    @if ($isOwner && ! $verified)
        <section class="rounded-card border border-line bg-card p-4 md:p-5" role="status">
            <h2 class="text-[16px] font-extrabold">Aperçu privé de votre entreprise</h2>
            <p class="mt-1 text-[14px] text-muted">Cette page ne figure pas dans la recherche et les autres membres n'y ont pas accès tant que l'immatriculation n'est pas vérifiée.</p>
            @if ($company->review_note)<p class="mt-2 break-words text-[13px] font-semibold">Message de l'examen : {{ $company->review_note }}</p>@endif
            @if ($company->status === 'declined')
                <a href="{{ route('entreprises.resubmit', $company) }}" class="mt-3 inline-flex min-h-11 items-center text-[13px] font-bold text-link">Corriger mes justificatifs et redemander un examen</a>
            @endif
        </section>
    @endif

    <div class="grid min-w-0 items-start gap-5 lg:grid-cols-2">
        <div class="flex min-w-0 flex-col gap-5">
            <section id="presentation" aria-labelledby="mission-titre" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6" x-data="{ more: false }">
                <div class="flex items-start gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="compass" :size="26"/></span>
                    <div class="min-w-0 flex-1">
                        <h2 id="mission-titre" class="text-[20px] font-extrabold">Notre mission</h2>
                        <p @class(['mt-1 whitespace-pre-line break-words text-[14px] leading-relaxed text-muted', 'line-clamp-4' => $aboutLong]) @if ($aboutLong) x-bind:class="more ? '!line-clamp-none' : ''" @endif>{{ $about ?: 'La présentation détaillée de cette entreprise sera ajoutée ici.' }}</p>
                        @if ($aboutLong)<button type="button" x-on:click="more = ! more" x-bind:aria-expanded="more.toString()" class="mt-1 inline-flex min-h-11 items-center gap-1 text-[13px] font-bold text-link"><span x-text="more ? 'Réduire' : 'En savoir plus'">En savoir plus</span><x-icon name="arrow-right" :size="15"/></button>@endif
                    </div>
                </div>
                <ul aria-label="Secteurs" class="mt-3 flex flex-wrap gap-2"><li class="max-w-full break-words rounded-chip bg-soft-blue px-3 py-1.5 text-[12px] font-semibold text-link">{{ $company->sector }}</li></ul>
            </section>

            @if ($verified)
                <section id="realisations" aria-labelledby="realisations-titre" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6" x-data="{ all: false }">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-button text-ink" style="background-color: #fdf3d4"><x-icon name="idea" :size="26"/></span>
                        <div class="min-w-0 flex-1"><h2 id="realisations-titre" class="text-[20px] font-extrabold">Nos réalisations</h2><p class="mt-1 text-[14px] text-muted">Des projets et initiatives concrets publiés par l'entreprise.</p></div>
                    </div>
                    @if ($works->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($works->take(3) as $post)
                                <article class="flex min-w-0 flex-col overflow-hidden rounded-button border border-line">
                                    <div class="[&_img]:h-32 [&_img]:w-full [&_img]:rounded-none [&_img]:object-cover [&_video]:max-h-40"><x-publication-media :publication="$post"/></div>
                                    <div class="flex flex-1 flex-col gap-1 p-3">
                                        <h3 class="break-words text-[14px] font-extrabold leading-snug">{{ $firstLine($post) }}</h3>
                                        <p class="break-words text-[13px] leading-snug text-muted">{{ $restOf($post) }}</p>
                                        <ul class="mt-1 flex flex-wrap gap-1.5"><li class="max-w-full break-words rounded-chip bg-soft-blue px-2.5 py-1 text-[11px] font-semibold text-link">{{ $post->domain?->name ?? $company->sector }}</li></ul>
                                        <a href="{{ route('publications.show', $post) }}" class="mt-auto inline-flex min-h-11 items-center pt-1 text-[13px] font-bold">Voir la réalisation →</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if ($works->count() > 3)
                            <div x-show="all" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($works->slice(3) as $post)
                                    <article class="flex min-w-0 flex-col overflow-hidden rounded-button border border-line">
                                        <div class="[&_img]:h-32 [&_img]:w-full [&_img]:rounded-none [&_img]:object-cover [&_video]:max-h-40"><x-publication-media :publication="$post"/></div>
                                        <div class="flex flex-1 flex-col gap-1 p-3"><h3 class="break-words text-[14px] font-extrabold leading-snug">{{ $firstLine($post) }}</h3><p class="break-words text-[13px] leading-snug text-muted">{{ $restOf($post) }}</p>
                                            <a href="{{ route('publications.show', $post) }}" class="mt-auto inline-flex min-h-11 items-center pt-1 text-[13px] font-bold">Voir la réalisation →</a></div>
                                    </article>
                                @endforeach
                            </div>
                            <button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all.toString()" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link"><span x-text="all ? 'Réduire' : 'Voir toutes les réalisations ({{ $works->count() }})'">Voir toutes les réalisations ({{ $works->count() }})</span></button>
                        @endif
                    @else
                        <p class="mt-4 text-[14px] text-muted">Aucune réalisation publiée pour le moment.</p>
                        @if ($isOwner)<a href="{{ route('entreprises.posts.create', $company) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold text-link">Publier une réalisation →</a>@endif
                    @endif
                </section>

                <section id="produits-services" aria-labelledby="services-titre" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="grid" :size="26"/></span>
                        <div class="min-w-0 flex-1"><h2 id="services-titre" class="text-[20px] font-extrabold">Nos produits et services</h2><p class="mt-1 text-[14px] text-muted">Une présentation pour découvrir l'entreprise : GAMAD ne vend rien et n'enregistre aucun paiement.</p></div>
                    </div>
                    @if ($services->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($services as $service)
                                <article class="flex min-w-0 flex-col gap-1 rounded-button border border-line p-3">
                                    @if (filled($service['category'] ?? null))<span class="self-start rounded-chip bg-soft-blue px-2.5 py-1 text-[11px] font-semibold text-link">{{ $service['category'] }}</span>@endif
                                    <h3 class="break-words text-[14px] font-extrabold leading-snug">{{ $service['name'] }}</h3>
                                    @if (filled($service['description'] ?? null))<p class="break-words text-[13px] leading-snug text-muted">{{ $service['description'] }}</p>@endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-[14px] text-muted">Cette entreprise n’a pas encore présenté ses produits et services.</p>
                        @if ($isOwner)<a href="{{ route('entreprises.branding.edit', $company) }}#services" class="inline-flex min-h-11 items-center text-[14px] font-bold text-link">Présenter mes produits et services →</a>@endif
                    @endif
                </section>
            @endif

            <section id="contact" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
                <h2 class="text-[18px] font-extrabold">Informations professionnelles</h2>
                <dl class="mt-4 space-y-3 text-[14px]">
                    <div><dt class="text-[12px] font-semibold text-muted">Raison sociale</dt><dd class="mt-1 break-words font-bold">{{ $company->legal_name }}</dd></div>
                    <div><dt class="text-[12px] font-semibold text-muted">Lieu</dt><dd class="mt-1 font-bold">{{ $company->city ?: 'Non renseigné' }} · {{ $company->registration_country }}</dd></div>
                    @if ($company->website)<div><dt class="text-[12px] font-semibold text-muted">Site web</dt><dd class="mt-1 break-all"><a href="{{ $company->website }}" rel="noopener noreferrer nofollow" target="_blank" class="font-bold text-link">{{ $company->website }}</a></dd></div>@endif
                    @if ($company->email)<div><dt class="text-[12px] font-semibold text-muted">Contact professionnel</dt><dd class="mt-1 break-all"><a href="mailto:{{ $company->email }}" class="font-bold text-link">{{ $company->email }}</a></dd></div>@endif
                </dl>
                <div class="mt-5 border-t border-line-soft pt-4">
                    <div class="flex items-center gap-2"><x-icon name="shield" :size="22"/><h2 class="text-[17px] font-extrabold">Confiance sur GAMAD</h2></div>
                    <p class="mt-3 text-[13px] leading-relaxed text-muted">{{ $verified ? 'GAMAD a contrôlé les éléments d’immatriculation de cette entreprise.' : 'Le contrôle des pièces d’immatriculation n’est pas encore terminé.' }} Cette mention ne garantit pas les prestations ni l'identité du gestionnaire de la page.</p>
                    <p class="mt-3 text-[12px] text-muted">Les documents administratifs déposés ne sont jamais publiés.</p>
                </div>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-5">
            @if ($verified)
                <section id="offres" aria-labelledby="offres-titre" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="community" :size="26"/></span>
                        <div class="min-w-0 flex-1"><h2 id="offres-titre" class="text-[20px] font-extrabold">Nos offres</h2><p class="mt-1 text-[14px] text-muted">Opportunités et collaborations proposées par l'entreprise.</p></div>
                    </div>
                    @forelse ($offers as $offer)
                        <a href="{{ route('publications.show', $offer) }}" @if ($loop->first) class="mt-4 flex min-h-16 items-center gap-3 border-t border-line-soft py-3 text-ink no-underline hover:text-ink" @else class="flex min-h-16 items-center gap-3 border-t border-line-soft py-3 text-ink no-underline hover:text-ink" @endif>
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-button bg-type-need-bg text-type-need-fg"><x-icon name="heart" :size="20"/></span>
                            <span class="min-w-0 flex-1"><span class="block break-words text-[14px] font-extrabold">{{ $firstLine($offer) }}</span><span class="block text-[12px] text-muted">{{ $offer->displayLabel() }} · {{ $offer->city?->label() ?? $company->city }} · {{ $offer->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}</span></span>
                            <x-icon name="next" :size="16"/>
                        </a>
                    @empty
                        <p class="mt-4 text-[14px] text-muted">Aucune offre en cours pour le moment.</p>
                        @if ($isOwner)<a href="{{ route('entreprises.posts.create', $company) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold text-link">Publier une opportunité →</a>@endif
                    @endforelse
                </section>

                <section id="publications" aria-labelledby="publications-titre" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6" x-data="{ all: false }">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="document" :size="26"/></span>
                        <div class="flex min-w-0 flex-1 flex-wrap items-start justify-between gap-2"><h2 id="publications-titre" class="text-[20px] font-extrabold">Nos dernières publications</h2>@if ($isOwner)<a href="{{ route('entreprises.posts.create', $company) }}" class="inline-flex min-h-11 items-center gap-1 text-[13px] font-bold text-link">Nouvelle publication <x-icon name="plus" :size="16"/></a>@endif</div>
                    </div>
                    @forelse ($companyPosts as $post)
                        <article @if ($loop->index >= 3) x-show="all" x-cloak @endif class="mt-4 border-t border-line-soft pt-4">
                            <div class="flex items-start gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-button border border-line bg-soft-blue text-[13px] font-extrabold">@if ($company->logo_path)<img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="" loading="lazy" class="size-full bg-white object-contain">@else{{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}@endif</span>
                                <div class="min-w-0 flex-1"><p class="break-words text-[14px] font-bold">{{ $company->name }}</p><p class="text-[12px] text-muted">{{ $post->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans(['short' => true]) }}</p></div>
                                <span class="shrink-0 rounded-chip px-3 py-1 text-[12px] font-bold {{ $post->type->badgeClasses() }}">{{ $post->displayLabel() }}</span>
                            </div>
                            <p class="mt-3 line-clamp-5 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $post->body }}</p>
                            @if ($post->media_kind === 'image')<div class="mt-2 max-h-52 overflow-hidden rounded-button"><x-publication-media :publication="$post"/></div>@endif
                            <a href="{{ route('publications.show', $post) }}" class="inline-flex min-h-11 items-center gap-1 text-[13px] font-bold text-link">Voir, commenter ou partager <x-icon name="arrow-right" :size="16"/></a>
                        </article>
                    @empty
                        <p class="mt-4 text-[14px] text-muted">L’entreprise n’a pas encore publié d’actualité, de réalisation ou d’opportunité.</p>
                    @endforelse
                    @if ($companyPosts->count() > 3)<button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all.toString()" class="mt-2 inline-flex min-h-11 items-center gap-2 text-[13px] font-bold text-link"><span x-text="all ? 'Réduire' : 'Voir toutes les publications ({{ $companyPosts->count() }})'">Voir toutes les publications ({{ $companyPosts->count() }})</span></button>@endif
                </section>

                @if ($confirmedPeople->isNotEmpty())
                    <section id="equipe" aria-labelledby="equipe-titre" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
                        <h2 id="equipe-titre" class="text-[20px] font-extrabold">Équipe</h2>
                        <p class="mt-1 text-[13px] text-muted">Parcours confirmés : ces personnes ont choisi d'afficher ici leur collaboration confirmée avec l'entreprise.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($confirmedPeople as $item)
                                <a href="{{ route('personnes.show', $item->user->profile) }}" class="flex min-h-20 flex-col gap-1 rounded-button border border-line p-4 no-underline hover:border-ink">
                                    <span class="text-[15px] font-extrabold text-ink">{{ $item->user->name }}</span>
                                    <span class="text-[13px] font-semibold text-muted">{{ $item->title }} · {{ $item->relationshipLabel() }}</span>
                                    <span class="inline-flex items-center gap-1 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="15"/> Expérience confirmée</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @unless ($isOwner)
                    <section aria-labelledby="relation-titre" class="rounded-card border border-line bg-card p-5 md:p-6">
                        <div class="flex items-start gap-4">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-button bg-soft-blue text-link"><x-icon name="chat" :size="26"/></span>
                            <div class="min-w-0 flex-1"><h2 id="relation-titre" class="text-[20px] font-extrabold">Entrer en relation</h2><p class="mt-1 text-[14px] text-muted">Échangez avec {{ $company->name }} pour explorer des opportunités de collaboration ou de partenariat. Une demande de relation est envoyée au gestionnaire de la page ; aucun échange privé ne démarre sans accord.</p></div>
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <a href="{{ auth()->check() ? route('entreprises.contact', $company) : route('login') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-button bg-accent px-5 text-[15px] font-bold text-ink no-underline"><x-icon name="chat" :size="18"/>Contacter l’entreprise</a>
                            @auth
                                <form method="POST" action="{{ route('entreprises.follow', $company) }}">@csrf
                                    <x-button type="submit" variant="secondary" class="w-full"><x-icon :name="$following ? 'check' : 'plus'" :size="17" class="mr-1.5"/>{{ $following ? 'Ne plus suivre cette page' : 'Suivre' }}</x-button>
                                </form>
                            @else
                                <x-button :href="route('login')" variant="secondary" class="w-full">Se connecter pour suivre</x-button>
                            @endauth
                        </div>
                    </section>
                @endunless
            @endif
        </div>
    </div>
</div>
</x-layouts.app>
