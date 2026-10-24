<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-[28px] font-extrabold">Vérification des entreprises</h1><p class="mt-2 text-[14px] text-muted">Contrôle manuel des immatriculations et des pièces. Ne vérifie pas l'identité des gestionnaires.</p></div>
        <a href="{{ route('entreprises.index') }}" class="inline-flex min-h-11 items-center font-bold text-link">Voir l'annuaire →</a>
    </div>
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
    @foreach ($errors->all() as $error)<p role="alert" class="rounded-button border border-danger bg-card p-3 text-[13px] text-danger">{{ $error }}</p>@endforeach
    <section class="rounded-card border border-line bg-card p-5">
        <h2 class="text-[19px] font-extrabold">Recours et signalements</h2>
        <div class="mt-3 space-y-3">
            @forelse ($appeals as $appeal)
                <article wire:key="company-appeal-{{ $appeal->id }}" class="rounded-button border border-line p-4">
                    <p class="text-[14px] font-bold">{{ $appeal->company?->name }} · {{ $appeal->kind === 'report' ? 'Signalement' : 'Recours' }}</p>
                    <p class="mt-1 whitespace-pre-line break-words text-[13px]">{{ $appeal->reason }}</p>
                    <p class="mt-1 text-[12px] text-muted">Demandeur : {{ $appeal->requester?->name ?? 'Compte supprimé' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-button type="button" wire:click="decideAppeal('{{ $appeal->id }}', 'accept')" wire:confirm="Un recours accepté ouvre un nouvel examen. Un signalement accepté suspend la page." wire:loading.attr="disabled" variant="accent">Accepter et agir</x-button>
                        <x-button type="button" wire:click="decideAppeal('{{ $appeal->id }}', 'reject')" wire:loading.attr="disabled" variant="secondary">Classer sans suite</x-button>
                    </div>
                </article>
            @empty
                <p class="text-[13px] text-muted">Aucun dossier en attente.</p>
            @endforelse
        </div>
    </section>
    <div class="grid gap-4">
        @forelse ($companies as $company)
            <article wire:key="company-review-{{ $company->id }}-{{ $company->revision }}" class="rounded-card border border-line bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[19px] font-extrabold">{{ $company->name }}</h2>
                        <p class="mt-1 text-[13px] text-muted">{{ $company->legal_name }} · {{ $company->registration_country }} · {{ $company->registration_number }}</p>
                        <p class="mt-1 text-[13px] text-muted">Demandeur : {{ $company->owner?->name }} · {{ $company->sector }} · {{ $company->city }}</p>
                    </div>
                    <span class="rounded-chip px-3 py-1.5 text-[12px] font-bold {{ $company->verified() ? 'bg-type-knowledge-bg text-type-knowledge-fg' : 'bg-type-call-bg text-type-call-fg' }}">{{ $company->verified() ? 'Immatriculation vérifiée' : 'À contrôler' }}</span>
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($company->documents as $document)
                        <a href="{{ route('entreprises.document', [$company, $document]) }}" class="inline-flex min-h-11 items-center gap-2 rounded-button border border-line px-3 text-[13px] font-bold"><x-icon name="document" :size="17"/> Télécharger {{ strtoupper($document->kind) }}</a>
                    @endforeach
                </div>
                <p class="mt-3 text-[12px] text-muted">Comparer le RCCM et les identifiants avec le registre officiel du pays. Refuser si l'immatriculation est incohérente ou introuvable.</p>
                <label class="mt-4 flex flex-col gap-1.5 text-[13px] font-bold">Note de contrôle / motif de refus<textarea wire:model="notes.{{ $company->id }}" rows="2" maxlength="500" class="rounded-button border border-chip-line p-3" placeholder="Motif requis en cas de refus ou de suspension"></textarea></label>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($company->status === 'pending')
                        <x-button type="button" variant="accent" wire:click="review('{{ $company->id }}', {{ $company->revision }}, 'verify')" wire:confirm="Avez-vous contrôlé l'immatriculation auprès du registre compétent ?" wire:loading.attr="disabled">Valider l'immatriculation</x-button>
                        <x-button type="button" variant="secondary" wire:click="review('{{ $company->id }}', {{ $company->revision }}, 'decline')" wire:loading.attr="disabled">Refuser avec motif</x-button>
                    @else
                        <x-button type="button" variant="secondary" wire:click="review('{{ $company->id }}', {{ $company->revision }}, 'suspend')" wire:confirm="Suspendre publiquement cette page entreprise ?" wire:loading.attr="disabled">Suspendre avec motif</x-button>
                    @endif
                    <a class="inline-flex min-h-11 items-center px-2 text-[13px] font-bold text-link" href="{{ route('entreprises.show', $company) }}">Aperçu →</a>
                </div>
            </article>
        @empty
            <section class="rounded-card border border-line bg-card p-6"><h2 class="text-[17px] font-extrabold">Aucun dossier à contrôler.</h2></section>
        @endforelse
    </div>
</div>
