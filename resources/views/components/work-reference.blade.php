@props(['reference'])
<article class="rounded-button border border-line bg-card p-4">
    <p class="flex items-center gap-2 text-[14px] font-extrabold text-type-knowledge-fg"><x-icon name="check-circle" :size="20" />Expérience confirmée</p>
    <p class="mt-3 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $reference->claim }}</p>
    <p class="mt-2 text-[13px] text-muted">Intervention du {{ $reference->performed_on->format('d/m/Y') }}</p>
    <p class="mt-2 break-words text-[13px]">Confirmée par <a href="{{ route('personnes.show', $reference->attester->profile->public_id) }}" class="inline-flex min-h-11 items-center font-bold">{{ $reference->attester->name }}</a> · {{ \App\Models\WorkAttestation::ROLES[$reference->role] }}</p>
    <p class="text-[12px] text-muted">Identité du témoin vérifiée par GAMAD. Confirmation du {{ $reference->confirmed_at->format('d/m/Y') }} ; ce témoignage ne certifie pas toutes les compétences.</p>
    <div class="mt-2 flex flex-wrap gap-x-4">
        <a href="{{ route('publications.show', $reference->publication_id) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Voir la réalisation →</a>
        @auth<a href="{{ route('personnes.signaler', ['profile' => $reference->author->profile->public_id, 'reference' => $reference->id]) }}" class="inline-flex min-h-11 items-center text-[12px] font-semibold text-muted">Contester cette référence</a>@endauth
    </div>
</article>
