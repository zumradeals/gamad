<div class="flex min-w-0 flex-col gap-6">
    <section class="relative overflow-hidden rounded-[24px] bg-ink px-5 py-9 text-white md:px-10 md:py-12">
        <div class="pointer-events-none absolute -right-12 -top-20 size-72 rounded-full border-[36px] border-accent/20" aria-hidden="true"></div>
        <div class="pointer-events-none absolute bottom-0 right-12 size-28 rounded-full bg-white/5" aria-hidden="true"></div>
        <div class="relative max-w-2xl space-y-4">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-[12px] font-bold tracking-wide text-white/90"><x-icon name="project" :size="16" /> ENTREPRISES SUR GAMAD</span>
            <h1 class="text-[34px] font-extrabold leading-tight tracking-tight md:text-[46px]">Des entreprises à découvrir.<br><span class="text-accent">Des savoir-faire à rencontrer.</span></h1>
            <p class="max-w-xl text-[15px] leading-relaxed text-white/85 md:text-[17px]">Découvrez des organisations dont l'immatriculation a été contrôlée par GAMAD, leurs activités et leurs coordonnées professionnelles.</p>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_270px]">
        <div class="min-w-0 space-y-5">
            <section class="rounded-card border border-line bg-card p-4 md:p-5">
                <label for="company-search" class="mb-2 block text-[14px] font-bold">Rechercher une entreprise</label>
                <div class="flex min-h-12 items-center gap-2 rounded-button border border-chip-line px-3 focus-within:outline focus-within:outline-[3px] focus-within:outline-link">
                    <x-icon name="search" :size="20" class="shrink-0 text-muted" />
                    <input id="company-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="Nom, activité, ville…" class="min-w-0 flex-1 border-0 bg-transparent text-[14px] outline-none">
                </div>
                <p class="mt-2 text-[12px] text-muted">Seules les pages entreprises administrativement vérifiées figurent dans cet annuaire.</p>
            </section>

            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[21px] font-extrabold">Explorer les entreprises</h2>
                <span class="text-[12px] font-semibold text-muted">Ordre alphabétique</span>
            </div>
            <div class="grid gap-4 sm:grid-cols-2" wire:loading.class="opacity-60">
                @forelse ($companies as $company)
                    <article wire:key="company-{{ $company->id }}" class="group flex flex-col overflow-hidden rounded-card border border-line bg-card">
                        <div class="relative h-[84px] overflow-hidden bg-ink" aria-hidden="true">
                            @if ($company->banner_path)
                                <img src="{{ route('entreprises.visual', [$company, 'banner']) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover">
                            @else
                            <span class="absolute -right-6 -top-10 size-40 rounded-full border-[20px] border-accent/25"></span>
                            <span class="absolute bottom-0 left-3 h-8 w-24 rounded-t-full bg-white/10"></span>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col gap-3 px-4 pb-5">
                            <span class="-mt-7 flex size-14 items-center justify-center rounded-[16px] border-[4px] border-card bg-soft-blue text-[19px] font-extrabold text-ink" aria-label="Logo de {{ $company->name }}">
                                @if ($company->logo_path)
                                    <img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="" loading="lazy" class="size-full rounded-[12px] bg-white object-contain">
                                @else
                                    {{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}
                                @endif
                            </span>
                            <div class="space-y-1">
                                <h3 class="break-words text-[18px] font-extrabold"><a href="{{ route('entreprises.show', $company) }}" class="text-ink no-underline group-hover:underline">{{ $company->name }}</a></h3>
                                <p class="text-[13px] font-semibold text-muted">{{ $company->sector }} @if ($company->city) · {{ $company->city }} @endif</p>
                                <span class="inline-flex items-center gap-1.5 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="15"/> Immatriculation vérifiée</span>
                            </div>
                            @if ($company->summary)<p class="line-clamp-3 break-words text-[14px] leading-relaxed text-muted">{{ $company->summary }}</p>@endif
                            <a href="{{ route('entreprises.show', $company) }}" class="mt-auto inline-flex min-h-11 items-center gap-2 text-[13px] font-extrabold text-link">Découvrir l'entreprise <x-icon name="arrow-right" :size="16"/></a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-card border border-line bg-card p-6 sm:col-span-2">
                        <h3 class="text-[18px] font-extrabold">Aucune entreprise à afficher pour l'instant.</h3>
                        <p class="mt-2 text-[14px] text-muted">Les pages vérifiées apparaîtront ici. Aucun exemple fictif n'est présenté.</p>
                    </div>
                @endforelse
            </div>
            @if ($companies->hasPages())<div>{{ $companies->links() }}</div>@endif
        </div>

        <aside aria-label="Explorer les entreprises" class="min-w-0 space-y-4">
            <section class="rounded-card border border-line bg-card p-5">
                <h2 class="text-[18px] font-extrabold">Explorer les entreprises</h2>
                <p class="mt-2 text-[13px] leading-relaxed text-muted">Recherchez une organisation par nom, activité ou ville dans le champ de recherche.</p>
                <a href="#company-search" class="mt-3 inline-flex min-h-11 items-center gap-2 text-[14px] font-bold text-link"><x-icon name="search" :size="18"/> Rechercher une entreprise</a>
                <p class="mt-2 text-[12px] text-muted">Les résultats sont présentés par ordre alphabétique.</p>
            </section>
            <section class="rounded-card border border-line bg-card p-5">
                <span class="flex size-12 items-center justify-center rounded-button bg-type-knowledge-bg text-type-knowledge-fg"><x-icon name="shield" :size="25"/></span>
                <h2 class="mt-3 text-[18px] font-extrabold">Une identité administrative plus claire</h2>
                <p class="mt-2 text-[14px] leading-relaxed text-muted">GAMAD contrôle l'immatriculation de l'organisation avant d'afficher sa page publiquement. Cette vérification ne certifie ni les prestations ni l'identité de son gestionnaire.</p>
            </section>
            <section class="rounded-card border border-line bg-card p-5">
                <h2 class="text-[18px] font-extrabold">Votre entreprise est ici aussi chez elle.</h2>
                <p class="mt-2 text-[14px] leading-relaxed text-muted">Présentez votre activité et préparez votre présence sur GAMAD. Votre page restera privée pendant le contrôle administratif.</p>
                <x-button :href="auth()->check() ? route('entreprises.create') : route('register')" variant="accent" class="mt-4">{{ auth()->check() ? 'Créer une page' : 'Créer un compte' }}</x-button>
            </section>
        </aside>
    </div>
</div>
