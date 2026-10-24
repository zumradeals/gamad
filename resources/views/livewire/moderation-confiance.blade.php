<div class="flex min-w-0 flex-col gap-5">
    <h1 class="text-[26px] font-extrabold">Confiance et attestations</h1>
    @include('livewire.partials.moderation-nav', ['current' => 'confiance'])
    <p class="text-[14px] text-muted">Contrôles humains, références et recours. Aucun acte sur votre propre dossier. Ne recopiez aucune pièce ni numéro de document dans les motifs.</p>
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @foreach ($errors->all() as $error)<p role="alert" class="rounded-button border border-danger bg-card p-3 text-[14px] text-danger">{{ $error }}</p>@endforeach
    <p wire:loading role="status" class="text-[13px] text-muted">Enregistrement…</p>
    <label for="trust-search" class="text-[14px] font-bold">Rechercher une personne dans les identités et références</label><input id="trust-search" wire:model.live.debounce.400ms="search" maxlength="100" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3">
    <section class="flex min-w-0 flex-col gap-4"><h2 class="text-[21px] font-extrabold">Contrôles d’identité</h2>
        @forelse ($identities as $row)
            <article wire:key="identity-{{ $row->id }}-{{ $row->revision }}" class="rounded-card border border-line bg-card p-5">
                <h3 class="break-words text-[18px] font-extrabold">{{ $row->user->name }}</h3><p class="mt-2 text-[14px] font-bold">{{ \App\Models\IdentityVerification::STATES[$row->state] }}</p>
                <p class="mt-2 break-words text-[13px] text-muted">Nom demandé : {{ $row->checked_name }}. Dossier ouvert le {{ $row->created_at->format('d/m/Y') }}.</p>
                @if ($row->reason)<p class="mt-2 whitespace-pre-line break-words text-[13px]">{{ $row->reason }}</p>@endif
                @if ($row->instruction)<p class="mt-2 whitespace-pre-line break-words text-[13px]">{{ $row->instruction }}</p>@endif
                @can('review', $row)
                    @if (in_array($row->state, ['pending', 'verified', 'declined', 'revoked'], true))
                        <label for="identity-reason-{{ $row->id }}" class="mt-3 block text-[13px] font-bold">Modalités à communiquer ou motif de décision (500 caractères)</label><textarea id="identity-reason-{{ $row->id }}" wire:model="reasons.{{ $row->id }}" maxlength="500" rows="3" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3"></textarea>
                    @endif
                    @if ($row->state === 'pending')
                        <label for="identity-method-{{ $row->id }}" class="mt-3 block text-[13px] font-bold">Contrôle réellement effectué</label><select id="identity-method-{{ $row->id }}" wire:model="methods.{{ $row->id }}" class="mt-2 min-h-12 w-full rounded-button border border-chip-line bg-card px-3"><option value="">Choisir après le contrôle</option><option value="in_person">En présence</option><option value="live_review">Échange en direct</option></select>
                        <label class="mt-2 flex min-h-11 cursor-pointer items-start gap-3 py-2"><input type="checkbox" wire:model="namesChecked.{{ $row->id }}" class="mt-1 size-5 shrink-0 accent-ink"><span class="text-[14px]">J’ai rapproché le nom affiché d’un justificatif adapté, sans en conserver de copie.</span></label>
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 py-2"><input type="checkbox" wire:model="personsChecked.{{ $row->id }}" class="mt-1 size-5 shrink-0 accent-ink"><span class="text-[14px]">J’ai effectivement contrôlé que le justificatif correspond à la personne présente.</span></label>
                        <div class="mt-3 flex flex-wrap gap-2"><x-button type="button" wire:click="identity('{{ $row->id }}', {{ $row->revision }}, 'verify')" wire:loading.attr="disabled" wire:confirm="Attester ce contrôle humain d’identité ? Cette mention sera visible sur le profil pendant un an.">Attester le contrôle</x-button><x-button type="button" variant="secondary" wire:click="identity('{{ $row->id }}', {{ $row->revision }}, 'instruct')" wire:loading.attr="disabled">Préciser les modalités</x-button><x-button type="button" variant="secondary" wire:click="identity('{{ $row->id }}', {{ $row->revision }}, 'decline')" wire:loading.attr="disabled">Refuser avec motif</x-button></div>
                    @elseif ($row->state === 'verified')
                        <x-button type="button" variant="secondary" class="mt-3" wire:click="identity('{{ $row->id }}', {{ $row->revision }}, 'revoke')" wire:loading.attr="disabled" wire:confirm="Retirer cette vérification avec le motif saisi ?">Retirer la vérification</x-button>
                    @elseif (in_array($row->state, ['declined', 'revoked'], true))
                        <x-button type="button" variant="secondary" class="mt-3" wire:click="identity('{{ $row->id }}', {{ $row->revision }}, 'reopen')" wire:loading.attr="disabled">Réexaminer après un recours</x-button>
                    @endif
                @endcan
            </article>
        @empty<p class="rounded-card border border-line bg-card p-5 text-[14px] text-muted">Aucun dossier d’identité à afficher.</p>@endforelse
    </section>
    <section class="flex min-w-0 flex-col gap-4"><h2 class="text-[21px] font-extrabold">Signalements et recours ouverts</h2>
        @forelse ($reports as $row)
            <article wire:key="report-{{ $row->id }}" class="rounded-card border border-line bg-card p-5">
                <h3 class="break-words text-[17px] font-extrabold">{{ \App\Models\TrustReport::CATEGORIES[$row->category] }} · {{ $row->subject->name }}</h3><p class="mt-2 text-[12px] text-muted">Déposé par {{ $row->reporter->name }} · réservé à cet examen.</p><p class="mt-3 whitespace-pre-line break-words text-[14px]">{{ $row->details }}</p>
                @if ($row->workAttestation)
                    <div class="mt-3 rounded-button bg-ground p-3"><p class="whitespace-pre-line break-words text-[14px]">{{ $row->workAttestation->claim }}</p><p class="mt-2 text-[12px] font-bold">{{ \App\Models\WorkAttestation::STATES[$row->workAttestation->state] }}</p></div>
                    @can('review', $row->workAttestation)
                        <label for="reported-work-{{ $row->id }}" class="mt-3 block text-[13px] font-bold">Motif d’une décision sur la référence</label><textarea id="reported-work-{{ $row->id }}" wire:model="reasons.{{ $row->workAttestation->id }}" maxlength="500" rows="2" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3"></textarea>
                        @if ($row->workAttestation->state === 'revoked')<x-button type="button" variant="secondary" class="mt-2" wire:click="reopen('{{ $row->workAttestation->id }}', {{ $row->workAttestation->revision }})" wire:loading.attr="disabled">Réexaminer : redemander la confirmation</x-button>@elseif (in_array($row->workAttestation->state, ['confirmed', 'pending'], true))<x-button type="button" variant="secondary" class="mt-2" wire:click="revoke('{{ $row->workAttestation->id }}', {{ $row->workAttestation->revision }})" wire:loading.attr="disabled" wire:confirm="Retirer cette référence avec le motif saisi ?">Retirer la référence</x-button>@endif
                    @endcan
                @endif
                @can('review', $row)
                    <label for="report-response-{{ $row->id }}" class="mt-3 block text-[13px] font-bold">Réponse au signalant ou au recours (sans divulguer son identité)</label><textarea id="report-response-{{ $row->id }}" wire:model="reasons.{{ $row->id }}" maxlength="500" rows="3" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3"></textarea>
                    <p class="mt-2 text-[12px] text-muted">La clôture communique votre réponse. Un retrait de badge ou de référence se fait par l’action distincte correspondante.</p>
                    <x-button type="button" class="mt-3" wire:click="closeReport('{{ $row->id }}', {{ $row->revision }})" wire:loading.attr="disabled">Répondre et clore</x-button>
                @endcan
            </article>
        @empty<p class="rounded-card border border-line bg-card p-5 text-[14px] text-muted">Aucun signalement ni recours à examiner.</p>@endforelse
    </section>
    <section class="flex min-w-0 flex-col gap-4"><h2 class="text-[21px] font-extrabold">Références récentes</h2>
        @forelse ($works as $row)<article wire:key="review-work-{{ $row->id }}" class="rounded-card border border-line bg-card p-5"><p class="break-words text-[14px] font-bold">{{ $row->author->name }} · Témoin : {{ $row->attester->name }}</p><p class="mt-2 whitespace-pre-line break-words text-[14px]">{{ $row->claim }}</p>@can('review', $row)<label for="work-reason-{{ $row->id }}" class="mt-3 block text-[13px] font-bold">Motif du retrait</label><textarea id="work-reason-{{ $row->id }}" wire:model="reasons.{{ $row->id }}" maxlength="500" rows="2" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3"></textarea><x-button type="button" variant="secondary" class="mt-3" wire:click="revoke('{{ $row->id }}', {{ $row->revision }})" wire:loading.attr="disabled" wire:confirm="Retirer cette référence avec le motif saisi ?">Retirer la référence</x-button>@endcan</article>@empty<p class="rounded-card border border-line bg-card p-5 text-[14px] text-muted">Aucune référence en attente ou confirmée.</p>@endforelse
    </section>
    @if ($limit < 200 && ($identities->count() >= $limit || $reports->count() >= $limit || $works->count() >= $limit))<x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled">Afficher davantage</x-button>@endif
</div>
