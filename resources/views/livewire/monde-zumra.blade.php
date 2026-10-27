@php
    $initial = \App\Support\Experience::initial();
    // Module ZUMRA ouvert à cette personne : l'adhésion est active, même si les Projets et les finances restent gelés.
    $moduleOpen = \App\Support\ZumraModule::accessibleTo($viewer);
    $frozen = $initial && ! $moduleOpen;
    // Publier dans la ZUMRA (D-214) : le module est ouvert et la personne y est membre vérifiée ; les types dépendent de son rôle.
    $publishTypes = $moduleOpen && $viewer?->hasVerifiedEmail() && $role
        ? ($manages ? \App\Enums\PublicationType::forZumra() : \App\Enums\PublicationType::forZumraMember())
        : [];
    $meta = collect([$zumra->sphere?->label(), $zumra->domain->name, $zumra->city?->label(), $zumra->format->label()])->filter()->implode(' · ');
    $since = $zumra->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('M Y');
@endphp

<div class="flex flex-col gap-3.5">
    @if ($frozen)<p class="rounded-card border border-line bg-card p-4 text-[14px] text-muted">Espace existant conservé. Les nouvelles créations, invitations et publications collectives sont en pause.</p>@endif
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[26px] pt-3 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <a href="{{ $frozen ? route('espace') : route('zumras.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-white no-underline hover:text-white hover:underline">
            <x-icon name="back" :size="18" />{{ $frozen ? 'Mon espace' : 'Carrefour ZUMRA' }}
        </a>

        <div class="flex items-center gap-3.5">
            <x-zumra-initials :zumra="$zumra" :size="64" class="!bg-white !text-ink" />
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="break-words text-[26px] font-extrabold leading-tight tracking-[-0.4px]">{{ $zumra->name }}</h1>
                <span class="text-[13px] text-[#d6e0e8]">{{ $meta }}</span>
            </div>
        </div>

        @if ($engaged)
            <span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-call-bg px-3 py-1 text-[12px] font-bold text-type-call-fg" data-testid="badge-engagee"><x-icon name="check" :size="14" :stroke="3" />ZUMRA engagée</span>
        @endif
        @if ($zumra->isRecognized())
            <span class="inline-flex items-center gap-1.5 self-start rounded-chip bg-type-knowledge-bg px-3 py-1 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check" :size="14" :stroke="3" />Reconnue par GAMAD</span>
        @else
            <span class="self-start rounded-chip bg-type-call-bg px-3 py-1 text-[12px] font-bold text-type-call-fg">Active · en attente de reconnaissance par GAMAD</span>
        @endif

        <p class="whitespace-pre-line break-words text-[15px] leading-relaxed text-[#eaf0f5] md:max-w-3xl">{{ $zumra->mission }}</p>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        @if ($manages)
            <div class="flex flex-col gap-2 md:flex-row md:self-start">
                <x-button :href="route('zumras.edit', $zumra)" variant="accent" class="md:px-8">Modifier la ZUMRA</x-button>
                @if ($walletsEnabled && ! $initial)
                    <x-button :href="route('zumras.portefeuille', $zumra)" variant="secondary" class="md:px-8">Portefeuille de la ZUMRA</x-button>
                    <x-button :href="route('zumras.contribution', $zumra)" variant="secondary" class="md:px-8">Contribution de la ZUMRA</x-button>
                @endif
            </div>
        @endif
    </header>

    @if ($zumra->isClosed())
        <section aria-label="ZUMRA fermée" role="status" class="flex flex-col gap-2 rounded-card border-2 border-ink bg-card p-4">
            <p class="text-[15px] font-extrabold">Cette ZUMRA est fermée.</p>
            <p class="text-[14px] leading-normal">Elle n’apparaît plus dans le Carrefour ni dans les fils. Rien n’est supprimé.@if ($role === \App\Enums\ZumraRole::Founder && ! $zumra->isSuspended()) Vous pouvez la rouvrir quand vous voulez.@endif</p>
            @if ($role === \App\Enums\ZumraRole::Founder && ! $zumra->isSuspended())
                <x-button type="button" wire:click="reopenZumra" wire:loading.attr="disabled" wire:target="reopenZumra">Rouvrir la ZUMRA</x-button>
            @endif
        </section>
    @endif

    @if ($zumra->isSuspended())
        <section aria-label="ZUMRA suspendue" role="status" class="flex flex-col gap-1 rounded-card border-2 border-danger bg-card p-4">
            <p class="text-[15px] font-extrabold text-danger">Cette ZUMRA est suspendue par la modération.</p>
            <p class="text-[14px] leading-normal">Elle n’apparaît plus dans le Carrefour ni dans les fils ; ses membres la voient, mais l’adhésion, les invitations, les publications et la Discussion sont fermées.@if ($role || auth()->user()?->can('moderate')) Motif : {{ $zumra->suspension_reason }}@endif</p>
        </section>
    @endif

    <section aria-label="La ZUMRA en chiffres" class="-mt-9 grid grid-cols-3 divide-x divide-line rounded-card border border-line bg-card py-3.5 md:mt-0">
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[22px] font-extrabold leading-tight">{{ $zumra->members_count }}</span>
            <span class="text-[12px] text-muted">{{ $zumra->members_count > 1 ? 'membres' : 'membre' }}</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[22px] font-extrabold leading-tight">{{ $projectsCount }}</span>
            <span class="text-[12px] text-muted">{{ $projectsCount > 1 ? 'projets visibles' : 'projet visible' }}</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[18px] font-extrabold capitalize leading-tight">{{ $since }}</span>
            <span class="text-[12px] text-muted">création</span>
        </div>
    </section>

    <section aria-labelledby="gouvernance" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4">
        <h2 id="gouvernance" class="text-[16px] font-extrabold">Responsabilités</h2>
        <ul class="flex flex-col divide-y divide-line-soft">
            @foreach ($governance as $post)
                <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 first:pt-0 last:pb-0">
                    <span class="text-[14px] font-semibold">{{ $post['label'] }}</span>
                    @if ($post['state'] === 'occupied')
                        <span class="text-[14px] font-extrabold">{{ $canSeeMembers ? $post['holder']->name : 'Poste occupé' }}</span>
                    @elseif ($post['state'] === 'pending')
                        <span class="text-[13px] font-semibold text-muted">En attente{{ $canSeeMembers ? ' : '.$post['holder']->name : '' }}</span>
                    @else
                        <span class="text-[13px] text-muted">À pourvoir</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @can('moderate')
        <section aria-labelledby="moderation-zumra" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4">
            <h2 id="moderation-zumra" class="text-[15px] font-extrabold">Modération</h2>
            <p class="text-[13px] text-muted">{{ $zumra->isSuspended() ? 'Rétablir rend la ZUMRA au Carrefour et aux fils.' : 'Suspendre retire la ZUMRA du Carrefour et des fils ; ses membres la gardent, sans pouvoir y agir.' }} L’acte exige un motif et est inscrit au journal d’administration.</p>
            <label for="motif-moderation" class="text-[13px] font-extrabold">Motif{{ $zumra->isSuspended() ? ' (facultatif)' : ' (obligatoire)' }}</label>
            <textarea id="motif-moderation" wire:model="moderationReason" rows="2" maxlength="500" class="w-full rounded-button border border-chip-line bg-card px-3.5 py-3 text-[15px]"></textarea>
            @if ($zumra->isSuspended())
                <x-button type="button" variant="secondary" wire:click="reinstate" wire:loading.attr="disabled" wire:target="reinstate">Rétablir la ZUMRA</x-button>
            @else
                <x-button type="button" wire:click="suspend" wire:loading.attr="disabled" wire:target="suspend">Suspendre la ZUMRA</x-button>
            @endif
        </section>
    @endcan

    @can('super-admin')
        <section aria-labelledby="reconnaissance" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4" x-data="{ confirming: false }">
            <h2 id="reconnaissance" class="text-[15px] font-extrabold">Reconnaissance par GAMAD</h2>
            @if ($zumra->isRecognized())
                <p class="text-[13px] text-muted">Reconnue le {{ $zumra->recognized_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}. Le retrait est inscrit au journal d’administration, avec son motif.</p>
                <div x-show="! confirming"><x-button type="button" variant="secondary" x-on:click="confirming = true">Retirer la reconnaissance</x-button></div>
                <div x-show="confirming" x-cloak class="flex flex-col gap-2">
                    <label for="motif-reconnaissance" class="text-[13px] font-extrabold">Motif du retrait (obligatoire)</label>
                    <textarea id="motif-reconnaissance" wire:model="recognitionReason" rows="2" maxlength="500" class="w-full rounded-button border border-chip-line bg-card px-3.5 py-3 text-[15px]"></textarea>
                    <x-button type="button" wire:click="withdrawRecognition" wire:loading.attr="disabled" wire:target="withdrawRecognition">Retirer la reconnaissance</x-button>
                    <x-button type="button" variant="secondary" x-on:click="confirming = false">Annuler</x-button>
                </div>
            @else
                <p class="text-[13px] text-muted">Reconnaître cette ZUMRA lui donne le badge « Reconnue par GAMAD ». L’acte est inscrit au journal d’administration.</p>
                <x-button type="button" wire:click="recognize" wire:loading.attr="disabled" wire:target="recognize">Reconnaître cette ZUMRA</x-button>
            @endif
        </section>
    @endcan

    @if ($errors->any())
        <div role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    @if ($myInvitation)
        <section aria-labelledby="invitation" class="flex flex-col gap-2.5 rounded-card border-2 border-ink bg-card p-4">
            <h2 id="invitation" class="text-[16px] font-extrabold">Vous êtes invité à rejoindre cette ZUMRA</h2>
            <p class="text-[14px] text-muted">{{ $myInvitation->inviter ? $myInvitation->inviter->name.' vous invite' : 'Vous êtes invité' }}. L’invitation est valable jusqu’au {{ $myInvitation->expires_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}.</p>
            @if (filled($myInvitation->message))
                <p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px] leading-normal">{{ $myInvitation->message }}</p>
            @endif
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button type="button" wire:click="acceptInvitation('{{ $myInvitation->id }}')" wire:loading.attr="disabled" wire:target="acceptInvitation, refuseInvitation" class="sm:flex-1">Accepter</x-button>
                <x-button type="button" variant="secondary" wire:click="refuseInvitation('{{ $myInvitation->id }}')" wire:loading.attr="disabled" wire:target="acceptInvitation, refuseInvitation" class="sm:flex-1">Refuser</x-button>
            </div>
        </section>
    @elseif (! $role && (! $frozen || $myRequest?->state === \App\Enums\JoinRequestState::Pending))
        <section id="rejoindre" aria-label="Rejoindre cette ZUMRA" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4"
                 x-data="{ open: false }" x-init="open = location.hash === '#rejoindre'">
            @guest
                <p class="text-[14px] text-muted">Connectez-vous pour {{ $zumra->joinsFreely() ? 'rejoindre' : 'demander à rejoindre' }} cette ZUMRA.</p>
                <x-button :href="route('login')" variant="accent">{{ $zumra->joinsFreely() ? 'Rejoindre la ZUMRA' : 'Demander à rejoindre' }}</x-button>
            @else
                @if (! $viewer->hasVerifiedEmail())
                    <p class="text-[14px] text-muted">Vérifiez votre adresse e-mail pour {{ $zumra->joinsFreely() ? 'rejoindre' : 'demander à rejoindre' }} une ZUMRA.</p>
                @elseif ($myRequest?->state === \App\Enums\JoinRequestState::Pending)
                    <p class="text-[15px] font-extrabold">Demande envoyée</p>
                    <p class="text-[14px] text-muted">Le fondateur ou un responsable y répondra.</p>
                    @if (filled($myRequest->message))
                        <p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px] leading-normal">{{ $myRequest->message }}</p>
                    @endif
                    <x-button type="button" variant="secondary" wire:click="withdrawRequest" wire:loading.attr="disabled" wire:target="withdrawRequest">Retirer ma demande</x-button>
                @elseif (! $zumra->joinsFreely() && $myRequest?->state === \App\Enums\JoinRequestState::Declined)
                    <p class="text-[14px] font-semibold text-muted">Votre demande n’a pas été retenue.</p>
                @elseif ($zumra->joinsFreely())
                    <p class="text-[14px] text-muted">Adhésion libre : vous entrez tout de suite et pouvez quitter quand vous voulez.</p>
                    <x-button type="button" variant="accent" class="w-full" wire:click="join" wire:loading.attr="disabled" wire:target="join">Rejoindre la ZUMRA</x-button>
                @else
                    <div x-show="! open">
                        <x-button type="button" variant="accent" class="w-full" x-on:click="open = true">Demander à rejoindre</x-button>
                    </div>
                    <form wire:submit="requestJoin" x-show="open" x-cloak class="flex flex-col gap-2.5" x-data="{ n: 0 }">
                        <label for="mot-adhesion" class="text-[14px] font-extrabold">Un mot pour le fondateur (facultatif)</label>
                        <textarea id="mot-adhesion" wire:model="joinWord" rows="3" maxlength="{{ \App\Models\JoinRequest::MESSAGE_MAX }}" x-on:input="n = $el.value.length"
                                  placeholder="Pourquoi voulez-vous rejoindre cette ZUMRA ?"
                                  @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('joinWord') ? 'border-danger' : 'border-chip-line'])></textarea>
                        <span class="self-end text-[12px] text-muted"><span x-text="n"></span> / {{ \App\Models\JoinRequest::MESSAGE_MAX }}</span>
                        <x-button wire:loading.attr="disabled" wire:target="requestJoin">Envoyer ma demande</x-button>
                    </form>
                @endif
            @endguest
        </section>
    @endif

    <x-contribution-entry kind="zumra" :model="$zumra" :viewer="$viewer" />

    <nav aria-label="Onglets de la ZUMRA" class="-mx-4 flex overflow-x-auto border-b border-line bg-card px-2 md:mx-0 md:rounded-card md:border">
        @foreach (['formation' => 'Formation', 'fil' => 'Fil'] + (\App\Support\ProjectsModule::accessibleTo($viewer) ? ['projets' => 'Projets'] : []) + ['membres' => 'Membres'] + ($canDiscuss ? ['discussion' => 'Discussion'] : []) as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" @if ($tab === $key) aria-current="page" @endif
                    @class(['flex min-h-12 min-w-11 shrink-0 items-center justify-center border-b-[3px] px-3 text-[14px]', 'border-accent font-extrabold text-ink' => $tab === $key, 'border-transparent font-semibold text-muted' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </nav>

    <div wire:loading.delay wire:target="setTab, more" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    @if (in_array($tab, ['formation', 'fil']))
        @if ($tab === 'formation')
            <section class="flex flex-col gap-1 rounded-card bg-type-call-bg p-4 text-type-call-fg" aria-label="Apprendre ensemble">
                <span class="text-[12px] font-extrabold tracking-[1.5px]">APPRENDRE ENSEMBLE</span>
                <p class="text-[17px] font-extrabold leading-snug">Ici, on apprend avant de construire.</p>
                @unless ($role || $frozen)
                    <p class="text-[14px] font-semibold leading-snug">Rejoignez la ZUMRA pour suivre ses formations, ou partagez ce que vous savez.</p>
                @endunless
            </section>

            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[18px] font-extrabold">Savoirs partagés</h2>
                @if (in_array(\App\Enums\PublicationType::Knowledge, $publishTypes, true))
                    <a href="{{ route('zumras.publier', [$zumra, 'savoir']) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Partager un savoir</a>
                @endif
            </div>
        @else
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[18px] font-extrabold">Fil de la ZUMRA</h2>
            </div>
            @if ($publishTypes !== [])
                <section aria-label="Publier dans la ZUMRA" class="flex flex-col gap-2 rounded-card border border-line bg-card p-3.5">
                    <span class="text-[13px] font-bold text-muted">{{ $manages ? 'Publier au nom de la ZUMRA' : 'Publier dans la ZUMRA' }}</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($publishTypes as $kind)
                            <a href="{{ route('zumras.publier', [$zumra, $kind->slug()]) }}" class="inline-flex min-h-11 items-center rounded-chip px-3 text-[13px] font-bold no-underline hover:underline {{ $kind->badgeClasses() }}">{{ $kind->label() }}</a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        {{-- Ordinateur : la Formation met ses savoirs à gauche et les capacités à droite ; le Fil range ses cartes sur deux colonnes. --}}
        <div @class(['flex flex-col gap-3.5', 'lg:grid lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-6' => $tab === 'formation', 'lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-6' => $tab === 'fil'])>
        <div @class(['flex flex-col gap-3.5' => $tab === 'formation', 'contents' => $tab === 'fil'])>
        @forelse ($publications as $post)
            <x-publication-card wire:key="zumra-publication-{{ $post->id }}" :publication="$post" :viewer="$viewer" :gesture="$gestures[$post->id] ?? null" :supported="$supported" :managed="$managed" :member-of="$memberOf" />
        @empty
            <section @class(['rounded-card border border-line bg-card p-5', 'lg:col-span-2' => $tab === 'fil'])>
                <p class="text-[16px] font-extrabold">{{ $tab === 'formation' ? 'Aucun savoir partagé pour l’instant.' : 'Rien dans le Fil de cette ZUMRA pour l’instant.' }}</p>
                <p class="mt-1 text-muted">{{ $manages && $publishTypes !== [] ? 'Publiez au nom de la ZUMRA : un savoir à partager, un besoin, une offre d’aide ou un appel à rejoindre.' : ($publishTypes !== [] ? 'Partagez un savoir ou une offre d’aide avec la ZUMRA.' : 'Les publications de la ZUMRA apparaîtront ici.') }}</p>
            </section>
        @endforelse

        @if ($hasMore)
            <x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled" wire:target="more" @class(['lg:col-span-2' => $tab === 'fil'])>Voir plus de publications</x-button>
        @endif
        </div>

        @if ($tab === 'formation')
            <section aria-labelledby="capacites" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <h2 id="capacites" class="text-[17px] font-extrabold">Ce que nos membres savent faire</h2>
                @if ($skills['offered']->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($skills['offered'] as $skill)
                            <li class="rounded-chip border border-line bg-ground px-3 py-1.5 text-[13px] font-semibold">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-[14px] text-muted">Aucune capacité indiquée pour l’instant.</p>
                @endif

                <h2 class="text-[17px] font-extrabold">Ce que des personnes veulent apprendre ici</h2>
                @if ($skills['wanted']->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($skills['wanted'] as $skill)
                            <li class="rounded-chip bg-type-learning-bg px-3 py-1.5 text-[13px] font-bold text-type-learning-fg">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-[14px] text-muted">Aucun souhait indiqué pour l’instant.</p>
                @endif

                @if ($manages && ! $initial)
                    <a href="{{ route('zumras.invite', $zumra) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Inviter des personnes selon leurs capacités</a>
                @endif
            </section>
        @endif
        </div>

        <livewire:gesture-sheet />
    @endif

    @if ($tab === 'projets')
        @if ($hostingRequests->isNotEmpty())
            <section aria-labelledby="demandes-accueil" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-card p-4">
                <h2 id="demandes-accueil" class="text-[17px] font-extrabold">Demandes d’accueil de projets</h2>
                <p class="text-[13px] text-muted">Vous voyez seulement ce qui sert à décider. Accueillir un projet ne vous donne ni sa direction, ni ses documents, ni ses ressources.</p>
                @foreach ($hostingRequests as $request)
                    <div wire:key="accueil-{{ $request->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                        <span class="break-words text-[16px] font-extrabold">{{ $request->name }}</span>
                        <span class="text-[12px] text-muted">{{ $request->domain?->name }} · Porteur : {{ $request->owner?->name ?? 'une personne' }}</span>
                        <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ $request->goal }}</p>
                        @if ($request->hosting_message)<p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px]">« {{ $request->hosting_message }} »</p>@endif
                        <div class="grid grid-cols-2 gap-2">
                            <x-button type="button" wire:click="acceptHosting('{{ $request->id }}')" wire:loading.attr="disabled" wire:target="acceptHosting, declineHosting" class="!px-4 !text-[14px]">Accueillir</x-button>
                            <x-button type="button" variant="secondary" wire:click="declineHosting('{{ $request->id }}')" wire:loading.attr="disabled" wire:target="acceptHosting, declineHosting" class="!px-4 !text-[14px]">Refuser</x-button>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        <section aria-labelledby="projets" class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-2">
                <h2 id="projets" class="text-[17px] font-extrabold">Projets de la ZUMRA</h2>
                @if ($viewer)
                    <a href="{{ route('projets.create', $zumra) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Proposer mon projet</a>
                @endif
            </div>

            <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projects as $project)
                <a wire:key="projet-{{ $project->id }}" href="{{ route('projets.show', $project) }}" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 text-ink no-underline hover:text-ink">
                    <span class="self-start rounded-chip bg-type-call-bg px-2.5 py-1 text-[12px] font-bold text-type-call-fg">{{ $project->stage->label() }}</span>
                    <span class="break-words text-[16px] font-extrabold">{{ $project->name }}</span>
                    <span class="whitespace-pre-line break-words text-[14px] leading-normal">{{ \Illuminate\Support\Str::limit($project->isShowcasePublished() ? $project->showcase_summary : 'Votre projet (privé).', 160) }}</span>
                </a>
            @empty
                <section class="rounded-card border border-line bg-card p-5 md:col-span-full">
                    <p class="text-[16px] font-extrabold">Aucun projet pour l’instant.</p>
                    <p class="mt-1 text-muted">Les projets accueillis par la ZUMRA apparaissent ici lorsque leur porteur publie une présentation.</p>
                </section>
            @endforelse
            </div>
        </section>
    @endif

    @if ($tab === 'discussion')
        <section aria-labelledby="discussion" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" wire:poll.15s>
            <h2 id="discussion" class="text-[17px] font-extrabold">Discussion des membres</h2>
            <p class="text-[12px] leading-normal text-muted">Seuls les membres de la ZUMRA lisent cette discussion. Le texte reste un texte simple : les adresses web n’y sont pas cliquables.</p>

            @forelse ($discussion as $post)
                @php
                    $mine = $post->user_id === $viewer->getKey();
                    $time = $post->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j M, H:i');
                @endphp
                <div wire:key="discussion-{{ $post->id }}" class="flex flex-col gap-1 border-t border-line-soft pt-3 first:border-0 first:pt-0" x-data="{ confirming: false }">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="break-words text-[14px] font-extrabold">{{ $post->author->name }}</span>
                        <span class="shrink-0 text-[11px] text-muted">{{ $time }}</span>
                    </div>
                    @if ($post->hidden_at)
                        <p class="rounded-button bg-ground p-3 text-[14px] italic text-muted">Message masqué par la modération.</p>
                    @else
                        <p class="whitespace-pre-line break-words text-[15px] leading-normal">{{ $post->body }}</p>
                    @endif
                    @if (! $mine && ! $post->hidden_at)
                        <a href="{{ route('zumras.discussion.report', [$zumra, $post]) }}" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline">Signaler</a>
                    @endif
                    @if (($mine || $manages) && ! $post->hidden_at)
                        <div>
                            <button type="button" x-show="! confirming" x-on:click="confirming = true" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-muted underline">Supprimer</button>
                            <span x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                                <span class="text-[13px] font-semibold">Supprimer ce message ?</span>
                                <x-button type="button" wire:click="deletePost('{{ $post->id }}')" wire:loading.attr="disabled" wire:target="deletePost" class="!px-4 !text-[14px]">Oui</x-button>
                                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
                            </span>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-[14px] text-muted">Aucun message pour l’instant. Écrivez le premier.</p>
            @endforelse

            <form wire:submit="sendPost" class="flex flex-col gap-2 border-t border-line pt-3" x-data="{ n: 0 }">
                <label for="message-discussion" class="text-[13px] font-extrabold">Votre message</label>
                <textarea id="message-discussion" wire:model="postBody" rows="3" maxlength="{{ \App\Models\ZumraPost::BODY_MAX }}" x-on:input="n = $el.value.length"
                          @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('postBody') ? 'border-danger' : 'border-chip-line'])
                          @if ($errors->has('postBody')) aria-invalid="true" aria-describedby="discussion-erreur" @endif></textarea>
                <div class="flex justify-between gap-2">
                    @error('postBody')
                        <p id="discussion-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                    @else
                        <span></span>
                    @enderror
                    <span class="text-[12px] text-muted"><span x-text="n"></span> / {{ \App\Models\ZumraPost::BODY_MAX }}</span>
                </div>
                <x-button wire:loading.attr="disabled" wire:target="sendPost">Envoyer</x-button>
            </form>
        </section>
    @endif

    @if ($tab === 'membres')
    <section aria-labelledby="membres" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="membres" class="text-[17px] font-extrabold">Membres</h2>

        @if ($manages)
            <div class="flex flex-col gap-3 rounded-button border border-line p-3">
                <h3 class="text-[14px] font-extrabold">Demandes d’adhésion</h3>
                @forelse ($requests as $request)
                    @php
                        $applicant = $request->user;
                        $applicantProfile = $applicant->profile;
                        $applicantProfile?->setRelation('user', $applicant);
                        $canSeeApplicant = $applicantProfile && Gate::forUser($viewer)->allows('view', $applicantProfile);
                    @endphp
                    <div wire:key="demande-{{ $request->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                        <div class="flex items-center gap-3">
                            @if ($canSeeApplicant)
                                <x-avatar :profile="$applicantProfile" :size="44" />
                                <a href="{{ route('personnes.show', $applicantProfile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $applicant->name }}</a>
                            @else
                                <span class="break-words text-[15px] font-extrabold">{{ $applicant->name }}</span>
                            @endif
                        </div>
                        @if ($canSeeApplicant && $applicantProfile->offeredSkills->isNotEmpty())
                            <ul class="flex flex-wrap gap-1.5" aria-label="Ce que cette personne peut apporter">
                                @foreach ($applicantProfile->offeredSkills->take(3) as $skill)
                                    <li class="rounded-chip bg-type-knowledge-bg px-2.5 py-1 text-[12px] font-bold text-type-knowledge-fg">{{ $skill->name }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (filled($request->message))
                            <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ $request->message }}</p>
                        @endif
                        <div class="flex gap-2">
                            <x-button type="button" wire:click="acceptRequest('{{ $request->id }}')" wire:loading.attr="disabled" wire:target="acceptRequest, declineRequest" class="flex-1 !text-[14px]">Accepter</x-button>
                            <x-button type="button" variant="secondary" wire:click="declineRequest('{{ $request->id }}')" wire:loading.attr="disabled" wire:target="acceptRequest, declineRequest" class="flex-1 !text-[14px]">Décliner</x-button>
                        </div>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">Aucune demande en attente.</p>
                @endforelse
            </div>

            <div class="flex flex-col gap-3 rounded-button border border-line p-3">
                <h3 class="text-[14px] font-extrabold">Invitations en attente</h3>
                @forelse ($invitations as $invitation)
                    <div wire:key="invitation-{{ $invitation->id }}" class="flex items-center gap-3 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="break-words text-[14px] font-extrabold">{{ $invitation->user->name }}</span>
                            <span class="text-[12px] text-muted">jusqu’au {{ $invitation->expires_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F') }}</span>
                        </div>
                        <x-button type="button" variant="secondary" wire:click="cancelInvitation('{{ $invitation->id }}')" wire:loading.attr="disabled" wire:target="cancelInvitation" class="shrink-0 !px-4 !text-[14px]">Annuler</x-button>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">Aucune invitation en attente.</p>
                @endforelse
                @unless ($initial)<x-button :href="route('zumras.invite', $zumra)" variant="secondary">Inviter des personnes selon leurs capacités</x-button>@endunless
            </div>
        @endif

        @if ($canSeeMembers)
            <ul class="flex flex-col divide-y divide-line-soft md:grid md:grid-cols-2 md:gap-x-8 md:divide-y-0">
                @foreach ($members as $membership)
                    @php
                        $person = $membership->user;
                        $profile = $person->profile;
                        $profile?->setRelation('user', $person);
                        $canSee = $profile && Gate::forUser($viewer)->allows('view', $profile);
                    @endphp
                    <li wire:key="membre-{{ $membership->id }}" class="flex flex-col gap-2 py-2.5 first:pt-0 last:pb-0 md:border-b md:border-line-soft md:first:pt-2.5 md:last:pb-2.5" x-data="{ confirming: false }">
                        <div class="flex items-center gap-3">
                        @if ($canSee)
                            <x-avatar :profile="$profile" :size="44" />
                        @else
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-line-soft text-[15px] font-extrabold text-muted" aria-hidden="true">{{ $person->initials() }}</span>
                        @endif
                        <div class="flex min-w-0 flex-1 flex-col">
                            @if ($canSee)
                                <a href="{{ route('personnes.show', $profile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[15px] font-extrabold text-ink no-underline hover:text-ink hover:underline">{{ $person->name }}</a>
                            @else
                                <span class="break-words text-[15px] font-extrabold">{{ $person->name }}</span>
                            @endif
                            @if ($canSee && $profile->city)
                                <span class="-mt-2 text-[12px] text-muted">{{ $profile->city->label() }}</span>
                            @endif
                        </div>
                        @if ($membership->role !== \App\Enums\ZumraRole::Member)
                            <span class="rounded-chip bg-type-call-bg px-2.5 py-1 text-[12px] font-bold text-type-call-fg">{{ $membership->role->label() }}</span>
                        @endif
                        </div>
                    @if ($manages && $membership->role !== \App\Enums\ZumraRole::Founder && $membership->user_id !== $viewer->getKey())
                        @php
                            $mayRemove = $membership->role === \App\Enums\ZumraRole::Member || $role === \App\Enums\ZumraRole::Founder;
                            $isFounder = $role === \App\Enums\ZumraRole::Founder;
                        @endphp
                        <div class="flex flex-wrap gap-2">
                            @if ($isFounder && $membership->role === \App\Enums\ZumraRole::Manager)
                                <div class="flex w-full flex-col gap-1">
                                    <label for="poste-{{ $membership->id }}" class="text-[12px] font-bold text-muted">Proposer un poste de gouvernance @if ($membership->dutyPending())<span class="font-semibold">(en attente d’acceptation)</span>@endif</label>
                                    <select id="poste-{{ $membership->id }}" wire:change="assignDuty('{{ $membership->id }}', $event.target.value)" class="min-h-11 w-full rounded-button border border-chip-line bg-card px-3 text-[14px]">
                                        <option value="">{{ $membership->duty ? 'Retirer '.($membership->dutyPending() ? 'la proposition' : 'le poste') : 'Aucun poste précis' }}</option>
                                        @foreach (\App\Enums\ZumraDuty::cases() as $duty)
                                            <option value="{{ $duty->value }}" @selected($membership->duty === $duty)>{{ $duty->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if ($isFounder)
                                @if ($membership->role === \App\Enums\ZumraRole::Member)
                                    <x-button type="button" variant="secondary" wire:click="promote('{{ $membership->id }}')" wire:loading.attr="disabled" wire:target="promote, demote, removeMember" class="!px-4 !text-[14px]">Nommer responsable</x-button>
                                @else
                                    <x-button type="button" variant="secondary" wire:click="demote('{{ $membership->id }}')" wire:loading.attr="disabled" wire:target="promote, demote, removeMember" class="!px-4 !text-[14px]">Retirer le rôle de responsable</x-button>
                                @endif
                            @endif
                            @if ($mayRemove)
                                <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true" class="!px-4 !text-[14px]">Retirer de la ZUMRA</x-button>
                                <span x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                                    <span class="text-[13px] font-semibold">Retirer {{ $person->name }} ?</span>
                                    <x-button type="button" wire:click="removeMember('{{ $membership->id }}')" wire:loading.attr="disabled" wire:target="removeMember" class="!px-4 !text-[14px]">Oui, retirer</x-button>
                                    <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Annuler</x-button>
                                </span>
                            @endif
                        </div>
                    @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-[14px] text-muted">Les membres d’une ZUMRA sont visibles des personnes inscrites.</p>
            @guest
                <div class="flex flex-col gap-2 sm:flex-row">
                    <x-button :href="route('login')" variant="secondary" class="sm:flex-1">Se connecter</x-button>
                    <x-button :href="route('register')" class="sm:flex-1">Créer un compte</x-button>
                </div>
            @endguest
        @endif
    </section>
    @endif

    @if ($role === \App\Enums\ZumraRole::Founder && ! $zumra->isUnavailable())
        <section aria-labelledby="fondation" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4" x-data="{ confirming: null }">
            <h2 id="fondation" class="text-[16px] font-extrabold">Passer la main</h2>
            @if ($foundationCandidates->isNotEmpty())
                <p class="text-[14px] leading-normal text-muted">Le fondateur ne part pas sans passer la main. Confiez la fondation à un membre : vous restez responsable, et pourrez ensuite quitter la ZUMRA comme tout responsable.</p>
                @foreach ($foundationCandidates as $candidate)
                    <div wire:key="fondation-{{ $candidate->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                        <span class="break-words text-[15px] font-extrabold">{{ $candidate->user->name }}<span class="ml-2 text-[12px] font-semibold text-muted">{{ $candidate->role === \App\Enums\ZumraRole::Manager ? 'Responsable' : 'Membre' }}</span></span>
                        <div x-show="confirming !== '{{ $candidate->id }}'"><x-button type="button" variant="secondary" x-on:click="confirming = '{{ $candidate->id }}'">Confier la fondation</x-button></div>
                        <div x-show="confirming === '{{ $candidate->id }}'" x-cloak class="flex flex-col gap-2">
                            <p class="text-[14px] font-semibold">Confier la fondation à {{ $candidate->user->name }} ? Elle devient fondatrice ou fondateur et peut vous retirer vos responsabilités.</p>
                            <x-button type="button" wire:click="transferFoundation('{{ $candidate->id }}')" wire:loading.attr="disabled" wire:target="transferFoundation">Oui, confier la fondation</x-button>
                            <x-button type="button" variant="secondary" x-on:click="confirming = null">Annuler</x-button>
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-[14px] leading-normal text-muted">Vous êtes le seul membre. Vous pouvez fermer la ZUMRA : elle disparaît du Carrefour et des fils, rien n’est supprimé, et vous pouvez la rouvrir.</p>
                <div x-show="confirming !== 'close'"><x-button type="button" variant="secondary" x-on:click="confirming = 'close'">Fermer la ZUMRA</x-button></div>
                <div x-show="confirming === 'close'" x-cloak class="flex flex-col gap-2">
                    <p class="text-[14px] font-semibold">Fermer cette ZUMRA ?</p>
                    <x-button type="button" wire:click="closeZumra" wire:loading.attr="disabled" wire:target="closeZumra">Oui, fermer la ZUMRA</x-button>
                    <x-button type="button" variant="secondary" x-on:click="confirming = null">Annuler</x-button>
                </div>
            @endif
        </section>
    @endif

    @if ($role && $role !== \App\Enums\ZumraRole::Founder)
        <section aria-label="Quitter la ZUMRA" class="rounded-card border border-line bg-card p-4" x-data="{ confirming: false }">
            <div x-show="! confirming">
                <x-button type="button" variant="secondary" x-on:click="confirming = true">Quitter la ZUMRA</x-button>
            </div>
            <div x-show="confirming" x-cloak class="flex flex-col gap-2">
                <p class="text-[14px] font-semibold">Quitter cette ZUMRA ? Vous pourrez demander à la rejoindre de nouveau.</p>
                <x-button type="button" wire:click="leave" wire:loading.attr="disabled" wire:target="leave">Oui, quitter</x-button>
                <x-button type="button" variant="secondary" x-on:click="confirming = false">Annuler</x-button>
            </div>
        </section>
    @endif

    @if ($viewer && $moduleOpen && ! $manages && ! $zumra->isSuspended() && $viewer->hasVerifiedEmail())
        <a href="{{ route('zumras.report', $zumra) }}" class="inline-flex min-h-11 items-center self-start text-[13px] font-semibold text-muted underline">Signaler cette ZUMRA</a>
    @endif
</div>
