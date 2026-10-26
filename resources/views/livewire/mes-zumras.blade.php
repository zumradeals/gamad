<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Mon espace</a>
        <h1 class="text-[26px] font-extrabold leading-tight">Mes ZUMRA</h1>
        <p class="text-muted">Les communautés dont vous faites partie, vos invitations et vos demandes en attente.</p>
    </div>

    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row">
        <x-button class="gap-2" :href="route('zumras.create')" variant="accent"><x-icon name="community" :size="20" />Créer une ZUMRA</x-button>
        <x-button :href="route('zumras.index')" variant="secondary">Découvrir les ZUMRA</x-button>
    </div>

    @if ($invitations->isNotEmpty())
        <section aria-labelledby="invitations" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-card p-4">
            <h2 id="invitations" class="text-[18px] font-extrabold">Mes invitations</h2>
            @foreach ($invitations as $invitation)
                <div wire:key="invitation-{{ $invitation->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                    <p class="text-[15px] font-extrabold"><a href="{{ route('zumras.show', $invitation->zumra) }}" class="text-ink">{{ $invitation->zumra->name }}</a></p>
                    <p class="text-[13px] text-muted">{{ $invitation->inviter ? $invitation->inviter->name.' vous invite' : 'Vous êtes invité' }} · valable jusqu’au {{ $invitation->expires_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}.</p>
                    @if (filled($invitation->message))<p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px] leading-normal">{{ $invitation->message }}</p>@endif
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button type="button" wire:click="accept('{{ $invitation->id }}')" wire:loading.attr="disabled" wire:target="accept, refuse" class="sm:flex-1">Accepter</x-button>
                        <x-button type="button" variant="secondary" wire:click="refuse('{{ $invitation->id }}')" wire:loading.attr="disabled" wire:target="accept, refuse" class="sm:flex-1">Refuser</x-button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($proposals->isNotEmpty())
        <section aria-labelledby="mes-responsabilites" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-card p-4">
            <h2 id="mes-responsabilites" class="text-[18px] font-extrabold">Responsabilités proposées</h2>
            <p class="text-[13px] text-muted">Une responsabilité ne vous est confiée qu’une fois acceptée. Tant que vous n’avez pas répondu, elle reste « en attente ».</p>
            @foreach ($proposals as $proposal)
                <div wire:key="poste-{{ $proposal->id }}" class="flex flex-col gap-2 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                    <p class="text-[15px] font-extrabold">{{ $proposal->duty->label() }}</p>
                    <p class="text-[13px] text-muted">Dans <a href="{{ route('zumras.show', $proposal->zumra) }}" class="font-semibold">{{ $proposal->zumra->name }}</a>. Ce poste ne donne aucun pouvoir supplémentaire.</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button type="button" wire:click="acceptDuty('{{ $proposal->id }}')" wire:loading.attr="disabled" wire:target="acceptDuty, declineDuty" class="sm:flex-1">Accepter</x-button>
                        <x-button type="button" variant="secondary" wire:click="declineDuty('{{ $proposal->id }}')" wire:loading.attr="disabled" wire:target="acceptDuty, declineDuty" class="sm:flex-1">Refuser</x-button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section aria-labelledby="mes-communautes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
        <h2 id="mes-communautes" class="text-[18px] font-extrabold">Mes communautés</h2>
        @forelse ($memberships as $membership)
            @php $zumra = $membership->zumra; $members = $counts[$zumra->id] ?? 0; @endphp
            <div wire:key="mienne-{{ $zumra->id }}" class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                <x-zumra-initials :zumra="$zumra" :size="48" />
                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="break-words text-[16px] font-extrabold">{{ $zumra->name }}</span>
                    <span class="text-[13px] text-muted">{{ collect([$zumra->sphere?->label(), $zumra->domain->name, $zumra->city?->label(), $zumra->format->label()])->filter()->implode(' · ') }}</span>
                    <span class="text-[13px] font-semibold">{{ $membership->role === \App\Enums\ZumraRole::Founder ? 'Responsable principal' : $membership->role->label() }}@if ($membership->dutyEffective()) · {{ $membership->duty->label() }}@endif · {{ $members }} {{ $members > 1 ? 'membres' : 'membre' }}</span>
                </div>
                <x-button :href="route('zumras.show', $zumra)" variant="secondary" class="w-full sm:w-auto">Ouvrir l’espace</x-button>
            </div>
        @empty
            <p class="text-[14px] text-muted">Vous n’êtes dans aucune ZUMRA pour l’instant. Découvrez les communautés ou créez la vôtre : vous pouvez la lancer seul.</p>
        @endforelse
    </section>

    @if ($requests->isNotEmpty())
        <section aria-labelledby="mes-demandes" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
            <h2 id="mes-demandes" class="text-[18px] font-extrabold">Mes demandes en attente</h2>
            @foreach ($requests as $request)
                <p wire:key="demande-{{ $request->id }}" class="text-[14px]"><a href="{{ route('zumras.show', $request->zumra) }}" class="font-extrabold">{{ $request->zumra->name }}</a> : un responsable y répondra.</p>
            @endforeach
        </section>
    @endif
</div>
