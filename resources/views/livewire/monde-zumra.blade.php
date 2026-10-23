@php
    $meta = collect([$zumra->domain->name, $zumra->city?->label(), $zumra->format->label()])->filter()->implode(' · ');
    $since = $zumra->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('M Y');
    $manages = $role?->manages();
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

    <nav aria-label="Onglets de la ZUMRA" class="-mx-4 flex border-b border-line bg-card px-2 md:mx-0 md:rounded-card md:border">
        <span aria-current="page" class="flex min-h-12 items-center border-b-[3px] border-accent px-3 text-[14px] font-extrabold text-ink">Membres</span>
    </nav>

    <section aria-labelledby="membres" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="membres" class="text-[17px] font-extrabold">Membres</h2>

        @if ($canSeeMembers)
            <ul class="flex flex-col divide-y divide-line-soft">
                @foreach ($members as $membership)
                    @php
                        $person = $membership->user;
                        $profile = $person->profile;
                        $profile?->setRelation('user', $person);
                        $canSee = $profile && Gate::forUser($viewer)->allows('view', $profile);
                    @endphp
                    <li wire:key="membre-{{ $membership->id }}" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
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
</div>
