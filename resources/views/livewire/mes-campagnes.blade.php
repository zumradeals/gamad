@php
    $date = fn ($d) => $d?->copy()->locale('fr')->translatedFormat('j M');
    $period = fn ($c) => $c->starts_on && $c->ends_on ? $date($c->starts_on).' – '.$date($c->ends_on) : '—';
    $fcfa = fn ($n) => $n === null ? '—' : number_format($n, 0, ',', ' ').' FCFA';
    $btn = 'inline-flex min-h-11 items-center rounded-button border-[1.5px] border-chip-line bg-card px-3.5 text-[13px] font-bold';
    $btnPrimary = 'inline-flex min-h-11 items-center rounded-button border-[1.5px] border-ink bg-card px-3.5 text-[13px] font-bold';
@endphp
<div class="flex flex-col gap-4">
    @include('livewire.partials.publicite-entete', ['current' => 'campagnes'])

    <x-status class="!border-type-call-bg !bg-type-call-bg !text-type-call-fg"><strong>Budget prévisionnel.</strong> Aucune diffusion payante n’est activée avant la validation du dispositif d’encaissement : une campagne acceptée par la modération reste « Prête ».</x-status>

    @if ($message)
        <x-status :tone="$messageIsError ? 'error' : 'info'">{{ $message }}</x-status>
    @endif

    @if ($confirmStop)
        <div role="alertdialog" aria-labelledby="stop-titre" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-card p-4">
            <h2 id="stop-titre" class="text-[18px] font-extrabold">Arrêter cette campagne ?</h2>
            <p class="text-[14px] text-muted">Elle ne sera plus diffusée. <strong class="text-ink">L’arrêt est définitif</strong> : vous pourrez la dupliquer pour en créer une nouvelle. Si vous voulez seulement l’interrompre, mettez-la plutôt en pause.</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button type="button" wire:click="stop('{{ $confirmStop }}')" class="!border-danger !bg-danger">Arrêter définitivement</x-button>
                <x-button type="button" variant="secondary" wire:click="cancelStop">Annuler</x-button>
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0" role="group" aria-label="Filtrer par état">
            <button type="button" wire:click="setFilter('')" @if ($filter === '') aria-pressed="true" @endif @class(['inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-chip border px-3.5 text-[13px] font-semibold', 'border-link bg-soft-blue text-link' => $filter === '', 'border-chip-line bg-card' => $filter !== ''])>Toutes · {{ $total }}</button>
            @foreach ($states as $state)
                @if (($counts[$state->value] ?? 0) > 0)
                    <button type="button" wire:click="setFilter('{{ $state->value }}')" @if ($filter === $state->value) aria-pressed="true" @endif @class(['inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-chip border px-3.5 text-[13px] font-semibold', 'border-link bg-soft-blue text-link' => $filter === $state->value, 'border-chip-line bg-card' => $filter !== $state->value])>{{ $state->label() }} · {{ $counts[$state->value] }}</button>
                @endif
            @endforeach
        </div>
        @if (\Illuminate\Support\Facades\Route::has('publicite.creer'))<x-button :href="route('publicite.creer')" class="max-md:w-full">Créer une campagne</x-button>@endif
    </div>

    <div wire:loading.delay class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    @if ($total === 0)
        <section class="flex flex-col items-start gap-3 rounded-card border border-line bg-card p-5">
            <h2 class="text-[18px] font-extrabold">Aucune campagne pour l’instant</h2>
            <p class="text-[14px] text-muted">Créez votre première annonce : elle est relue par la modération avant toute diffusion.</p>
            @if (\Illuminate\Support\Facades\Route::has('publicite.creer'))<x-button :href="route('publicite.creer')">Créer une campagne</x-button>@endif
        </section>
    @elseif ($items->isEmpty())
        <p class="rounded-card border border-line bg-card p-5 text-muted">Aucune campagne dans cet état.</p>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($items as $c)
                @php $state = $c->displayState(); @endphp
                <li wire:key="campagne-{{ $c->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:flex-row md:items-center md:gap-4">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-button bg-gradient-to-br from-accent to-soft-blue text-ink/50" aria-hidden="true">
                            @if ($c->image_path)<img src="{{ route('publicite.image', $c) }}" alt="" class="size-full object-cover">@else<x-icon name="megaphone" :size="22" />@endif
                        </span>
                        <span class="min-w-0">
                            <strong class="block truncate text-[14.5px]">{{ $c->title ?: 'Campagne sans titre' }}</strong>
                            <span class="block text-[12.5px] text-muted">{{ $c->objective?->label() ?? 'Objectif à choisir' }}@if ($c->company) · {{ $c->company->name }}@endif</span>
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-[13px] md:w-[290px] md:shrink-0">
                        <span class="inline-flex min-h-[26px] items-center rounded-chip px-2.5 text-[12px] font-extrabold {{ $state->badge() }}">{{ $state->label() }}</span>
                        <span><strong>{{ $fcfa($c->budget_fcfa) }}</strong> <span class="text-muted">prévus</span></span>
                        <span class="text-muted">{{ $period($c) }}</span>
                    </div>
                    @if ($state === \App\Enums\AdState::Refused && $c->refusal_reason)
                        <p class="rounded-button bg-type-need-bg px-3 py-2 text-[13px] text-type-need-fg md:hidden"><strong>Motif :</strong> {{ $c->refusal_reason }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2 md:w-[250px] md:shrink-0 md:justify-end">
                        @switch($state)
                            @case(\App\Enums\AdState::Draft)
                                <a href="{{ route('publicite.modifier', $c) }}" class="{{ $btnPrimary }}">Modifier</a>
                                <button type="button" class="{{ $btn }}" wire:click="delete('{{ $c->id }}')" wire:confirm="Supprimer ce brouillon ?">Supprimer</button>
                                @break
                            @case(\App\Enums\AdState::Review)
                                <button type="button" class="{{ $btn }}" wire:click="withdraw('{{ $c->id }}')">Retirer</button>
                                @break
                            @case(\App\Enums\AdState::Refused)
                                <a href="{{ route('publicite.modifier', $c) }}" class="{{ $btnPrimary }}">Modifier</a>
                                @break
                            @case(\App\Enums\AdState::Ready)
                                <a href="{{ route('publicite.modifier', $c) }}" class="{{ $btnPrimary }}">Modifier</a>
                                <button type="button" class="{{ $btn }}" wire:click="askStop('{{ $c->id }}')">Arrêter</button>
                                @break
                            @case(\App\Enums\AdState::Active)
                                <button type="button" class="{{ $btnPrimary }}" wire:click="pause('{{ $c->id }}')">Pause</button>
                                <a href="{{ route('publicite.modifier', $c) }}" class="{{ $btn }}">Modifier</a>
                                <button type="button" class="{{ $btn }}" wire:click="askStop('{{ $c->id }}')">Arrêter</button>
                                @break
                            @case(\App\Enums\AdState::Paused)
                                <button type="button" class="{{ $btnPrimary }}" wire:click="resume('{{ $c->id }}')">Reprendre</button>
                                <button type="button" class="{{ $btn }}" wire:click="askStop('{{ $c->id }}')">Arrêter</button>
                                @break
                            @case(\App\Enums\AdState::Done)
                                <button type="button" class="{{ $btnPrimary }}" wire:click="duplicate('{{ $c->id }}')">Dupliquer</button>
                                @break
                        @endswitch
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <section aria-labelledby="etats" class="rounded-card border border-line bg-card p-4 md:p-5">
        <h2 id="etats" class="text-[16px] font-extrabold">Les états d’une campagne</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-2 text-[13px] md:grid-cols-2">
            @foreach ([['draft', 'Vous la complétez, rien n’est envoyé.'], ['review', 'Relue par la modération avant toute diffusion.'], ['refused', 'Le motif est indiqué ; vous corrigez et renvoyez.'], ['ready', 'Acceptée ; en attente de la diffusion (date de début, ou diffusion payante pas encore ouverte).'], ['active', 'Diffusée dans les espaces choisis.'], ['paused', 'Suspendue par vous ; reprise en un geste.'], ['done', 'Durée écoulée ou campagne arrêtée.']] as [$key, $text])
                @php $s = \App\Enums\AdState::from($key); @endphp
                <div class="flex items-start gap-2.5"><dt class="inline-flex min-h-[26px] shrink-0 items-center rounded-chip px-2.5 text-[12px] font-extrabold {{ $s->badge() }}">{{ $s->label() }}</dt><dd class="pt-0.5 text-muted">{{ $text }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-3 text-[12px] text-muted">Modifier une annonce déjà acceptée la renvoie à la modération. Arrêter est définitif ; mettre en pause ne l’est pas.</p>
    </section>
</div>
