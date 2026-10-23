@php
    $zumra = $project->zumra;
    $meta = collect([$zumra->domain->name, $zumra->city?->label()])->filter()->implode(' · ');
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
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

        <p class="whitespace-pre-line break-words text-[15px] leading-relaxed text-[#eaf0f5]">{{ $project->goal }}</p>

        @if (session('status'))
            <x-status>{{ session('status') }}</x-status>
        @endif

        @if ($manages)
            <x-button :href="route('projets.edit', $project)" variant="accent">Modifier le projet</x-button>
        @endif
    </header>

    <section aria-labelledby="avancement" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="avancement" class="text-[17px] font-extrabold">Avancement</h2>
        <p class="text-[15px]"><strong>{{ $project->state->label() }}</strong> depuis le {{ $date($project->state_changed_at) }}.</p>

        <ol class="flex flex-col gap-2.5 border-t border-line-soft pt-3" aria-label="Histoire du projet">
            @foreach ($changes as $change)
                <li wire:key="changement-{{ $change->id }}" class="flex flex-col text-[14px]">
                    <span class="font-bold">{{ $change->from_state ? 'Passé de « '.$change->from_state->label().' » à « '.$change->to_state->label().' »' : 'Projet créé comme une idée' }}</span>
                    <span class="text-[12px] text-muted">{{ $date($change->created_at) }}@if ($change->user) · par {{ $change->user->name }}@endif</span>
                </li>
            @endforeach
        </ol>
    </section>

    @if ($manages)
        <section aria-labelledby="etat" class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4" x-data="{ confirming: null }">
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
