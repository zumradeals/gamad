<x-layouts.app :title="'Personnaliser — '.$company->name" narrow>
    <div class="space-y-5">
        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="18" /> Retour à ma page entreprise</a>

        <header class="relative overflow-hidden rounded-[24px] bg-ink px-5 py-8 text-white md:px-8 md:py-10">
            <div class="absolute -right-10 -top-14 size-52 rounded-full border-[28px] border-accent/20" aria-hidden="true"></div>
            <div class="relative max-w-xl space-y-2">
                <p class="text-[12px] font-extrabold tracking-widest text-accent">IDENTITÉ VISUELLE · GAMAD ENTREPRISES</p>
                <h1 class="break-words text-[30px] font-extrabold leading-tight md:text-[36px]">Donnez un visage à {{ $company->name }}</h1>
                <p class="text-[14px] leading-relaxed text-white/85">Un logo, une bannière et quelques mots pour présenter votre savoir-faire, aussi clairement que sur les profils personnels.</p>
            </div>
        </header>

        @if ($errors->any())
            <div class="rounded-card border border-danger bg-card p-4" role="alert">
                @foreach ($errors->all() as $message)<p class="text-[13px] font-semibold text-danger">{{ $message }}</p>@endforeach
            </div>
        @endif

        <form action="{{ route('entreprises.branding.update', $company) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <section class="rounded-card border border-line bg-card p-5 md:p-6">
                <h2 class="text-[20px] font-extrabold">1. Logo et bannière</h2>
                <p class="mt-1 text-[13px] leading-relaxed text-muted">Images JPG, PNG ou WebP, jusqu’à 5 Mo chacune. GAMAD les optimise et retire les métadonnées. Les images existantes sont conservées si vous ne sélectionnez rien.</p>

                <div class="mt-5 grid gap-5">
                    <div>
                        <div class="relative h-[132px] overflow-hidden rounded-[18px] bg-ink md:h-[210px]">
                            @if ($company->banner_path)
                                <img src="{{ route('entreprises.visual', [$company, 'banner']) }}" alt="Bannière actuelle de {{ $company->name }}" class="size-full object-cover">
                            @else
                                <div class="absolute -right-8 -top-12 size-48 rounded-full border-[22px] border-accent/30" aria-hidden="true"></div>
                                <span class="absolute bottom-4 left-5 text-[13px] font-bold text-white/80">Bannière personnalisable</span>
                            @endif
                        </div>
                        <label for="company-banner-upload" class="mt-3 block text-[14px] font-bold">Choisir une bannière panoramique</label>
                        <input id="company-banner-upload" type="file" name="banner" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3 text-[13px]">
                        <p class="mt-1 text-[12px] text-muted">Cadrage horizontal 1600 × 600 pixels, adapté aux écrans mobiles et ordinateurs. Évitez de placer du texte près des bords.</p>
                        @if ($company->banner_path)
                            <label class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-semibold"><input type="checkbox" name="remove_banner" value="1" class="size-4 accent-ink"> Retirer la bannière actuelle</label>
                        @endif
                    </div>

                    <div class="border-t border-line-soft pt-5">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex size-[84px] shrink-0 items-center justify-center overflow-hidden rounded-[19px] border-4 border-card bg-soft-blue text-[26px] font-extrabold text-ink shadow-sm">
                                @if ($company->logo_path)
                                    <img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="Logo actuel" class="size-full bg-white object-contain">
                                @else
                                    {{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-[16px] font-extrabold">Logo de l’entreprise</h3>
                                <p class="mt-1 text-[13px] text-muted">Un logo carré, lisible même lorsqu’il apparaît dans le Fil à côté d’une publication.</p>
                            </div>
                        </div>
                        <label for="company-logo-upload" class="mt-3 block text-[14px] font-bold">Choisir un logo</label>
                        <input id="company-logo-upload" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-button border border-chip-line bg-card p-3 text-[13px]">
                        @if ($company->logo_path)
                            <label class="mt-3 inline-flex min-h-11 items-center gap-2 text-[13px] font-semibold"><input type="checkbox" name="remove_logo" value="1" class="size-4 accent-ink"> Retirer le logo actuel</label>
                        @endif
                    </div>
                </div>
            </section>

            <section class="rounded-card border border-line bg-card p-5 md:p-6">
                <h2 class="text-[20px] font-extrabold">2. Notre présentation</h2>
                <p class="mt-1 text-[13px] text-muted">Expliquez simplement ce que fait votre entreprise et ce qu’on peut réaliser avec elle.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">En quelques mots (280 caractères)
                        <textarea name="summary" maxlength="280" rows="3" class="rounded-button border border-chip-line bg-card p-3 text-[14px]">{{ old('summary', $company->summary) }}</textarea>
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Présentation détaillée
                        <textarea name="description" maxlength="1800" rows="5" class="rounded-button border border-chip-line bg-card p-3 text-[14px]">{{ old('description', $company->description) }}</textarea>
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold">Ville
                        <input name="city" maxlength="120" value="{{ old('city', $company->city) }}" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold">Site web
                        <input name="website" type="url" maxlength="255" value="{{ old('website', $company->website) }}" placeholder="https://…" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Courriel professionnel
                        <input name="email" type="email" maxlength="255" value="{{ old('email', $company->email) }}" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                    </label>
                </div>
            </section>

            <section class="rounded-card border border-line bg-card p-5">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-button bg-type-knowledge-bg text-type-knowledge-fg"><x-icon name="shield" :size="21" /></span>
                    <div>
                        <h2 class="text-[16px] font-extrabold">Votre immatriculation reste protégée</h2>
                        <p class="mt-1 text-[13px] leading-relaxed text-muted">La raison sociale, les documents déposés et le statut d’immatriculation ne sont pas modifiables ici. Personnaliser sa page ne donne aucun nouveau badge ni nouvelle vérification.</p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Annuler</a>
                    <x-button type="submit" variant="accent">Enregistrer ma présentation</x-button>
                </div>
            </section>
        </form>
    </div>
</x-layouts.app>
