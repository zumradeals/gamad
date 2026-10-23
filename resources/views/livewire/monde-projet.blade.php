@php
    $zumra = $project->zumra;
    $meta = collect([$zumra->domain->name, $zumra->city?->label()])->filter()->implode(' · ');
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
    $total = $steps->count();
@endphp

<div class="flex flex-col gap-3.5">
    <header class="-mx-4 -mt-4 flex flex-col gap-3.5 bg-ink px-4 pb-[26px] pt-3 text-white md:mx-0 md:mt-0 md:rounded-card md:p-6">
        <a href="{{ route('projets.index') }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-white no-underline hover:text-white hover:underline">
            <x-icon name="back" :size="18" />Carrefour Projets
        </a>

        <div class="flex flex-col gap-2">
            <span class="self-start rounded-chip px-3 py-1 text-[12px] font-bold {{ $project->state->badgeClasses() }}">{{ $project->state->label() }}</span>
            <h1 class="break-words text-[26px] font-extrabold leading-tight tracking-[-0.4px]">{{ $project->name }}</h1>
            <a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center gap-2.5 self-start text-white no-underline hover:text-white">
                <x-zumra-initials :zumra="$zumra" :size="36" class="!bg-white !text-ink" />
                <span class="flex flex-col leading-tight">
                    <span class="text-[14px] font-extrabold underline">{{ $zumra->name }}</span>
                    <span class="text-[12px] text-[#d6e0e8]">{{ $meta }}</span>
                </span>
            </a>
        </div>

        <p class="whitespace-pre-line break-words text-[15px] leading-relaxed text-[#eaf0f5] md:max-w-3xl">{{ $project->goal }}</p>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        @if ($manages)
            <x-button :href="route('projets.edit', $project)" variant="accent" class="md:self-start md:px-8">Modifier le projet</x-button>
        @endif
    </header>

    <nav aria-label="Onglets du projet" class="-mx-4 flex overflow-x-auto border-b border-line bg-card px-2 md:mx-0 md:rounded-card md:border">
        @foreach (['avancement' => 'Avancement', 'fil' => 'Fil', 'equipe' => 'Équipe', 'besoins' => 'Besoins'] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" @if ($tab === $key) aria-current="page" @endif
                    @class(['flex min-h-12 min-w-11 shrink-0 items-center justify-center border-b-[3px] px-3 text-[14px]', 'border-accent font-extrabold text-ink' => $tab === $key, 'border-transparent font-semibold text-muted' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </nav>

    <div wire:loading.delay wire:target="setTab, more" class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    @if ($tab === 'avancement')
        <div class="flex flex-col gap-3.5 lg:grid lg:grid-cols-[minmax(0,1fr)_420px] lg:grid-rows-[auto_auto_1fr] lg:items-start lg:gap-x-6">
        <section aria-labelledby="avancement" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px] lg:col-start-2 lg:row-start-1">
            <h2 id="avancement" class="text-[17px] font-extrabold">Avancement</h2>
            <p class="text-[15px]"><strong>{{ $project->state->label() }}</strong> depuis le {{ $date($project->state_changed_at) }}.</p>

            @if ($total > 0)
                <div class="flex flex-col gap-1.5">
                    <p class="text-[14px] font-bold">{{ $stepsDone }} {{ $stepsDone > 1 ? 'étapes franchies' : 'étape franchie' }} sur {{ $total }}</p>
                    <div role="progressbar" aria-label="Étapes franchies" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $stepsDone }}" class="h-2.5 w-full overflow-hidden rounded-full bg-line">
                        <div class="h-full rounded-full bg-type-knowledge-fg" style="width: {{ (int) round($stepsDone / $total * 100) }}%"></div>
                    </div>
                </div>
            @endif
        </section>

        <section aria-labelledby="etapes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px] lg:col-start-1 lg:row-span-3 lg:row-start-1">
            <h2 id="etapes" class="text-[17px] font-extrabold">Étapes</h2>

            @forelse ($steps as $step)
                <div wire:key="etape-{{ $step->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0" x-data="{ confirming: false }">
                    <div class="flex items-start justify-between gap-2">
                        <span class="break-words text-[15px] font-bold">{{ $loop->iteration }}. {{ $step->title }}</span>
                        <span class="shrink-0 rounded-chip px-2.5 py-1 text-[12px] font-extrabold {{ $step->state->badgeClasses() }}">{{ $step->state->label() }}</span>
                    </div>
                    @if ($manages)
                        <div class="flex flex-wrap items-center gap-2">
                            <label for="etat-{{ $step->id }}" class="sr-only">État de l’étape {{ $step->title }}</label>
                            <select id="etat-{{ $step->id }}" wire:change="setStepState('{{ $step->id }}', $event.target.value)" class="min-h-11 rounded-[10px] border border-chip-line bg-card px-3 text-[13px] font-semibold text-ink">
                                @foreach (\App\Enums\ProjectStepState::cases() as $option)
                                    <option value="{{ $option->value }}" @selected($step->state === $option)>{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="moveStep('{{ $step->id }}', -1)" @disabled($loop->first) class="inline-flex min-h-11 items-center rounded-[10px] border border-chip-line px-3 text-[13px] font-semibold disabled:opacity-40">Monter</button>
                            <button type="button" wire:click="moveStep('{{ $step->id }}', 1)" @disabled($loop->last) class="inline-flex min-h-11 items-center rounded-[10px] border border-chip-line px-3 text-[13px] font-semibold disabled:opacity-40">Descendre</button>
                            <button type="button" x-show="! confirming" x-on:click="confirming = true" class="inline-flex min-h-11 min-w-11 items-center justify-center px-2 text-[13px] font-semibold text-muted underline">Retirer</button>
                            <span x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                                <span class="text-[13px] font-semibold">Retirer cette étape ?</span>
                                <x-button type="button" wire:click="removeStep('{{ $step->id }}')" wire:loading.attr="disabled" wire:target="removeStep" class="!px-4 !text-[14px]">Oui</x-button>
                                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
                            </span>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-[14px] text-muted">{{ $manages ? 'Aucune étape pour l’instant. Ajoutez la première : un jalon que le projet doit franchir.' : 'Aucune étape pour l’instant.' }}</p>
            @endforelse

            @if ($manages)
                @if ($total < $stepMax)
                    <form wire:submit="addStep" class="flex flex-col gap-2 border-t border-line pt-3">
                        <label for="titre-etape" class="text-[13px] font-extrabold">Nouvelle étape</label>
                        <input id="titre-etape" type="text" wire:model="stepTitle" maxlength="{{ \App\Models\ProjectStep::TITLE_MAX }}" autocomplete="off" placeholder="Ex. Commander le tissu"
                               @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('stepTitle') ? 'border-danger' : 'border-chip-line'])
                               @if ($errors->has('stepTitle')) aria-invalid="true" aria-describedby="etape-erreur" @endif>
                        @error('stepTitle')
                            <p id="etape-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                        @enderror
                        <x-button wire:loading.attr="disabled" wire:target="addStep">
                            <span wire:loading.remove wire:target="addStep">Ajouter l’étape</span>
                            <span wire:loading wire:target="addStep">Ajout…</span>
                        </x-button>
                    </form>
                @else
                    <p class="border-t border-line pt-3 text-[13px] text-muted">Un projet a {{ $stepMax }} étapes au plus.</p>
                @endif
            @endif
        </section>

        <section aria-labelledby="histoire" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px] lg:col-start-2 lg:row-start-2">
            <h2 id="histoire" class="text-[17px] font-extrabold">Histoire du projet</h2>
            <ol class="flex flex-col gap-2.5" aria-label="Changements d’état">
                @foreach ($changes as $change)
                    <li wire:key="changement-{{ $change->id }}" class="flex flex-col text-[14px]">
                        <span class="font-bold">{{ $change->from_state ? 'Passé de « '.$change->from_state->label().' » à « '.$change->to_state->label().' »' : 'Projet créé comme une idée' }}</span>
                        <span class="text-[12px] text-muted">{{ $date($change->created_at) }}@if ($change->user) · par {{ $change->user->name }}@endif</span>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($manages)
            <section aria-labelledby="etat" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4 lg:col-start-2 lg:row-start-3" x-data="{ confirming: null }">
                <h2 id="etat" class="text-[15px] font-extrabold">Changer l’état du projet</h2>
                <p class="text-[13px] text-muted">Seuls le fondateur et les responsables de la ZUMRA changent l’état. Chaque changement est gardé dans l’histoire du projet.</p>
                <div class="flex flex-col gap-2">
                    @foreach ($project->state->next() as $next)
                        <div wire:key="etat-{{ $next->value }}" class="flex flex-col gap-2">
                            <x-button type="button" variant="secondary" x-show="confirming !== '{{ $next->value }}'" x-on:click="confirming = '{{ $next->value }}'">{{ $next->action() }}</x-button>
                            <div x-show="confirming === '{{ $next->value }}'" x-cloak class="flex flex-wrap items-center gap-2">
                                <span class="text-[13px] font-semibold">Passer à « {{ $next->label() }} » ?</span>
                                <x-button type="button" wire:click="changeState('{{ $next->value }}')" wire:loading.attr="disabled" wire:target="changeState" class="!px-4 !text-[14px]">Oui</x-button>
                                <x-button type="button" variant="secondary" x-on:click="confirming = null" class="!px-4 !text-[14px]">Non</x-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        </div>
    @elseif ($tab === 'fil')
        <section aria-labelledby="fil-projet" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="fil-projet" class="text-[17px] font-extrabold">Fil du projet</h2>
                @if ($manages)
                    <div class="flex flex-wrap items-center gap-x-4" aria-label="Publier au nom du projet">
                        @foreach ([\App\Enums\PublicationType::ProjectUpdate, \App\Enums\PublicationType::Achievement] as $kind)
                            <a href="{{ route('projets.publier', [$project, $kind->slug()]) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">{{ $kind === \App\Enums\PublicationType::ProjectUpdate ? 'Publier une avancée' : 'Publier une réalisation' }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid gap-3.5 md:grid-cols-2">
            @forelse ($publications as $post)
                <x-publication-card wire:key="projet-publication-{{ $post->id }}" :publication="$post" :viewer="$viewer" :gesture="null" :supported="$supported" :managed="$managed" :member-of="$memberOf" />
            @empty
                <section class="rounded-card border border-line bg-card p-5 md:col-span-full">
                    <p class="text-[16px] font-extrabold">Rien dans le Fil de ce projet pour l’instant.</p>
                    <p class="mt-1 text-muted">{{ $manages ? 'Publiez une avancée pour dire où en est le projet, ou une réalisation pour montrer ce qui a été fait.' : 'Les avancées et réalisations du projet apparaîtront ici.' }}</p>
                </section>
            @endforelse
            </div>

            @if ($hasMore)
                <x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled" wire:target="more">Voir plus de publications</x-button>
            @endif
        </section>
    @elseif ($tab === 'equipe')
        <div class="flex flex-col gap-3.5 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6">
        <div class="flex flex-col gap-3.5">
        <section aria-labelledby="equipe" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="equipe" class="text-[17px] font-extrabold">Équipe du projet</h2>
            <p class="text-[14px]"><strong>{{ $teamCount }}</strong> {{ $teamCount > 1 ? 'personnes participent' : 'personne participe' }} à ce projet. L’équipe se compose de membres de la ZUMRA.</p>

            @if ($canSeeTeam)
                <ul class="flex flex-col divide-y divide-line-soft">
                    @forelse ($teamMembers as $member)
                        @php
                            $person = $member->user;
                            $profile = $person->profile;
                            $profile?->setRelation('user', $person);
                            $canSee = $profile && Gate::forUser($viewer)->allows('view', $profile);
                        @endphp
                        <li wire:key="equipe-{{ $member->id }}" class="flex flex-col gap-2 py-2.5 first:pt-0 last:pb-0" x-data="{ confirming: false }">
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
                            </div>
                            @if ($manages)
                                <div class="flex flex-wrap gap-2">
                                    <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true" class="!px-4 !text-[14px]">Retirer de l’équipe</x-button>
                                    <span x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                                        <span class="text-[13px] font-semibold">Retirer {{ $person->name }} ?</span>
                                        <x-button type="button" wire:click="removeMember('{{ $member->id }}')" wire:loading.attr="disabled" wire:target="removeMember" class="!px-4 !text-[14px]">Oui, retirer</x-button>
                                        <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Annuler</x-button>
                                    </span>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="text-[14px] text-muted">{{ $manages ? 'Personne dans l’équipe pour l’instant. Ajoutez des membres de la ZUMRA ci-dessous.' : 'Personne dans l’équipe pour l’instant.' }}</li>
                    @endforelse
                </ul>
            @else
                <p class="text-[14px] text-muted">Les personnes de l’équipe sont visibles des personnes inscrites.</p>
                @guest
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button :href="route('login')" variant="secondary" class="sm:flex-1">Se connecter</x-button>
                        <x-button :href="route('register')" class="sm:flex-1">Créer un compte</x-button>
                    </div>
                @endguest
            @endif
        </section>

        @if ($viewer)
            <section aria-labelledby="participer" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <h2 id="participer" class="text-[17px] font-extrabold">Participer à ce projet</h2>
                @if ($isMember)
                    <p class="text-[14px] font-bold text-type-knowledge-fg">Vous êtes dans l’équipe.</p>
                    <div x-data="{ confirming: false }">
                        <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true">Quitter l’équipe</x-button>
                        <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                            <span class="text-[13px] font-semibold">Quitter l’équipe de ce projet ?</span>
                            <x-button type="button" wire:click="leaveTeam" wire:loading.attr="disabled" wire:target="leaveTeam" class="!px-4 !text-[14px]">Oui</x-button>
                            <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
                        </div>
                    </div>
                @elseif ($participation->allowed())
                    <form wire:submit="requestParticipation" class="flex flex-col gap-2" x-data="{ n: 0 }">
                        <label for="mot-participation" class="text-[13px] font-bold text-muted">Un mot pour les responsables (facultatif)</label>
                        <textarea id="mot-participation" wire:model="participationWord" rows="3" maxlength="{{ \App\Models\ParticipationRequest::MESSAGE_MAX }}" x-on:input="n = $el.value.length"
                                  @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('participationWord') ? 'border-danger' : 'border-chip-line'])
                                  @if ($errors->has('participationWord')) aria-invalid="true" aria-describedby="participation-erreur" @endif></textarea>
                        @error('participationWord')
                            <p id="participation-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                        @enderror
                        <x-button wire:loading.attr="disabled" wire:target="requestParticipation">
                            <span wire:loading.remove wire:target="requestParticipation">Je veux participer</span>
                            <span wire:loading wire:target="requestParticipation">Envoi…</span>
                        </x-button>
                    </form>
                @elseif ($myRequest?->isPending())
                    <p class="text-[14px] font-bold text-type-learning-fg">Demande envoyée. Les responsables de la ZUMRA vont la lire.</p>
                    <x-button type="button" variant="secondary" wire:click="withdrawParticipation" wire:loading.attr="disabled" wire:target="withdrawParticipation">Retirer ma demande</x-button>
                @else
                    <p class="text-[14px] text-muted">{{ $participation->message() }}</p>
                    @if (! $project->zumra->roleOf($viewer) && $viewer->hasVerifiedEmail())
                        <x-button :href="route('zumras.show', $zumra).'#rejoindre'" variant="secondary">Voir la ZUMRA</x-button>
                    @endif
                @endif
            </section>
        @else
            <section class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
                <p class="text-[14px] text-muted">Connectez-vous pour participer à ce projet : il faut être membre de la ZUMRA.</p>
                <x-button :href="route('login')" variant="secondary">Se connecter</x-button>
            </section>
        @endif

        </div>
        @if ($manages)
            <div class="flex flex-col gap-3.5">
            <section aria-labelledby="demandes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <h2 id="demandes" class="text-[17px] font-extrabold">Demandes de participation</h2>
                @forelse ($requests as $request)
                    @php
                        $person = $request->user;
                        $profile = $person->profile;
                        $profile?->setRelation('user', $person);
                        $canSee = $profile && Gate::forUser($viewer)->allows('view', $profile);
                    @endphp
                    <div wire:key="demande-{{ $request->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                        <span class="break-words text-[15px] font-extrabold">{{ $person->name }}</span>
                        @if ($canSee && $profile->offeredSkills->isNotEmpty())
                            <span class="text-[12px] text-muted">{{ $profile->offeredSkills->pluck('name')->take(3)->implode(' · ') }}</span>
                        @endif
                        @if ($request->message)
                            <p class="whitespace-pre-line break-words text-[14px] leading-normal">« {{ $request->message }} »</p>
                        @endif
                        <div class="grid grid-cols-2 gap-2">
                            <x-button type="button" wire:click="acceptRequest('{{ $request->id }}')" wire:loading.attr="disabled" wire:target="acceptRequest, declineRequest" class="!px-4 !text-[14px]">Accepter</x-button>
                            <x-button type="button" variant="secondary" wire:click="declineRequest('{{ $request->id }}')" wire:loading.attr="disabled" wire:target="acceptRequest, declineRequest" class="!px-4 !text-[14px]">Décliner</x-button>
                        </div>
                    </div>
                @empty
                    <p class="text-[14px] text-muted">Aucune demande en attente.</p>
                @endforelse
            </section>

            <section aria-labelledby="ajouter" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
                <h2 id="ajouter" class="text-[17px] font-extrabold">Ajouter des membres de la ZUMRA</h2>
                @error('team')
                    <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
                @forelse ($candidates as $candidate)
                    <div wire:key="candidat-{{ $candidate->id }}" class="flex items-center gap-3 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="break-words text-[15px] font-extrabold">{{ $candidate->user->name }}</span>
                            <span class="text-[12px] text-muted">{{ $candidate->role->label() }}</span>
                        </div>
                        <x-button type="button" variant="secondary" wire:click="addMember('{{ $candidate->user_id }}')" wire:loading.attr="disabled" wire:target="addMember" class="shrink-0 !px-4 !text-[14px]">Ajouter</x-button>
                    </div>
                @empty
                    <p class="text-[14px] text-muted">Tous les membres de la ZUMRA sont déjà dans l’équipe.</p>
                @endforelse
            </section>
        </div>
        @endif
        </div>
    @else
        <section aria-labelledby="besoins-projet" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="besoins-projet" class="text-[17px] font-extrabold">Besoins du projet</h2>
                @if ($manages)
                    <a href="{{ route('projets.publier', [$project, 'besoin']) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Publier un besoin</a>
                @endif
            </div>

            <div class="grid gap-3.5 md:grid-cols-2">
            @forelse ($publications as $post)
                <x-publication-card wire:key="projet-besoin-{{ $post->id }}" :publication="$post" :viewer="$viewer" :gesture="$gestures[$post->id] ?? null" :supported="$supported" :managed="$managed" :member-of="$memberOf" />
            @empty
                <section class="rounded-card border border-line bg-card p-5 md:col-span-full">
                    <p class="text-[16px] font-extrabold">Aucun besoin pour ce projet pour l’instant.</p>
                    <p class="mt-1 text-muted">{{ $manages ? 'Publiez un besoin : ce qui manque au projet pour avancer. Des personnes pourront proposer leur aide.' : 'Les besoins du projet apparaîtront ici.' }}</p>
                </section>
            @endforelse
            </div>

            @if ($hasMore)
                <x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled" wire:target="more">Voir plus de besoins</x-button>
            @endif
        </section>
    @endif

    <livewire:gesture-sheet />
</div>
