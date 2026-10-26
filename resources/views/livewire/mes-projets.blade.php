@php $box = 'flex flex-col gap-3 rounded-card border border-line bg-card p-4'; @endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Mon espace</a>
        <h1 class="text-[26px] font-extrabold leading-tight">Mes projets</h1>
        <p class="text-muted">Les projets que vous portez ou auxquels vous collaborez. Un projet reste privé : il n’est jamais publié sans votre choix.</p>
    </div>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @if ($errors->any())<div role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif

    <x-button class="gap-2 sm:self-start" :href="route('projets.new')" variant="accent"><x-icon name="plus" :size="18" />Créer un projet</x-button>

    @if ($invitations->isNotEmpty())
        <section aria-labelledby="invitations-projet" class="{{ $box }} !border-2 !border-ink">
            <h2 id="invitations-projet" class="text-[18px] font-extrabold">Invitations à collaborer</h2>
            @foreach ($invitations as $invitation)
                <div wire:key="invitation-{{ $invitation->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                    <p class="break-words text-[15px] font-extrabold">{{ $invitation->project->name }}</p>
                    <p class="text-[13px] text-muted">{{ $invitation->inviter ? $invitation->inviter->name.' vous invite' : 'Vous êtes invité' }} à collaborer à ce projet. L’accord est le vôtre.</p>
                    @if ($invitation->message)<p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px]">{{ $invitation->message }}</p>@endif
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button type="button" wire:click="accept('{{ $invitation->id }}')" wire:loading.attr="disabled" wire:target="accept, refuse" class="sm:flex-1">Accepter</x-button>
                        <x-button type="button" variant="secondary" wire:click="refuse('{{ $invitation->id }}')" wire:loading.attr="disabled" wire:target="accept, refuse" class="sm:flex-1">Refuser</x-button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($companyLinks->isNotEmpty())
        <section aria-labelledby="liens-projet" class="{{ $box }} !border-2 !border-ink">
            <h2 id="liens-projet" class="text-[18px] font-extrabold">Projets à relier à votre entreprise</h2>
            @foreach ($companyLinks as $link)
                <div wire:key="lien-{{ $link->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                    <p class="text-[15px] font-extrabold">{{ $link->project->name }}</p>
                    <p class="text-[13px] text-muted">Relation proposée : {{ strtolower($link->kindLabel()) }} {{ $link->company->name }}. Ni société à créer, ni contribution financière.</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button type="button" wire:click="confirmLink('{{ $link->id }}')" wire:loading.attr="disabled" wire:target="confirmLink, declineLink" class="sm:flex-1">Confirmer</x-button>
                        <x-button type="button" variant="secondary" wire:click="declineLink('{{ $link->id }}')" wire:loading.attr="disabled" wire:target="confirmLink, declineLink" class="sm:flex-1">Refuser</x-button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section aria-labelledby="projets-portes" class="{{ $box }}">
        <h2 id="projets-portes" class="text-[18px] font-extrabold">Projets que je porte</h2>
        @forelse ($owned as $project)
            <div wire:key="porte-{{ $project->id }}" class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                <x-zumra-initials :zumra="$project" :size="48" />
                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="break-words text-[16px] font-extrabold">{{ $project->name }}</span>
                    <span class="text-[13px] text-muted">{{ $project->privacyLabel() }} · {{ $project->stage->label() }} · {{ $project->isHosted() ? 'Accueilli par '.$project->zumra->name : $project->hosting_state->label().' : '.$project->zumra->name }}@if ($project->isShowcasePublished()) · Présentation publique @endif</span>
                </div>
                <x-button :href="route('projets.show', $project)" variant="secondary" class="w-full sm:w-auto">Ouvrir</x-button>
            </div>
        @empty
            <p class="text-[14px] text-muted">Vous ne portez aucun projet pour l’instant. Créez le vôtre en quelques instants : un nom, ce que vous voulez réaliser, un domaine et la ZUMRA qui vous accompagnera.</p>
        @endforelse
    </section>

    <section aria-labelledby="collaborations" class="{{ $box }}">
        <h2 id="collaborations" class="text-[18px] font-extrabold">Mes collaborations</h2>
        @forelse ($collaborations as $project)
            <div wire:key="collab-{{ $project->id }}" class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                <div class="flex min-w-0 flex-1 flex-col"><span class="break-words text-[16px] font-extrabold">{{ $project->name }}</span><span class="text-[13px] text-muted">Porté par {{ $project->owner?->name ?? 'une personne' }}</span></div>
                <x-button :href="route('projets.show', $project)" variant="secondary" class="w-full sm:w-auto">Ouvrir</x-button>
            </div>
        @empty
            <p class="text-[14px] text-muted">Vous ne collaborez à aucun projet pour l’instant.</p>
        @endforelse
    </section>

    @if ($requests->isNotEmpty())
        <section aria-labelledby="mes-demandes-projet" class="{{ $box }}">
            <h2 id="mes-demandes-projet" class="text-[18px] font-extrabold">Mes demandes d’aide en attente</h2>
            @foreach ($requests as $request)<p wire:key="demande-{{ $request->id }}" class="text-[14px]"><a href="{{ route('projets.show', $request->project) }}" class="font-extrabold">{{ $request->project->name }}</a> : le porteur y répondra.</p>@endforeach
        </section>
    @endif
</div>
