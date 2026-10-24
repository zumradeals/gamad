<x-layouts.app title="Contacter une entreprise" narrow>
    <div class="space-y-5">
        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="17"/> Retour à la page entreprise</a>
        <section class="rounded-card border border-line bg-card p-5 md:p-7">
            <p class="text-[12px] font-semibold text-muted">PRENDRE CONTACT · GAMAD</p>
            <h1 class="mt-2 break-words text-[26px] font-extrabold">Contacter {{ $company->name }}</h1>
            <p class="mt-3 text-[14px] leading-relaxed text-muted">Envoyez une demande de mise en relation au gestionnaire de cette page. Il doit l'accepter avant que vous puissiez échanger en privé. La vérification administrative de l'entreprise ne certifie pas l'identité du gestionnaire.</p>
            @if ($company->email)
                <p class="mt-3 text-[13px] text-muted">Vous pouvez aussi utiliser les coordonnées publiées par l'entreprise : <a class="font-bold text-link" href="mailto:{{ $company->email }}">{{ $company->email }}</a>.</p>
            @endif
            @if ($errors->any())<div role="alert" class="mt-4 rounded-button border border-danger p-3">@foreach ($errors->all() as $message)<p class="text-[13px] text-danger">{{ $message }}</p>@endforeach</div>@endif
            <form action="{{ route('entreprises.contact.store', $company) }}" method="POST" class="mt-5 space-y-4">
                @csrf
                <label for="contact-note" class="block text-[14px] font-bold">Pourquoi souhaitez-vous entrer en relation ?</label>
                <textarea id="contact-note" name="note" required minlength="5" maxlength="210" rows="4" class="w-full rounded-button border border-chip-line bg-card p-3" placeholder="Ex. Je souhaite échanger sur une prestation de maintenance.">{{ old('note') }}</textarea>
                <p class="text-[12px] text-muted">210 caractères au maximum. Les limites anti-spam et le respect des blocages existants s'appliquent.</p>
                <x-button type="submit" variant="accent">Envoyer ma demande</x-button>
            </form>
        </section>
    </div>
</x-layouts.app>
