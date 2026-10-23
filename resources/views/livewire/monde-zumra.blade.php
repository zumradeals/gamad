@php
    $meta = collect([$zumra->domain->name, $zumra->city?->label(), $zumra->format->label()])->filter()->implode(' · ');
    $since = $zumra->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('M Y');
@endphp

<div class="flex flex-col gap-3.5">
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[26px] pt-3 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <a href="{{ route('zumras.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-white no-underline hover:text-white hover:underline">
            <x-icon name="back" :size="18" />Carrefour ZUMRA
        </a>

        <div class="flex items-center gap-3.5">
            <x-zumra-initials :zumra="$zumra" :size="64" class="!bg-white !text-ink" />
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="break-words text-[26px] font-extrabold leading-tight tracking-[-0.4px]">{{ $zumra->name }}</h1>
                <span class="text-[13px] text-[#d6e0e8]">{{ $meta }}</span>
            </div>
        </div>

        <span class="self-start rounded-chip bg-type-call-bg px-3 py-1 text-[12px] font-bold text-type-call-fg">Active</span>

        <p class="whitespace-pre-line break-words text-[15px] leading-relaxed text-[#eaf0f5]">{{ $zumra->mission }}</p>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        @if ($manages)
            <x-button :href="route('zumras.edit', $zumra)" variant="accent">Modifier la ZUMRA</x-button>
        @endif
    </header>

    <section aria-label="La ZUMRA en chiffres" class="-mt-9 grid grid-cols-2 divide-x divide-line rounded-card border border-line bg-card py-3.5 md:mt-0">
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[22px] font-extrabold leading-tight">{{ $zumra->members_count }}</span>
            <span class="text-[12px] text-muted">{{ $zumra->members_count > 1 ? 'membres' : 'membre' }}</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
            <span class="text-[18px] font-extrabold capitalize leading-tight">{{ $since }}</span>
            <span class="text-[12px] text-muted">création</span>
        </div>
    </section>

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
    @elseif (! $role)
        <section id="rejoindre" aria-label="Rejoindre cette ZUMRA" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4"
                 x-data="{ open: false }" x-init="open = location.hash === '#rejoindre'">
            @guest
                <p class="text-[14px] text-muted">Connectez-vous pour demander à rejoindre cette ZUMRA.</p>
                <x-button :href="route('login')" variant="accent">Je veux rejoindre</x-button>
            @else
                @if (! $viewer->hasVerifiedEmail())
                    <p class="text-[14px] text-muted">Vérifiez votre adresse e-mail pour demander à rejoindre une ZUMRA.</p>
                @elseif ($myRequest?->state === \App\Enums\JoinRequestState::Pending)
                    <p class="text-[15px] font-extrabold">Demande envoyée</p>
                    <p class="text-[14px] text-muted">Le fondateur ou un responsable y répondra.</p>
                    @if (filled($myRequest->message))
                        <p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px] leading-normal">{{ $myRequest->message }}</p>
                    @endif
                    <x-button type="button" variant="secondary" wire:click="withdrawRequest" wire:loading.attr="disabled" wire:target="withdrawRequest">Retirer ma demande</x-button>
                @elseif ($myRequest?->state === \App\Enums\JoinRequestState::Declined)
                    <p class="text-[14px] font-semibold text-muted">Votre demande n’a pas été retenue.</p>
                @else
                    <div x-show="! open">
                        <x-button type="button" variant="accent" class="w-full" x-on:click="open = true">Je veux rejoindre</x-button>
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

    <nav aria-label="Onglets de la ZUMRA" class="-mx-4 flex border-b border-line bg-card px-2 md:mx-0 md:rounded-card md:border">
        <span aria-current="page" class="flex min-h-12 items-center border-b-[3px] border-accent px-3 text-[14px] font-extrabold text-ink">Membres</span>
    </nav>

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
                <x-button :href="route('zumras.invite', $zumra)" variant="secondary">Inviter des personnes selon leurs capacités</x-button>
            </div>
        @endif

        @if ($canSeeMembers)
            <ul class="flex flex-col divide-y divide-line-soft">
                @foreach ($members as $membership)
                    @php
                        $person = $membership->user;
                        $profile = $person->profile;
                        $profile?->setRelation('user', $person);
                        $canSee = $profile && Gate::forUser($viewer)->allows('view', $profile);
                    @endphp
                    <li wire:key="membre-{{ $membership->id }}" class="flex flex-col gap-2 py-2.5 first:pt-0 last:pb-0" x-data="{ confirming: false }">
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
</div>
