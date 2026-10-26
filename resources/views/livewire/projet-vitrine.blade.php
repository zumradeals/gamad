@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
    $place = ($project->city ?? $zumra->city)?->label();
    $card = 'rounded-card border border-line bg-card p-5';
@endphp

<div class="flex flex-col gap-4">
    <nav aria-label="Fil d’Ariane" class="flex flex-wrap items-center gap-x-2 text-[13px] text-muted">
        <a href="{{ route('fil') }}" class="inline-flex min-h-11 items-center">Accueil</a><span aria-hidden="true">›</span>
        <a href="{{ route('zumras.index') }}" class="inline-flex min-h-11 items-center">ZUMRA</a><span aria-hidden="true">›</span>
        <a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words">{{ $zumra->name }}</a><span aria-hidden="true">›</span>
        <span>Projet</span><span aria-hidden="true">›</span>
        <span class="font-bold text-ink [overflow-wrap:anywhere]" aria-current="page">{{ $project->name }}</span>
    </nav>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif

    <div class="flex flex-col gap-5 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4">
            <section class="{{ $card }} grid gap-5 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]" aria-labelledby="projet-titre">
                <div class="zumra-mission-cover !h-full !min-h-[180px] !w-full rounded-card" aria-hidden="true"><x-zumra-initials :zumra="$project" :size="88" /></div>
                <div class="flex min-w-0 flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-chip bg-accent px-3 py-1 text-[12px] font-extrabold text-ink">Projet</span>
                        <span class="inline-flex items-center gap-1.5 rounded-chip bg-type-knowledge-bg px-3 py-1 text-[12px] font-bold text-type-knowledge-fg"><x-icon name="check-circle" :size="14" />Présentation publique</span>
                    </div>
                    <h1 id="projet-titre" class="break-words text-[28px] font-extrabold leading-tight tracking-[-0.5px]">{{ $project->name }}</h1>
                    <p class="whitespace-pre-line break-words text-[16px] leading-relaxed text-muted">{{ $project->showcase_summary }}</p>
                    <ul class="flex flex-col gap-1.5 text-[14px] text-muted">
                        @if ($place)<li class="flex items-center gap-2"><x-icon name="pin" :size="18" class="shrink-0" />{{ $place }}</li>@endif
                        <li class="flex items-center gap-2"><x-icon name="community" :size="18" class="shrink-0" /><a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center font-semibold">{{ $zumra->name }}</a></li>
                    </ul>
                    @if ($project->domainLabel() || $needs->isNotEmpty())
                        <ul class="flex flex-wrap gap-2" aria-label="Thèmes">
                            @if ($project->domainLabel())<li class="rounded-chip bg-soft-blue px-3 py-1 text-[12px] font-semibold text-link">{{ $project->domainLabel() }}</li>@endif
                            @foreach ($needs as $skill)<li class="rounded-chip bg-soft-blue px-3 py-1 text-[12px] font-semibold text-link">{{ $skill->name }}</li>@endforeach
                        </ul>
                    @endif
                    <div class="flex flex-col gap-2 sm:flex-row">
                        @if ($owner && $ownerProfile && $viewer && $viewer->getKey() !== $owner->getKey())
                            <x-button :href="route('personnes.show', $ownerProfile->public_id)" variant="accent" class="gap-2"><x-icon name="chat" :size="18" />Contacter le porteur</x-button>
                        @elseif ($owner && ! $viewer)
                            <x-button :href="route('login')" variant="accent" class="gap-2"><x-icon name="chat" :size="18" />Contacter le porteur</x-button>
                        @endif
                    </div>
                </div>
            </section>

            <nav aria-label="Sections de la présentation" class="flex overflow-x-auto rounded-card border border-line bg-card px-2">
                @foreach (['presentation' => 'Présentation', 'porteur' => 'Porteur & ZUMRA', 'contribuer' => 'Comment contribuer ?', 'evolution' => 'Évolution'] as $anchor => $label)
                    <a href="#{{ $anchor }}" class="flex min-h-12 shrink-0 items-center whitespace-nowrap px-3 text-[14px] font-semibold text-ink no-underline hover:text-ink hover:underline">{{ $label }}</a>
                @endforeach
            </nav>

            <section id="presentation" class="{{ $card }} flex scroll-mt-24 flex-col gap-2" aria-labelledby="titre-presentation">
                <h2 id="titre-presentation" class="text-[20px] font-extrabold">À propos du projet</h2>
                <p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $project->showcase_summary }}</p>
            </section>

            <section id="porteur" class="{{ $card }} flex scroll-mt-24 flex-col gap-3" aria-labelledby="titre-porteur">
                <h2 id="titre-porteur" class="text-[20px] font-extrabold">Porteur & ZUMRA</h2>
                @if ($owner)
                    <div class="flex items-center gap-3">
                        @if ($ownerProfile)<x-avatar :profile="$ownerProfile" :size="52" />@else<span class="flex size-[52px] shrink-0 items-center justify-center rounded-full bg-line-soft text-[16px] font-extrabold text-muted" aria-hidden="true">{{ $owner->initials() }}</span>@endif
                        <div class="flex min-w-0 flex-col"><span class="text-[13px] text-muted">Porteur du projet</span>
                            @if ($ownerProfile)<a href="{{ route('personnes.show', $ownerProfile->public_id) }}" class="inline-flex min-h-11 items-center break-words text-[16px] font-extrabold">{{ $owner->name }}</a>@else<span class="break-words text-[16px] font-extrabold">{{ $owner->name }}</span>@endif</div>
                    </div>
                @endif
                <div class="flex items-center gap-3">
                    <x-zumra-initials :zumra="$zumra" :size="52" />
                    <div class="flex min-w-0 flex-col"><span class="text-[13px] text-muted">ZUMRA d’accueil</span><a href="{{ route('zumras.show', $zumra) }}" class="inline-flex min-h-11 items-center break-words text-[16px] font-extrabold">{{ $zumra->name }}</a></div>
                </div>
                <p class="text-[13px] text-muted">La ZUMRA accueille le projet : elle ne le dirige pas et n’en détient ni les documents ni les ressources.</p>
                @foreach ($confirmedCompanies as $link)<p class="text-[14px]"><strong>{{ $link->kindLabel() }}</strong> <a href="{{ route('entreprises.show', $link->company) }}">{{ $link->company->name }}</a></p>@endforeach
            </section>

            <section id="contribuer" class="{{ $card }} flex scroll-mt-24 flex-col gap-3" aria-labelledby="titre-contribuer">
                <h2 id="titre-contribuer" class="text-[20px] font-extrabold">Comment contribuer ?</h2>
                @if ($project->showcase_contribute)<p class="whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $project->showcase_contribute }}</p>@else<p class="text-[14px] text-muted">Le porteur n’a pas encore précisé comment contribuer.</p>@endif

                @if ($viewer)
                    @if ($participation?->allowed())
                        <form wire:submit="requestParticipation" class="flex flex-col gap-2" x-data="{ n: 0 }">
                            <label for="mot-aide" class="text-[13px] font-bold text-muted">Un mot pour le porteur (facultatif)</label>
                            <textarea id="mot-aide" wire:model="participationWord" rows="3" maxlength="{{ \App\Models\ParticipationRequest::MESSAGE_MAX }}" class="w-full rounded-button border border-chip-line bg-card px-3.5 py-3 text-[15px]"></textarea>
                            @error('participationWord')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                            <x-button wire:loading.attr="disabled" wire:target="requestParticipation">Proposer mon aide</x-button>
                        </form>
                    @elseif ($myRequest?->isPending())
                        <p class="text-[14px] font-bold text-type-learning-fg">Demande envoyée. Le porteur du projet va la lire.</p>
                        <x-button type="button" variant="secondary" wire:click="withdrawParticipation" wire:loading.attr="disabled" wire:target="withdrawParticipation">Retirer ma demande</x-button>
                    @elseif ($participation)
                        <p class="text-[14px] text-muted">{{ $participation->message() }}</p>
                    @endif
                @else
                    <x-button :href="route('login')" variant="secondary">Se connecter pour proposer mon aide</x-button>
                @endif
            </section>

            <section id="evolution" class="{{ $card }} flex scroll-mt-24 flex-col gap-3" aria-labelledby="titre-evolution">
                <h2 id="titre-evolution" class="text-[20px] font-extrabold">Évolution</h2>
                <ol class="grid grid-cols-1 gap-2.5 sm:grid-cols-5 sm:gap-1 sm:text-center" aria-label="Étapes de vie du projet">
                    @foreach (\App\Enums\ProjectStage::cases() as $stage)
                        <li class="flex items-center gap-3 sm:flex-col sm:gap-1.5">
                            <span @class(['flex size-7 items-center justify-center rounded-full border-2 text-[12px] font-extrabold', 'border-ink bg-ink text-white' => $stage->rank() <= $project->stage->rank(), 'border-line bg-card text-muted' => $stage->rank() > $project->stage->rank()]) aria-hidden="true">{{ $stage->rank() < $project->stage->rank() ? '✓' : $stage->rank() + 1 }}</span>
                            <span @class(['text-[12px] leading-tight sm:text-[13px]', 'font-extrabold' => $stage === $project->stage, 'text-muted' => $stage->rank() > $project->stage->rank()])>{{ $stage->label() }}</span>
                            <span class="sr-only">{{ $stage === $project->stage ? 'Étape actuelle' : ($stage->rank() < $project->stage->rank() ? 'Étape franchie' : 'Étape à venir') }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <aside class="flex min-w-0 flex-col gap-4" aria-label="Informations sur le projet">
            <section class="{{ $card }} flex flex-col gap-3" aria-labelledby="etat-projet">
                <h2 id="etat-projet" class="text-[18px] font-extrabold">État du projet</h2>
                <div class="rounded-card bg-type-call-bg p-3.5 text-type-call-fg"><p class="text-[15px] font-extrabold">{{ $project->stage->label() }}</p><p class="text-[13px]">{{ $project->stage->sentence() }}</p></div>
                <dl class="flex flex-col divide-y divide-line-soft text-[14px]">
                    @if ($project->domainLabel())<div class="flex justify-between gap-3 py-2"><dt class="text-muted">Domaine</dt><dd class="text-right font-semibold">{{ $project->domainLabel() }}</dd></div>@endif
                    @if ($place)<div class="flex justify-between gap-3 py-2"><dt class="text-muted">Localité</dt><dd class="text-right font-semibold">{{ $place }}</dd></div>@endif
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Présenté le</dt><dd class="text-right font-semibold">{{ $date($project->showcase_published_at) }}</dd></div>
                </dl>
            </section>

            @if ($owner)
                <section class="{{ $card }} flex flex-col gap-3" aria-labelledby="porteur-lateral">
                    <h2 id="porteur-lateral" class="text-[18px] font-extrabold">Porteur du projet</h2>
                    <div class="flex items-center gap-3">
                        @if ($ownerProfile)<x-avatar :profile="$ownerProfile" :size="52" />@endif
                        <span class="break-words text-[16px] font-extrabold">{{ $owner->name }}</span>
                    </div>
                    @if ($ownerProfile)<x-button :href="route('personnes.show', $ownerProfile->public_id)" variant="secondary">Voir le profil</x-button>@endif
                </section>
            @endif

            <section class="{{ $card }} flex flex-col gap-3" aria-labelledby="zumra-lateral">
                <h2 id="zumra-lateral" class="text-[18px] font-extrabold">ZUMRA d’accueil</h2>
                <div class="flex items-center gap-3"><x-zumra-initials :zumra="$zumra" :size="52" /><span class="break-words text-[16px] font-extrabold">{{ $zumra->name }}</span></div>
                <x-button :href="route('zumras.show', $zumra)" variant="secondary">Voir la ZUMRA</x-button>
            </section>

            <section class="flex flex-col gap-2 rounded-card bg-soft-blue p-5" aria-labelledby="visibilite">
                <h2 id="visibilite" class="flex items-center gap-2 text-[17px] font-extrabold text-link"><x-icon name="shield" :size="20" />Une visibilité maîtrisée</h2>
                <p class="text-[13px] leading-relaxed text-muted">Cette présentation ne donne pas accès au plan d’affaires, aux documents internes, au budget ni aux informations stratégiques du projet. Pour en savoir plus ou proposer votre aide, contactez le porteur.</p>
            </section>
        </aside>
    </div>
</div>
