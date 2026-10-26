<div class="flex min-w-0 flex-col gap-4" x-data>
    <a href="{{ route('entreprises.opportunities', $opportunity->company) }}" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[13px] font-semibold text-muted no-underline hover:text-ink"><x-icon name="back" :size="18" /> Retour à nos opportunités</a>
    <header class="rounded-card border border-line bg-card p-5">
        <h1 class="text-[22px] font-extrabold">Réponses reçues</h1>
        <p class="mt-1 break-words text-[14px] text-muted">{{ $opportunity->title }} · {{ $opportunity->kind->label() }} · {{ $opportunity->statusLabel() }}</p>
        <p class="mt-2 text-[12px] text-muted">Ces réponses sont privées : seule votre entreprise les voit. Elles ne sont ni notées ni classées.</p>
    </header>
    @if ($message)<x-status>{{ $message }}</x-status>@endif

    <section class="space-y-3" aria-label="Réponses">
        @forelse ($responses as $response)
            @php($person = $response->user)
            <article wire:key="resp-{{ $response->id }}" class="rounded-card border border-line bg-card p-4">
                <div class="flex min-w-0 items-start gap-3">
                    @if ($person->profile)<x-avatar :profile="$person->profile" :size="52" />@endif
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="break-words text-[16px] font-extrabold">{{ $person->name }}</span><span class="rounded-chip bg-soft-blue px-2.5 py-0.5 text-[12px] font-bold text-link">{{ $response->stateLabel() }}</span></p>
                        @if ($person->profile?->headline)<p class="break-words text-[14px] text-muted">{{ $person->profile->headline }}</p>@endif
                        <p class="text-[12px] text-muted">Reçue {{ $response->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->diffForHumans() }}</p>
                    </div>
                </div>
                @if ($response->message)<p class="mt-3 whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px] leading-relaxed">{{ $response->message }}</p>@endif
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($person->profile)<x-button :href="route('personnes.show', $person->profile->public_id)" variant="secondary">Voir le profil public</x-button>@endif
                    @if ($response->attachment_path)<x-button :href="route('opportunites.attachment', $response)" variant="secondary"><x-icon name="document" :size="16" class="mr-1.5" />Document PDF</x-button>@endif
                    @if ($response->state === 'sent')<button type="button" wire:click="markSeen('{{ $response->id }}')" class="min-h-11 px-3 text-[13px] font-bold text-link">Marquer comme vue</button>@endif
                    @if ($response->state !== 'contacted')<button type="button" wire:click="contact('{{ $response->id }}')" wire:confirm="Proposer un échange à cette personne ?" class="inline-flex min-h-11 items-center rounded-button bg-accent px-4 text-[14px] font-bold text-ink">Proposer un échange</button>@endif
                </div>
            </article>
        @empty
            <div class="rounded-card border border-line bg-card p-6"><h2 class="text-[18px] font-bold">Aucune réponse pour le moment.</h2><p class="mt-2 text-[14px] text-muted">Les personnes qui postulent ou se disent intéressées apparaîtront ici.</p></div>
        @endforelse
    </section>
</div>
