<x-layouts.app title="Mes expériences professionnelles" narrow>
    <div class="space-y-5">
        <a href="{{ route('espace.profil') }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="18"/> Retour à mon profil</a>
        <header class="rounded-[22px] bg-ink p-6 text-white md:p-8">
            <p class="text-[12px] font-extrabold tracking-widest text-accent">MON PARCOURS SUR GAMAD</p>
            <h1 class="mt-2 text-[29px] font-extrabold leading-tight">Mes expériences en entreprise</h1>
            <p class="mt-3 max-w-xl text-[14px] leading-relaxed text-white/85">Associez uniquement une entreprise dont l’immatriculation a été vérifiée sur GAMAD. L’expérience restera déclarative tant que cette entreprise ne l’aura pas confirmée.</p>
        </header>

        @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
        @if ($errors->any())
            <div class="rounded-card border border-danger bg-card p-4" role="alert">
                @foreach ($errors->all() as $message)<p class="text-[13px] font-semibold text-danger">{{ $message }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-card border border-line bg-card p-5 md:p-6">
            <h2 class="text-[20px] font-extrabold">Ajouter une expérience</h2>
            <p class="mt-2 text-[13px] text-muted">Une entreprise absente de cette liste ne peut pas encore être associée à votre parcours.</p>
            @if ($companies->isEmpty())
                <p class="mt-4 text-[14px] text-muted">Aucune entreprise administrativement vérifiée n'est encore disponible. Vous pourrez ajouter une référence dès la vérification d'une page.</p>
                <a href="{{ route('entreprises.index') }}" class="mt-2 inline-flex min-h-11 items-center font-bold">Découvrir les entreprises →</a>
            @else
                <form action="{{ route('espace.company-experiences.store') }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Entreprise vérifiée *
                        <select name="company_id" required class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                            <option value="">Choisir une entreprise</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') === $company->id)>{{ $company->name }} · {{ $company->sector }}</option>@endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold">Relation *
                        <select name="relationship" required class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                            @foreach ($types as $key => $label)<option value="{{ $key }}" @selected(old('relationship') === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold">Période *
                        <input name="period" maxlength="80" required value="{{ old('period') }}" placeholder="Ex. 2024 – 2026" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Fonction ou mission *
                        <input name="title" maxlength="120" required value="{{ old('title') }}" placeholder="Ex. Technicien fibre" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[14px]">
                    </label>
                    <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Ce que vous avez réalisé (facultatif)
                        <textarea name="description" maxlength="500" rows="3" class="rounded-button border border-chip-line bg-card p-3 text-[14px]">{{ old('description') }}</textarea>
                    </label>
                    <label class="flex items-start gap-2 sm:col-span-2">
                        <input type="checkbox" name="show_on_company_page" value="1" class="mt-1 size-4 shrink-0 accent-ink" @checked(old('show_on_company_page'))>
                        <span class="text-[13px] leading-relaxed">Si l'entreprise confirme mon expérience, j'accepte qu'elle soit aussi présentée sur sa page, selon la visibilité de mon profil.</span>
                    </label>
                    <div class="sm:col-span-2"><x-button type="submit" variant="accent">Ajouter à mon parcours</x-button></div>
                </form>
            @endif
        </section>

        <section class="rounded-card border border-line bg-card p-5 md:p-6">
            <h2 class="text-[20px] font-extrabold">Mes références professionnelles</h2>
            <div class="mt-4 space-y-4">
                @forelse ($experiences as $experience)
                    <article class="rounded-button border border-line p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="space-y-1">
                                <h3 class="break-words text-[17px] font-extrabold">{{ $experience->title }}</h3>
                                <p class="text-[13px] font-bold">{{ $experience->company->name }} · {{ $experience->relationshipLabel() }} · {{ $experience->period }}</p>
                            </div>
                            @if ($experience->confirmed())
                                <span class="rounded-chip bg-type-knowledge-bg px-2.5 py-1.5 text-[12px] font-bold text-type-knowledge-fg">Confirmée par l’entreprise</span>
                            @else
                                <span class="rounded-chip bg-ground px-2.5 py-1.5 text-[12px] font-bold text-muted">Non confirmée</span>
                            @endif
                        </div>
                        @if ($experience->description)<p class="mt-2 whitespace-pre-line break-words text-[13px] text-muted">{{ $experience->description }}</p>@endif
                        @unless ($experience->company->verified())<p class="mt-2 text-[12px] font-bold text-danger">Entreprise non vérifiée : la référence est masquée sur votre profil.</p>@endunless
                        <div class="mt-3 flex flex-wrap gap-3">
                            @if ($experience->confirmed())
                                <form action="{{ route('espace.company-experiences.visibility', $experience) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="visible" value="{{ $experience->show_on_company_page ? '0' : '1' }}">
                                    <x-button type="submit" variant="secondary">{{ $experience->show_on_company_page ? 'Masquer sur la page entreprise' : 'Afficher aussi sur la page entreprise' }}</x-button>
                                </form>
                            @endif
                            <form action="{{ route('espace.company-experiences.withdraw', $experience) }}" method="POST">
                                @csrf
                                <x-button type="submit" variant="secondary">Retirer cette expérience</x-button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-[14px] text-muted">Vous n'avez pas encore déclaré d'expérience liée à une entreprise vérifiée.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
