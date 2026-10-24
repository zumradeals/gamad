<x-layouts.app :title="'Corriger le dossier — '.$company->name" narrow>
    <div class="space-y-5">
        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="17"/> Retour à ma page</a>
        <section class="rounded-card border border-line bg-card p-5 md:p-7">
            <h1 class="text-[25px] font-extrabold">Corriger les justificatifs de {{ $company->name }}</h1>
            <p class="mt-2 text-[14px] text-muted">L'immatriculation n'a pas été validée. Remplacez les documents et demandez un nouvel examen sans recréer votre entreprise. Le nom légal et l'immatriculation restent inchangés.</p>
            @if ($company->review_note)<div class="mt-4 rounded-button bg-ground p-4 text-[14px]"><strong>Motif du refus :</strong> {{ $company->review_note }}</div>@endif
            @if ($errors->any())<div role="alert" class="mt-4 rounded-button border border-danger p-3">@foreach ($errors->all() as $error)<p class="text-[13px] text-danger">{{ $error }}</p>@endforeach</div>@endif
            <form method="POST" action="{{ route('entreprises.resubmit.store', $company) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="revision" value="{{ $company->revision }}">
                <label class="flex flex-col gap-2 text-[14px] font-bold">Nouvelle pièce officielle d'immatriculation *<input type="file" name="rccm" required accept=".pdf,.jpg,.jpeg,.png" class="rounded-button border border-chip-line p-3"></label>
                <label class="flex flex-col gap-2 text-[14px] font-bold">DFE (facultatif)<input type="file" name="dfe" accept=".pdf,.jpg,.jpeg,.png" class="rounded-button border border-chip-line p-3"></label>
                <label class="flex flex-col gap-2 text-[14px] font-bold">IDU (facultatif)<input type="file" name="idu" accept=".pdf,.jpg,.jpeg,.png" class="rounded-button border border-chip-line p-3"></label>
                <p class="text-[12px] text-muted">PDF, JPG ou PNG · 5 Mo par document · stockage privé accessible uniquement aux vérificateurs administratifs de GAMAD.</p>
                <x-button type="submit" variant="accent">Demander un nouvel examen</x-button>
            </form>
        </section>
    </div>
</x-layouts.app>
