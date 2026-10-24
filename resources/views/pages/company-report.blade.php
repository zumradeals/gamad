<x-layouts.app title="Signaler une page entreprise" narrow>
    <div class="space-y-5">
        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="17"/> Retour à l'entreprise</a>
        <section class="rounded-card border border-line bg-card p-5 md:p-7">
            <p class="text-[12px] font-bold text-muted">CONFIANCE · GAMAD ENTREPRISES</p>
            <h1 class="mt-2 text-[26px] font-extrabold">Signaler {{ $company->name }}</h1>
            <p class="mt-2 text-[14px] leading-relaxed text-muted">Une entreprise peut être légalement immatriculée sans que le gestionnaire de sa page ait été personnellement vérifié. Vous pouvez signaler une usurpation ou une information trompeuse. Votre identité de signalant n'est pas communiquée à la page.</p>
            @if ($errors->any())<div role="alert" class="mt-4 rounded-button border border-danger p-3">@foreach ($errors->all() as $message)<p class="text-[13px] text-danger">{{ $message }}</p>@endforeach</div>@endif
            <form action="{{ route('entreprises.reports.store', $company) }}" method="POST" class="mt-5 space-y-4">
                @csrf
                <label class="flex flex-col gap-2 text-[14px] font-bold">Motif du signalement
                    <select name="reason" required class="min-h-12 rounded-button border border-chip-line bg-card p-3">
                        <option value="">Choisir</option>
                        <option value="impersonation" @selected(old('reason') === 'impersonation')>Usurpation de l'identité d'une entreprise</option>
                        <option value="wrong_information" @selected(old('reason') === 'wrong_information')>Informations trompeuses ou incorrectes</option>
                        <option value="other" @selected(old('reason') === 'other')>Autre motif sérieux</option>
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-[14px] font-bold">Expliquez les faits (15 à 1 000 caractères)
                    <textarea name="details" rows="5" minlength="15" maxlength="1000" required class="rounded-button border border-chip-line bg-card p-3">{{ old('details') }}</textarea>
                </label>
                <x-button type="submit" variant="accent">Envoyer le signalement</x-button>
            </form>
        </section>
    </div>
</x-layouts.app>
