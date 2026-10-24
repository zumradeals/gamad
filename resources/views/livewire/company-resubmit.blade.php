<div class="space-y-5">
    <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="17"/> Retour à ma page</a>
    <section class="rounded-card border border-line bg-card p-5 md:p-7">
        <h1 class="text-[25px] font-extrabold">Corriger les justificatifs de {{ $company->name }}</h1>
        <p class="mt-2 text-[14px] text-muted">Votre immatriculation n'a pas été validée. Vous pouvez remplacer les documents et demander une nouvelle vérification sans recréer votre entreprise. Le nom légal et le RCCM restent inchangés.</p>
        @if ($company->review_note)<div class="mt-4 rounded-button bg-ground p-4 text-[14px]"><strong>Motif du refus :</strong> {{ $company->review_note }}</div>@endif
        @foreach ($errors->all() as $error)<p role="alert" class="mt-3 rounded-button border border-danger p-3 text-[13px] text-danger">{{ $error }}</p>@endforeach
        <form wire:submit="submit" class="mt-5 space-y-4">
            <label class="flex flex-col gap-2 text-[14px] font-bold">Nouvelle pièce officielle d'immatriculation *<input type="file" wire:model="rccm" required accept=".pdf,.jpg,.jpeg,.png" class="rounded-button border border-chip-line p-3"></label>
            <label class="flex flex-col gap-2 text-[14px] font-bold">DFE (facultatif)<input type="file" wire:model="dfe" accept=".pdf,.jpg,.jpeg,.png" class="rounded-button border border-chip-line p-3"></label>
            <label class="flex flex-col gap-2 text-[14px] font-bold">IDU (facultatif)<input type="file" wire:model="idu" accept=".pdf,.jpg,.jpeg,.png" class="rounded-button border border-chip-line p-3"></label>
            <p class="text-[12px] text-muted">PDF, JPG ou PNG · 5 Mo par document · stockage privé accessible uniquement à la modération administrative.</p>
            <x-button type="submit" variant="accent" wire:loading.attr="disabled" wire:target="submit,rccm,dfe,idu">Demander un nouvel examen</x-button>
        </form>
    </section>
</div>
