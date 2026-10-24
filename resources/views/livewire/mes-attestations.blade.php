<div class="flex min-w-0 flex-col gap-5">
    <a href="{{ route('espace') }}" class="inline-flex min-h-11 items-center gap-2 self-start font-bold"><x-icon name="back" :size="18" />Mon espace</a>
    <div><h1 class="text-[26px] font-extrabold">Mes attestations</h1><p class="mt-2 text-[14px] text-muted">Une identité contrôlée, des expériences précises. Aucun badge ne garantit l’absence d’arnaque.</p></div>
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @foreach ($errors->all() as $error)<p role="alert" class="rounded-button border border-danger bg-card p-3 text-[14px] text-danger">{{ $error }}</p>@endforeach
    <p wire:loading role="status" class="text-[13px] text-muted">Enregistrement…</p>
    <section class="rounded-card border border-line bg-card p-5">
        <h2 class="text-[20px] font-extrabold">Mon identité</h2>
        <p class="mt-3 text-[14px] leading-relaxed">Le contrôle humain rapproche votre nom affiché et un justificatif avec votre personne, en présence ou en échange direct. Aucun diplôme n’est demandé et aucune copie de pièce n’est conservée ici. N’envoyez pas de pièce d’identité dans les publications ou la messagerie.</p>
        @if ($identity)
            <p class="mt-4 font-bold">{{ $identity->state === 'verified' && ! $currentIdentity ? 'Vérification expirée ou devenue invalide' : \App\Models\IdentityVerification::STATES[$identity->state] }}</p>
            @if ($currentIdentity)<p class="mt-2 text-[13px] text-muted">Contrôle du {{ $currentIdentity->verified_at->format('d/m/Y') }} · valable jusqu’au {{ $currentIdentity->expires_at->format('d/m/Y') }}. Cette mention concerne l’identité, pas la qualité des prestations.</p>@endif
            @if ($identity->instruction)<div class="mt-3 rounded-button bg-soft-blue p-3"><h3 class="text-[14px] font-bold">Modalités du contrôle</h3><p class="mt-1 whitespace-pre-line break-words text-[14px]">{{ $identity->instruction }}</p></div>@endif
            @if ($identity->reason)<p class="mt-3 whitespace-pre-line break-words text-[14px] text-muted">{{ $identity->reason }}</p>@endif
            @if (in_array($identity->state, ['pending', 'verified'], true))<x-button type="button" variant="secondary" class="mt-3" wire:click="withdrawIdentity({{ $identity->revision }})" wire:loading.attr="disabled" wire:confirm="Retirer votre demande ou votre vérification ? La mention disparaîtra du profil.">Retirer mon consentement</x-button>@endif
            @if (in_array($identity->state, ['declined', 'revoked'], true))<a href="{{ route('personnes.signaler', ['profile' => $user->profile->public_id, 'categorie' => 'appeal']) }}" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Contester cette décision →</a>@endif
        @endif
        @if (! $identity || (! $currentIdentity && $identity->state !== 'pending'))
            <form wire:submit="requestIdentity" class="mt-4 flex flex-col gap-3">
                <label class="flex min-h-11 cursor-pointer items-start gap-3 py-2"><input type="checkbox" wire:model="consent" class="mt-1 size-5 shrink-0 accent-ink"><span class="text-[14px]">Je demande un contrôle humain et j’accepte l’affichage de la mention et de ses dates sur mon profil, selon sa visibilité.</span></label>
                <x-button wire:loading.attr="disabled">Demander la vérification de mon identité</x-button>
                <p class="text-[12px] text-muted">GAMAD précisera ici les modalités. Après un refus ou un retrait, une nouvelle demande est possible après sept jours ; un recours reste possible immédiatement.</p>
            </form>
        @endif
    </section>
    <section class="flex min-w-0 flex-col gap-4 rounded-card border border-line bg-card p-5">
        <h2 class="text-[20px] font-extrabold">Mes confirmations d’expérience</h2>
        <p class="text-[14px] text-muted">Depuis une publication « Ma réalisation », son auteur peut solliciter une relation dont l’identité est vérifiée. Une confirmation publie le texte accepté, le nom et le rôle du témoin selon les droits de visibilité.</p>
        @forelse ($works as $row)
            <article wire:key="work-{{ $row->id }}-{{ $row->revision }}" class="min-w-0 rounded-button border border-line p-4">
                <p class="text-[14px] font-bold">{{ \App\Models\WorkAttestation::STATES[$row->state] }} · {{ $row->attester_id === $user->id ? 'Sollicitation de '.$row->author->name : 'Demandée à '.$row->attester->name }}</p>
                <p class="mt-3 whitespace-pre-line break-words text-[15px]">{{ $row->claim }}</p><p class="mt-2 text-[13px] text-muted">Intervention du {{ $row->performed_on->format('d/m/Y') }}</p>
                @if ($row->reason)<p class="mt-2 whitespace-pre-line break-words text-[13px] text-muted">{{ $row->reason }}</p>@endif
                @can('view', $row->publication)<a href="{{ route('publications.show', $row->publication_id) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Consulter la réalisation →</a>@endcan
                @if ($row->attester_id === $user->id && ($row->state === 'pending' || ($row->state === 'confirmed' && $currentIdentity && $row->identity_revision !== $currentIdentity->revision)))
                    <p class="mt-2 text-[13px] text-muted">Votre identité doit être vérifiée au moment de confirmer. Une nouvelle vérification exige une nouvelle confirmation.</p>
                    <label for="role-{{ $row->id }}" class="mt-3 block text-[13px] font-bold">Mon rôle réel lors de cette intervention</label>
                    <select id="role-{{ $row->id }}" wire:model="roles.{{ $row->id }}" class="mt-2 min-h-12 w-full rounded-button border border-chip-line bg-card px-3"><option value="">Choisir</option>@foreach (\App\Models\WorkAttestation::ROLES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <label class="mt-2 flex min-h-11 cursor-pointer items-start gap-3 py-2"><input type="checkbox" wire:model="consents.{{ $row->id }}" class="mt-1 size-5 shrink-0 accent-ink"><span class="text-[14px]">Je confirme avoir personnellement constaté l’intervention décrite, à cette date, et j’accepte l’affichage de cette confirmation.</span></label>
                    <div class="mt-2 flex flex-wrap gap-2"><x-button type="button" wire:click="decide('{{ $row->id }}', {{ $row->revision }}, 'confirm')" wire:loading.attr="disabled">Confirmer cette expérience</x-button>@if ($row->state === 'pending')<x-button type="button" variant="secondary" wire:click="decide('{{ $row->id }}', {{ $row->revision }}, 'decline')" wire:loading.attr="disabled">Décliner</x-button>@endif</div>
                @endif
                @if (in_array($row->state, ['pending', 'confirmed'], true))<x-button type="button" variant="secondary" class="mt-3" wire:click="decide('{{ $row->id }}', {{ $row->revision }}, 'withdraw')" wire:loading.attr="disabled" wire:confirm="Retirer votre consentement ? Cette référence ne sera plus affichée.">Retirer mon consentement</x-button>@endif
                @if ($row->state === 'revoked')<a href="{{ route('personnes.signaler', ['profile' => $row->author->profile->public_id, 'reference' => $row->id, 'categorie' => 'appeal']) }}" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-bold">Déposer un recours →</a>@endif
            </article>
        @empty<p class="text-[14px] text-muted">Aucune confirmation demandée ou reçue pour le moment.</p>@endforelse
    </section>
    <section class="rounded-card border border-line bg-card p-5"><h2 class="text-[20px] font-extrabold">Mes signalements et recours</h2>
        @forelse ($reports as $report)<article class="mt-3 border-t border-line pt-3"><p class="text-[14px] font-bold">{{ \App\Models\TrustReport::CATEGORIES[$report->category] }} · {{ $report->state === 'open' ? 'En cours d’examen' : 'Réponse reçue' }}</p><p class="mt-2 whitespace-pre-line break-words text-[14px]">{{ $report->details }}</p>@if ($report->response)<p class="mt-2 whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px]">{{ $report->response }}</p>@endif</article>@empty<p class="mt-3 text-[14px] text-muted">Aucun signalement ni recours déposé.</p>@endforelse
    </section>
    @if ($limit < 200 && ($works->count() >= $limit || $reports->count() >= $limit))<x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled">Afficher davantage</x-button>@endif
</div>
