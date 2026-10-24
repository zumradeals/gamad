<section class="flex min-w-0 flex-col gap-4 rounded-card border border-line bg-card p-5">
    <h2 class="text-[20px] font-extrabold">Expériences confirmées</h2>
    @foreach ($references as $reference)<x-work-reference :reference="$reference" />@endforeach
    @if ($references->isEmpty())<p class="text-[14px] text-muted">Aucune confirmation visible pour cette réalisation.</p>@endif
    @if ($isOwner && ! $post->isHidden())
        <details><summary class="min-h-11 cursor-pointer content-center text-[14px] font-bold">Faire confirmer cette expérience</summary>
            <p class="my-3 text-[14px] text-muted">Choisissez une relation réciproque dont l’identité a été vérifiée. Elle devra avoir constaté l’intervention et accepter votre texte. Aucune confirmation ne s’achète.</p>
            @if (session('trust-status'))<x-status>{{ session('trust-status') }}</x-status>@endif
            @foreach ($errors->all() as $error)<p role="alert" class="my-2 text-[14px] text-danger">{{ $error }}</p>@endforeach
            @if ($candidates->isNotEmpty())<form wire:submit="request" class="flex flex-col gap-3">
                <label for="attester-{{ $publicationId }}" class="text-[14px] font-bold">Personne qui peut témoigner</label>
                <select id="attester-{{ $publicationId }}" wire:model="attesterId" required class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3"><option value="">Choisir une relation identifiée</option>@foreach ($candidates as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select>
                <label for="claim-{{ $publicationId }}" class="text-[14px] font-bold">Mon intervention précise</label><textarea id="claim-{{ $publicationId }}" wire:model="claim" maxlength="280" required rows="3" placeholder="Ex. J’ai posé et raccordé les sanitaires de cette boutique." class="w-full rounded-button border border-chip-line bg-card p-3"></textarea>
                <label for="performed-{{ $publicationId }}" class="text-[14px] font-bold">Date de l’intervention</label><input id="performed-{{ $publicationId }}" type="date" wire:model="performedOn" required max="{{ today()->toDateString() }}" class="min-h-12 w-full min-w-0 rounded-button border border-chip-line bg-card px-3">
                <x-button wire:loading.attr="disabled">Demander sa confirmation</x-button><p wire:loading role="status" class="text-[13px] text-muted">Envoi…</p>
            </form>@else<p class="text-[14px] text-muted">Aucune relation identifiée disponible. La personne qui peut témoigner doit d’abord entrer en relation avec vous et faire vérifier son identité.</p>@endif
            <a href="{{ route('espace.attestations') }}" class="mt-2 inline-flex min-h-11 items-center text-[14px] font-bold">Suivre mes attestations →</a>
        </details>
    @endif
</section>
