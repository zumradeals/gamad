<x-layouts.app :title="$company->name">
<div class="flex min-w-0 flex-col gap-4">
    <a href="{{ route('entreprises.index') }}" class="inline-flex min-h-11 items-center gap-1 self-start text-[13px] font-bold"><x-icon name="back" :size="17"/> Toutes les entreprises</a>

    <article class="overflow-hidden rounded-[22px] border border-line bg-card">
        <div class="relative h-[175px] overflow-hidden bg-ink md:h-[235px]" aria-label="Bannière de l'entreprise">
            @if ($company->banner_path)
                <img src="{{ route('entreprises.visual', [$company, 'banner']) }}" alt="Bannière de {{ $company->name }}" class="absolute inset-0 size-full object-cover" fetchpriority="high">
                <div class="absolute inset-0 bg-gradient-to-t from-ink/40 to-transparent" aria-hidden="true"></div>
            @else
            <div class="absolute -right-16 -top-28 size-[380px] rounded-full border-[50px] border-accent/30" aria-hidden="true"></div>
            <div class="absolute bottom-0 left-[6%] h-28 w-44 rounded-t-full border-[22px] border-white/10" aria-hidden="true"></div>
            <div class="absolute right-[23%] top-[20%] h-[70px] w-[140px] rotate-[-24deg] rounded-[12px] border-[10px] border-white/10" aria-hidden="true"></div>
            @endif
            <span class="absolute bottom-5 right-5 rounded-full border border-white/35 bg-white/10 px-3 py-1.5 text-[11px] font-semibold text-white">GAMAD · Entreprises</span>
        </div>

        <div class="relative px-4 pb-6 md:px-7">
            <div class="flex min-w-0 flex-col gap-4 md:flex-row md:items-start md:gap-5">
                <div class="-mt-11 flex size-[92px] shrink-0 items-center justify-center rounded-[22px] border-[5px] border-card bg-soft-blue text-[33px] font-extrabold text-ink shadow-sm md:-mt-14 md:size-[115px]" aria-label="Logo de l'entreprise">
                    @if ($company->logo_path)
                        <img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="Logo de {{ $company->name }}" class="size-full rounded-[16px] bg-white object-contain">
                    @else
                        {{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}
                    @endif
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2 pt-0 md:pt-4">
                    <h1 class="break-words text-[28px] font-extrabold leading-tight md:text-[36px]">{{ $company->name }}</h1>
                    <p class="text-[15px] font-bold text-muted">{{ $company->sector }} @if ($company->city) · {{ $company->city }} @endif · {{ $company->registration_country }}</p>
                    @if ($company->verified())
                        <span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-knowledge-bg px-3 py-1.5 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="16"/> Immatriculation vérifiée par GAMAD</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-call-bg px-3 py-1.5 text-[12px] font-bold text-type-call-fg"><x-icon name="shield" :size="16"/> {{ $company->status === 'pending' ? 'Contrôle administratif en attente' : 'Page non publique' }}</span>
                    @endif
                    @if ($company->summary)<p class="max-w-[680px] whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $company->summary }}</p>@endif
                </div>
                <div class="flex flex-wrap items-center gap-2 md:pt-5">
                    @if ($isOwner)
                        <x-button :href="route('entreprises.branding.edit', $company)" variant="secondary"><x-icon name="pencil" :size="16" class="mr-1"/> Personnaliser</x-button>
                    @endif
                    @if ($company->verified())
                        @auth
                            <form method="POST" action="{{ route('entreprises.follow', $company) }}">@csrf
                                <x-button type="submit" :variant="$following ? 'secondary' : 'accent'"><x-icon :name="$following ? 'check' : 'plus'" :size="17" class="mr-1"/>{{ $following ? 'Abonné à la page' : 'Suivre' }}</x-button>
                            </form>
                        @else
                            <x-button :href="route('login')" variant="accent">Suivre</x-button>
                        @endauth
                        <button type="button" x-data x-on:click="navigator.share ? navigator.share({ title: @js($company->name), url: @js(route('entreprises.show', $company)) }).catch(() => {}) : navigator.clipboard?.writeText(@js(route('entreprises.show', $company)))" class="inline-flex min-h-11 items-center gap-1 rounded-button border border-line px-3 text-[13px] font-bold" aria-label="Partager la page"><x-icon name="external" :size="17"/> Partager</button>
                    @endif
                </div>
            </div>
            <nav aria-label="Rubriques de l'entreprise" class="mt-6 flex min-w-0 gap-5 overflow-x-auto border-t border-line-soft pt-2">
                <a href="#presentation" class="inline-flex min-h-11 shrink-0 items-center border-b-[3px] border-accent text-[13px] font-extrabold text-ink no-underline">À propos</a>
                <a href="#activites" class="inline-flex min-h-11 shrink-0 items-center text-[13px] font-semibold text-muted no-underline">Activités</a>
                <a href="#contact" class="inline-flex min-h-11 shrink-0 items-center text-[13px] font-semibold text-muted no-underline">Coordonnées</a>
                @if ($company->verified())<a href="#publications" class="inline-flex min-h-11 shrink-0 items-center text-[13px] font-semibold text-muted no-underline">Publications</a>@endif
                @if ($confirmedPeople->isNotEmpty())<a href="#parcours-confirmes" class="inline-flex min-h-11 shrink-0 items-center text-[13px] font-semibold text-muted no-underline">Parcours confirmés</a>@endif
            </nav>
        </div>
    </article>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif

    @if ($isOwner && $company->verified())
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
    @endif

    @if ($isOwner && ! $company->verified())
        <section class="rounded-card border border-line bg-card p-4 md:p-5" role="status">
            <h2 class="text-[16px] font-extrabold">Aperçu privé de votre entreprise</h2>
            <p class="mt-1 text-[14px] text-muted">Cette page ne figure pas dans la recherche et les autres membres n'y ont pas accès tant que l'immatriculation n'est pas vérifiée.</p>
            @if ($company->review_note)<p class="mt-2 break-words text-[13px] font-semibold">Message de l'examen : {{ $company->review_note }}</p>@endif
        </section>
    @endif

    <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-4">
            <section id="presentation" class="rounded-card border border-line bg-card p-5 md:p-6">
                <div class="mb-4 flex items-center gap-2"><x-icon name="project" :size="22"/><h2 class="text-[21px] font-extrabold">Notre entreprise</h2></div>
                <p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $company->description ?: ($company->summary ?: 'La présentation détaillée de cette entreprise sera ajoutée ici.') }}</p>
            </section>
            @if ($confirmedPeople->isNotEmpty())
                <section id="parcours-confirmes" class="rounded-card border border-line bg-card p-5 md:p-6">
                    <h2 class="text-[20px] font-extrabold">Parcours confirmés</h2>
                    <p class="mt-2 text-[13px] text-muted">Ces personnes ont choisi d'afficher ici leur collaboration confirmée avec l'entreprise.</p>
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
            @if ($company->verified())
                <section id="publications" class="scroll-mt-44 rounded-card border border-line bg-card p-5 md:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="text-[21px] font-extrabold">Publications & opportunités</h2><p class="mt-1 text-[13px] text-muted">Les actualités, réalisations et opportunités partagées par cette entreprise.</p></div>
                        @if ($isOwner)<a href="{{ route('entreprises.posts.create', $company) }}" class="inline-flex min-h-11 items-center gap-1 text-[13px] font-bold text-link">Nouvelle publication <x-icon name="plus" :size="16"/></a>@endif
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @forelse ($companyPosts as $post)
                            <article class="flex min-w-0 flex-col overflow-hidden rounded-button border border-line">
                                <div class="flex flex-col gap-2 p-4">
                                    <span class="self-start rounded-chip px-2.5 py-1 text-[12px] font-bold {{ $post->type->badgeClasses() }}">{{ $post->displayLabel() }}</span>
                                    <p class="text-[12px] text-muted">{{ $post->created_at->locale('fr')->format('d/m/Y') }}</p>
                                    <p class="line-clamp-5 whitespace-pre-line break-words text-[14px] leading-relaxed">{{ $post->body }}</p>
                                </div>
                                @if ($post->media_kind === 'image')<div class="max-h-52 overflow-hidden"><x-publication-media :publication="$post"/></div>@endif
                                <a href="{{ route('publications.show', $post) }}" class="mt-auto inline-flex min-h-11 items-center gap-1 px-4 py-3 text-[13px] font-extrabold text-link">Voir, commenter ou partager <x-icon name="arrow-right" :size="16"/></a>
                            </article>
                        @empty
                            <p class="text-[14px] text-muted sm:col-span-2">L'entreprise n'a pas encore publié d'actualité, de réalisation ou d'opportunité.</p>
                        @endforelse
                    </div>
                </section>
            @endif
            <section id="activites" class="rounded-card border border-line bg-card p-5 md:p-6">
                <h2 class="text-[21px] font-extrabold">Ce que nous faisons</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="inline-flex min-h-11 items-center gap-2 rounded-button bg-type-knowledge-bg px-4 text-[14px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="19"/>{{ $company->sector }}</span>
                </div>
                <p class="mt-3 text-[13px] text-muted">Les réalisations et opportunités publiées apparaîtront au fil du développement de cette page.</p>
            </section>
        </div>

        <aside class="space-y-4">
            <section id="contact" class="rounded-card border border-line bg-card p-5">
                <h2 class="text-[18px] font-extrabold">Informations professionnelles</h2>
                <dl class="mt-4 space-y-3 text-[14px]">
                    <div><dt class="text-[12px] font-semibold text-muted">Raison sociale</dt><dd class="mt-1 break-words font-bold">{{ $company->legal_name }}</dd></div>
                    <div><dt class="text-[12px] font-semibold text-muted">Lieu</dt><dd class="mt-1 font-bold">{{ $company->city ?: 'Non renseigné' }} · {{ $company->registration_country }}</dd></div>
                    @if ($company->website)<div><dt class="text-[12px] font-semibold text-muted">Site web</dt><dd class="mt-1 break-all"><a href="{{ $company->website }}" rel="noopener noreferrer nofollow" target="_blank" class="font-bold text-link">{{ $company->website }}</a></dd></div>@endif
                    @if ($company->email)<div><dt class="text-[12px] font-semibold text-muted">Contact professionnel</dt><dd class="mt-1 break-all"><a href="mailto:{{ $company->email }}" class="font-bold text-link">{{ $company->email }}</a></dd></div>@endif
                </dl>
            </section>
            <section class="rounded-card border border-line bg-card p-5">
                <div class="flex items-center gap-2"><x-icon name="shield" :size="22"/><h2 class="text-[17px] font-extrabold">Confiance sur GAMAD</h2></div>
                <p class="mt-3 text-[13px] leading-relaxed text-muted">{{ $company->verified() ? 'GAMAD a contrôlé les éléments d’immatriculation de cette entreprise.' : 'Le contrôle des pièces d’immatriculation n’est pas encore terminé.' }} Cette mention ne garantit pas les prestations ni l'identité du gestionnaire de la page.</p>
                <p class="mt-3 text-[12px] text-muted">Les documents administratifs déposés ne sont jamais publiés.</p>
            </section>
        </aside>
    </div>
</div>

</x-layouts.app>
