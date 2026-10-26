@php
    $fcfa = fn ($n) => $n === null ? '—' : number_format($n, 0, ',', ' ').' FCFA';
    $date = fn ($d) => $d?->copy()->locale('fr')->translatedFormat('j M Y');
@endphp
<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace', ['rubrique' => 'moderation']) }}" aria-label="Retour à la modération" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Modération des annonces</h1>
    </div>

    @include('livewire.partials.moderation-nav', ['current' => 'annonces'])

    <p class="text-[13px] leading-normal text-muted">Les annonces En examen, de la plus ancienne à la plus récente. Aucune annonce n’est diffusée avant votre décision. Un refus exige un motif que l’annonceur lit ; chaque décision est inscrite au journal d’administration.</p>

    @if ($message)<x-status :tone="$messageIsError ? 'error' : 'info'">{{ $message }}</x-status>@endif

    <div wire:loading.delay class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    <ol class="flex flex-col gap-3">
        @forelse ($items as $c)
            <li wire:key="annonce-{{ $c->id }}" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-[12.5px] text-muted">
                    <span><strong class="text-ink">{{ $c->advertiserName() }}</strong>@if ($c->company) · entreprise vérifiée @endif · envoyée le {{ $date($c->submitted_at) }}</span>
                    <span>{{ $c->objective?->label() }} · {{ $fcfa($c->budget_fcfa) }} prévus · {{ $date($c->starts_on) }} – {{ $date($c->ends_on) }}</span>
                </div>

                <div class="max-w-sm">
                    @include('livewire.partials.publicite-apercu', ['name' => $c->advertiserName(), 'verified' => (bool) $c->company, 'title' => (string) $c->title, 'body' => (string) $c->body, 'imageUrl' => $c->image_path ? route('publicite.image', $c) : null, 'ctaLabel' => $c->cta?->label() ?? ''])
                </div>

                <dl class="grid gap-1 text-[13px]">
                    @if ($c->destination_url)<div class="flex gap-2"><dt class="font-bold">Destination</dt><dd class="min-w-0 break-all text-muted">{{ $c->destination_url }}</dd></div>@endif
                    <div class="flex gap-2"><dt class="font-bold">Audience</dt><dd class="text-muted">
                        @if ($c->isPersonalized())
                            Personnalisée
                            @if ($c->skills->isNotEmpty()) · compétences : {{ $c->skills->pluck('name')->implode(', ') }} @endif
                            @if ($c->domains->isNotEmpty()) · domaines : {{ $c->domains->pluck('name')->implode(', ') }} @endif
                            @if ($c->cities->isNotEmpty() || $c->remote) · lieux : {{ $c->cities->map->label()->implode(', ') }}{{ $c->remote ? ($c->cities->isNotEmpty() ? ', à distance' : 'à distance') : '' }}@endif
                        @else
                            Contextuelle (aucun profil utilisé)
                        @endif
                    </dd></div>
                </dl>

                @if ($c->user_id === $me)
                    <p class="rounded-button bg-line-soft px-3 py-2 text-[13px] font-semibold text-muted">C’est votre annonce : une autre personne de la modération la décide.</p>
                @else
                    <div class="flex flex-col gap-1.5">
                        <label for="motif-{{ $c->id }}" class="text-[13px] font-bold">Motif (obligatoire pour refuser)</label>
                        <textarea id="motif-{{ $c->id }}" wire:model="reasons.{{ $c->id }}" rows="2" maxlength="280" class="w-full rounded-button border border-chip-line bg-soft-blue px-3 py-2.5 text-[14px]" @error('reason-'.$c->id) aria-invalid="true" aria-describedby="err-{{ $c->id }}" @enderror></textarea>
                        @error('reason-'.$c->id)<p id="err-{{ $c->id }}" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button type="button" wire:click="accept('{{ $c->id }}')" wire:loading.attr="disabled">Accepter</x-button>
                        <x-button type="button" variant="secondary" wire:click="refuse('{{ $c->id }}')" wire:loading.attr="disabled">Refuser</x-button>
                    </div>
                @endif
            </li>
        @empty
            <li class="rounded-card border border-line bg-card p-5">
                <p class="text-[16px] font-extrabold">Aucune annonce à examiner.</p>
                <p class="mt-1 text-muted">Les annonces envoyées par leurs annonceurs apparaissent ici.</p>
            </li>
        @endforelse
    </ol>

    @if ($reported->isNotEmpty())
        <h2 class="mt-2 text-[17px] font-extrabold">Annonces signalées</h2>
        <p class="-mt-1 text-[13px] text-muted">Des personnes ont signalé ces annonces déjà acceptées. Les retirer de la diffusion exige un motif ; l’annonceur le lit.</p>
        <ul class="flex flex-col gap-3">
            @foreach ($reported as $c)
                <li wire:key="signalee-{{ $c->id }}" class="flex flex-col gap-2.5 rounded-card border-2 border-ink bg-card p-4">
                    <p class="text-[14px]"><strong>{{ $c->title }}</strong> · {{ $c->advertiserName() }}</p>
                    <p class="text-[13px] text-muted">Signalements : @foreach ($c->reports as $reason => $total){{ \App\Services\Publicite\AdFeedback::REASONS[$reason] ?? $reason }} ({{ $total }}){{ $loop->last ? '' : ', ' }}@endforeach</p>
                    @if ($c->user_id !== $me)
                        <label for="retrait-{{ $c->id }}" class="text-[13px] font-bold">Motif du retrait</label>
                        <textarea id="retrait-{{ $c->id }}" wire:model="reasons.{{ $c->id }}" rows="2" maxlength="280" class="w-full rounded-button border border-chip-line bg-soft-blue px-3 py-2.5 text-[14px]"></textarea>
                        @error('reason-'.$c->id)<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                        <x-button type="button" variant="secondary" wire:click="revoke('{{ $c->id }}')" wire:loading.attr="disabled" class="self-start">Retirer de la diffusion</x-button>
                    @else
                        <p class="text-[13px] font-semibold text-muted">C’est votre annonce : une autre personne de la modération la traite.</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
