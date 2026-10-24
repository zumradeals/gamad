<x-layouts.app :title="'Publier au nom de '.$company->name" narrow>
    <div class="flex flex-col gap-5">
        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 self-start text-[13px] font-bold"><x-icon name="back" :size="17" /> Retour à la page entreprise</a>
        <header class="rounded-[22px] bg-ink px-5 py-7 text-white md:px-8">
            <p class="text-[12px] font-extrabold tracking-widest text-accent">GAMAD · ENTREPRISES</p>
            <h1 class="mt-2 break-words text-[29px] font-extrabold leading-tight md:text-[35px]">Une nouvelle publication pour {{ $company->name }}</h1>
            <p class="mt-3 max-w-xl text-[14px] leading-relaxed text-white/85">Montrez vos activités, racontez une réalisation ou proposez une opportunité. Votre publication rejoindra le Fil GAMAD et conservera ses commentaires, partages et échanges.</p>
        </header>

        @if ($errors->any())
            <div class="rounded-card border border-danger bg-card p-4" role="alert">
                @foreach ($errors->all() as $message)<p class="text-[13px] font-semibold text-danger">{{ $message }}</p>@endforeach
            </div>
        @endif

        <form action="{{ route('entreprises.posts.store', $company) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <section class="rounded-card border border-line bg-card p-5 md:p-6">
                <h2 class="text-[20px] font-extrabold">Que souhaite partager votre entreprise ?</h2>
                <fieldset class="mt-4 grid gap-3 md:grid-cols-3">
                    <legend class="sr-only">Type de publication</legend>
                    @foreach ($types as $key => $info)
                        <label class="flex min-h-24 cursor-pointer flex-col gap-1 rounded-button border border-chip-line p-3 has-[:checked]:border-ink has-[:checked]:bg-ground">
                            <span class="flex items-center gap-2 text-[14px] font-extrabold"><input type="radio" name="kind" value="{{ $key }}" required @checked(old('kind', 'news') === $key) class="size-4 accent-ink">{{ $info['label'] }}</span>
                            <span class="text-[12px] leading-relaxed text-muted">{{ $info['description'] }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <label for="body" class="mt-5 block text-[14px] font-extrabold">Votre message *</label>
                <textarea id="body" name="body" required maxlength="{{ \App\Models\Publication::BODY_MAX }}" rows="6" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3 text-[15px]" placeholder="Expliquez concrètement votre actualité, votre réalisation ou votre opportunité…">{{ old('body') }}</textarea>
                <p class="mt-1 text-[12px] text-muted">500 caractères maximum. Les publications sont publiques. Aucun recrutement automatisé ni classement de popularité.</p>

                <label for="company-media" class="mt-5 block text-[14px] font-extrabold">Illustration (facultative)</label>
                <input id="company-media" type="file" name="media" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.pdf" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3 text-[13px]">
                <p class="mt-1 text-[12px] text-muted">Image ou PDF : 5 Mo maximum ; vidéo : 10 Mo maximum. Les médias passent par les contrôles habituels des publications GAMAD.</p>
            </section>
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-card border border-line bg-card p-5">
                <p class="max-w-lg text-[13px] text-muted">Cette publication sera clairement identifiée comme émanant de {{ $company->name }} (immatriculation vérifiée par GAMAD).</p>
                <x-button type="submit" variant="accent">Publier au nom de l'entreprise</x-button>
            </section>
        </form>
    </div>
</x-layouts.app>
