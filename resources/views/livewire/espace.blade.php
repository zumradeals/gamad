@php
    $sections = [
        ['vue-ensemble', 'Vue d’ensemble', 'grid'],
        ['profil', 'Mon profil', 'user'],
        ['publications', 'Mes publications', 'document'],
        ['echanges', 'Mes échanges', 'chat'],
        ['compte', 'Mon compte', 'settings'],
    ];
@endphp

<div id="vue-ensemble" class="space-layout" x-data="{ section: window.location.hash || '#vue-ensemble' }" x-on:hashchange.window="section = window.location.hash || '#vue-ensemble'">
    <aside aria-label="Mon espace personnel" class="space-sidebar">
        <div class="space-panel overflow-hidden">
            <div class="space-cover" aria-hidden="true"></div>
            <div class="px-4 pb-4">
                <div class="relative -mt-8 mb-3 inline-flex rounded-full border-4 border-card">
                    <x-avatar :profile="$profile" :size="64" />
                </div>
                <p class="break-words text-[16px] font-extrabold">{{ $user->name }}</p>
                <p class="mt-0.5 text-[12px] text-muted">Mon espace personnel</p>
                <nav aria-label="Sections de Mon espace" class="space-menu mt-4 flex flex-col gap-1 border-t border-line-soft pt-3">
                    @foreach ($sections as [$id, $label, $icon])
                        <a href="#{{ $id }}" @if ($loop->first) aria-current="location" @endif :aria-current="section === '#{{ $id }}' ? 'location' : null" class="space-menu-link">
                            <x-icon :name="$icon" :size="20" /><span>{{ $label }}</span>
                        </a>
                    @endforeach
                    @can('moderate')
                        <a href="#moderation" class="space-menu-link"><x-icon name="shield" :size="20" />Modération</a>
                    @endcan
                    @can('super-admin')
                        <a href="#administration" class="space-menu-link"><x-icon name="settings" :size="20" />Administration</a>
                    @endcan
                </nav>
                <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-line-soft pt-3">
                    @csrf
                    <button type="submit" class="space-menu-link w-full"><x-icon name="logout" :size="20" />Se déconnecter</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="space-content">
        <x-profile-next-step :user="$user" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-[26px] font-extrabold leading-tight tracking-[-0.5px] md:text-[28px]">Mon espace</h1>
                <p class="mt-1 text-[14px] text-muted">Votre profil, vos actions et votre compte.</p>
            </div>
            <x-button :href="route('personnes.show', $profile->public_id)" variant="secondary" class="!px-3.5 !text-[13px]"><x-icon name="external" :size="18" class="mr-2" />Voir mon profil</x-button>
        </div>

        <nav aria-label="Sections de Mon espace sur mobile" class="space-menu -mx-1 flex gap-1 overflow-x-auto px-1 py-1 lg:hidden">
            @foreach ($sections as [$id, $label, $icon])
                <a href="#{{ $id }}" @if ($loop->first) aria-current="location" @endif :aria-current="section === '#{{ $id }}' ? 'location' : null" class="space-menu-link shrink-0 whitespace-nowrap !text-[12px]">{{ $label }}</a>
            @endforeach
        </nav>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        <section aria-labelledby="afaire" wire:poll.30s @class(['space-panel flex flex-col gap-4 border-l-[3px] p-4 md:p-5', 'border-l-type-knowledge-fg' => count($todo) === 0, 'border-l-accent' => count($todo) > 0])>
            <div class="flex items-center gap-2.5">
                <x-icon name="tasks" :size="23" />
                <h2 id="afaire" class="text-[18px] font-extrabold">À faire maintenant</h2>
                @if (count($todo) > 0)
                    <span class="ml-auto min-w-6 rounded-full bg-accent px-2 text-center text-[13px] font-extrabold leading-6 text-ink" aria-label="{{ count($todo) }} à faire">{{ count($todo) }}</span>
                @endif
            </div>
            @forelse ($todo as $line)
                <div wire:key="{{ $line['key'] }}" class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3">
                    <div class="flex min-w-0 flex-1 basis-44 flex-col">
                        <span class="break-words text-[15px] font-extrabold">{{ $line['title'] }}</span>
                        <span class="break-words text-[13px] text-muted">{{ $line['detail'] }}</span>
                    </div>
                    <x-button :href="$line['url']" variant="secondary" class="shrink-0 !px-4 !text-[13px]">{{ $line['action'] }}</x-button>
                </div>
            @empty
                <div class="flex items-center gap-4 py-1 md:px-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-type-knowledge-bg text-type-knowledge-fg"><x-icon name="check-circle" :size="28" /></span>
                    <div><p class="text-[16px] font-extrabold">Vous êtes à jour</p><p class="mt-1 text-[14px] text-muted">Rien ne vous attend pour l’instant.</p></div>
                </div>
            @endforelse
            <p class="border-t border-line-soft pt-3 text-[12px] leading-relaxed text-muted">Cette liste ne montre que ce qui attend une action de votre part. Elle se vide quand vous avez tout traité.</p>
        </section>

        <div class="grid min-w-0 gap-4 xl:grid-cols-2">
            <section aria-labelledby="profil" class="space-panel flex min-w-0 flex-col gap-3 p-4 md:p-5">
                <h2 id="profil" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="user" :size="23" />Mon profil</h2>
                <p class="text-[14px] leading-relaxed text-muted">Faites connaître ce que vous pouvez apporter et ce que vous souhaitez apprendre.</p>
                <div class="divide-y divide-line-soft">
                    @foreach ([['Je peux apporter', $profile->offeredSkills, 'Ajoutez vos savoir-faire', 'bg-type-knowledge-bg text-type-knowledge-fg'], ['Je veux apprendre', $profile->wantedSkills, 'Précisez ce qui vous intéresse', 'bg-type-learning-bg text-type-learning-fg']] as [$label, $skills, $empty, $colors])
                        <div class="py-2.5 first:pt-0">
                            <h3 class="text-[13px] font-bold">{{ $label }}</h3>
                            @if ($skills->isNotEmpty())
                                <ul class="mt-2 flex flex-wrap gap-1.5" aria-label="{{ $label }}">
                                    @foreach ($skills as $skill)
                                        <li class="max-w-full break-words rounded-chip px-2.5 py-1 text-[12px] font-semibold {{ $colors }}">{{ $skill->name }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-0.5 text-[13px] text-muted">{{ $empty }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-auto pt-1"><x-button :href="route('espace.profil')" class="!px-4 !text-[13px]"><x-icon name="edit" :size="18" class="mr-2" />Modifier mon profil</x-button></div>
            </section>

            <section aria-labelledby="publications" class="space-panel flex min-w-0 flex-col gap-3 p-4 md:p-5">
                <h2 id="publications" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="document" :size="23" />Mes publications</h2>
                @if ($publications->isNotEmpty())
                    <ul class="flex flex-col divide-y divide-line-soft">
                        @foreach ($publications as $publication)
                            <li wire:key="espace-publication-{{ $publication->id }}">
                                <a href="{{ route('publications.show', $publication) }}" class="flex min-h-11 flex-col gap-1.5 py-3 text-ink no-underline hover:text-ink hover:underline">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-chip px-2.5 py-0.5 text-[12px] font-bold {{ $publication->type->badgeClasses() }}">{{ $publication->displayLabel() }}</span>
                                        @if ($publication->isHidden())
                                            <span class="text-[12px] font-extrabold text-danger">Masquée</span>
                                        @endif
                                        @if ($publication->need_state && $publication->need_state !== \App\Enums\NeedState::Open)
                                            <span class="text-[12px] font-extrabold text-muted">{{ $publication->need_state->label() }}</span>
                                        @endif
                                    </span>
                                    <span class="break-words text-[14px] font-semibold">{{ Str::limit($publication->body, 90) }}</span>
                                    @if ($publication->live_gestures_count > 0)
                                        <span class="text-[12px] font-semibold text-muted">{{ $publication->type->factLabel($publication->live_gestures_count) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="flex flex-1 flex-col items-center justify-center gap-3 py-2 text-center">
                        <span class="flex size-14 items-center justify-center rounded-card bg-type-call-bg text-type-call-fg"><x-icon name="document" :size="30" /></span>
                        <div><h3 class="text-[16px] font-extrabold">Votre première action commence ici</h3><p class="mt-1 text-[14px] leading-relaxed text-muted">Un besoin, un savoir ou une aide à proposer : partagez-le dans les publications.</p></div>
                        <x-button :href="route('agir')" variant="accent" class="!px-5 !text-[13px]"><x-icon name="plus" :size="18" class="mr-2" />Agir</x-button>
                    </div>
                @endif
            </section>
        </div>

        <section aria-labelledby="echanges" class="space-panel flex min-w-0 flex-col gap-3 p-4 md:p-5">
            <h2 id="echanges" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="chat" :size="23" />Mes échanges</h2>
            <p class="text-[14px] text-muted">Vos échanges privés et vos conversations avec les personnes en relation avec vous.</p>
            @forelse ($conversations as $conversation)
                <a wire:key="espace-conversation-{{ $conversation->id }}" href="{{ route('conversations.show', $conversation) }}" class="flex min-h-11 items-center justify-between gap-3 border-t border-line-soft py-2 text-[14px] font-semibold"><span class="min-w-0 [overflow-wrap:anywhere]">{{ $conversation->otherPerson($user)?->name ?? 'Personne supprimée' }}</span><x-icon name="arrow-right" :size="18" class="shrink-0" /></a>
            @empty
                <p class="text-[14px] text-muted">Vos premiers échanges commenceront par un geste dans les publications.</p>
            @endforelse
            {{ $conversations->links() }}
        </section>

        <livewire:match-suggestions />
        <section class="space-panel p-5"><h2 class="text-[18px] font-extrabold">Mes attestations</h2><p class="mt-2 text-[14px] text-muted">Faire vérifier mon identité, répondre aux confirmations d’expérience et suivre mes recours.</p><a href="{{ route('espace.attestations') }}" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Ouvrir mes attestations →</a></section>
        <livewire:my-network />

        @if ($legacyZumras->isNotEmpty() || $legacyWallets->isNotEmpty())
            <details class="space-panel p-4 md:p-5">
                <summary class="min-h-11 cursor-pointer content-center text-[16px] font-extrabold">Mes activités antérieures</summary>
                <p class="mb-3 text-[13px] leading-relaxed text-muted">Vos activités et historiques restent accessibles. Les nouvelles créations collectives, recharges et contributions sont en pause.</p>
                <div class="flex flex-col gap-2">
                    @foreach ($legacyZumras as $legacyZumra)
                        <a href="{{ route('zumras.show', $legacyZumra) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold [overflow-wrap:anywhere]">{{ $legacyZumra->name }}</a>
                    @endforeach
                    @foreach ($legacyWallets as $legacyWallet)
                        @php $legacyOwner = $legacyWallet->isPerson() ? null : $legacyZumras->firstWhere('id', $legacyWallet->owner_id); @endphp
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $legacyWallet->isPerson() ? route('portefeuille') : route('zumras.portefeuille', $legacyWallet->owner_id) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">{{ $legacyWallet->isPerson() ? 'Mon historique de portefeuille' : 'Portefeuille : '.$legacyOwner?->name }}</a>
                            <a href="{{ $legacyWallet->isPerson() ? route('contribution') : route('zumras.contribution', $legacyWallet->owner_id) }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">Historique des contributions</a>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($walletsEnabled)
            <section aria-labelledby="portefeuille" class="space-panel flex flex-col gap-3 p-4 md:p-5">
                <h2 id="portefeuille" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="lock" :size="23" />Mon portefeuille</h2>
                <p class="text-[13px] text-muted">Vos ZAHAB et leurs mouvements. Privé : vous seul le voyez.</p>
                <div class="flex flex-col gap-2 md:flex-row">
                    <x-button :href="route('portefeuille')" variant="secondary">Ouvrir mon portefeuille</x-button>
                    <x-button :href="route('contribution')" variant="secondary">Ma contribution</x-button>
                </div>
            </section>
        @endif

        <section aria-labelledby="compte" class="space-panel flex flex-col gap-4 p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line-soft pb-3">
                <h2 id="compte" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="settings" :size="23" />Mon compte</h2>
                <span class="flex items-center gap-2 text-[11px] text-muted"><x-icon name="lock" :size="15" />Informations privées</span>
            </div>
            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                <form wire:submit="updateName" class="flex min-w-0 flex-col gap-2">
                    <label for="name" class="text-[13px] font-bold">Nom affiché</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="name" type="text" wire:model="name" autocomplete="name" required maxlength="255"
                               @class(['min-h-11 min-w-0 w-full flex-1 rounded-button border bg-card px-3.5 text-[14px]', $errors->has('name') ? 'border-danger' : 'border-chip-line'])
                               @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                        <x-button wire:loading.attr="disabled" wire:target="updateName" class="shrink-0 !px-3.5 !text-[12px]">
                            <span wire:loading.remove wire:target="updateName">Enregistrer le nom</span>
                            <span wire:loading wire:target="updateName">Enregistrement…</span>
                        </x-button>
                    </div>
                    @error('name')<p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </form>
                <div class="min-w-0 border-t border-line-soft pt-4 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
                    <p class="text-[13px] font-bold">Adresse e-mail</p>
                    <p class="mt-1 break-words text-[14px]">{{ $user->email }}</p>
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">Votre adresse e-mail n’est visible que par vous.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-line-soft pt-3 lg:hidden">
                @csrf
                <x-button variant="secondary" class="!text-[13px]"><x-icon name="logout" :size="18" class="mr-2" />Se déconnecter</x-button>
            </form>
        </section>
    @can('moderate')
        <section aria-labelledby="moderation" class="space-panel flex flex-col gap-3 p-4 md:p-5">
            <h2 id="moderation" class="text-[18px] font-extrabold">Modération</h2>
            <p class="text-muted">Les publications signalées, celles que vous avez masquées et l’explication de leur auteur ; les avis et suspensions de personnes ; le journal des actes.</p>
            <x-button :href="route('moderation')">Ouvrir la file de modération</x-button>
            <x-button variant="secondary" :href="route('moderation.personnes')">Modération des personnes</x-button>
            <x-button variant="secondary" :href="route('moderation.journal')">Journal d’administration</x-button>
        </section>
    @endcan

    @can('super-admin')
        <section aria-labelledby="administration" class="space-panel flex flex-col gap-3 p-4 md:p-5">
            <h2 id="administration" class="text-[18px] font-extrabold">Administration</h2>
            <p class="text-muted">Courriel, nom et adresse de la plateforme, limites de sécurité, domaines, territoires, charte et mentions légales.</p>
            <x-button :href="route('espace.parametres')">Paramètres</x-button>
        </section>
    @endcan


        <details wire:ignore.self class="space-panel group" @if ($errors->has('password')) open @endif x-data="{ confirming: {{ $errors->has('password') ? 'true' : 'false' }} }">
            <summary class="space-delete-summary">
                <x-icon name="warning" :size="24" class="shrink-0 text-danger" />
                <span class="min-w-0 flex-1"><span id="suppression" class="block text-[14px] font-bold text-danger">Suppression du compte</span><span class="mt-0.5 block text-[12px] leading-relaxed text-muted">Une action définitive, à confirmer avec votre mot de passe.</span></span>
                <x-icon name="chevron-down" :size="18" class="shrink-0 transition-transform group-open:rotate-180" />
            </summary>
            <div class="flex flex-col gap-3 border-t border-line-soft p-4 md:p-5">
                <p class="text-[14px] leading-relaxed text-muted">La suppression est définitive : votre compte et vos données personnelles sont effacés et ne peuvent pas être restaurés.</p>
                <div x-show="!confirming"><x-button type="button" variant="secondary" x-on:click="confirming = true; $nextTick(() => $refs.deletePassword.focus())" class="!border-danger !text-danger">Supprimer mon compte</x-button></div>
        <form wire:submit="deleteAccount" x-show="confirming" x-cloak class="flex flex-col gap-3">
            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-[13px] font-semibold">Confirmez avec votre mot de passe</label>
                <input id="password" x-ref="deletePassword" type="password" wire:model="password" autocomplete="current-password" required
                       @class(['min-h-11 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('password') ? 'border-danger' : 'border-chip-line'])
                       @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                @error('password')
                    <p id="password-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button wire:loading.attr="disabled" wire:target="deleteAccount" class="sm:flex-1">
                    <span wire:loading.remove wire:target="deleteAccount">Supprimer définitivement</span>
                    <span wire:loading wire:target="deleteAccount">Suppression…</span>
                </x-button>
                <x-button type="button" variant="secondary" class="sm:flex-1" x-on:click="confirming = false; $wire.set('password', '')">Annuler</x-button>
            </div>
        </form>
            </div>
        </details>
    </div>
</div>
