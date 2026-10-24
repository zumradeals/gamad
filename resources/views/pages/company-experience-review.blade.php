<x-layouts.app :title="'Demandes professionnelles — '.$company->name" narrow>
    <div class="space-y-5">
        <a href="{{ route('entreprises.show', $company) }}" class="inline-flex min-h-11 items-center gap-2 text-[13px] font-bold"><x-icon name="back" :size="18"/> Page entreprise</a>
        <header class="rounded-[22px] bg-ink p-6 text-white md:p-8">
            <p class="text-[12px] font-extrabold tracking-widest text-accent">PARCOURS PROFESSIONNELS</p>
            <h1 class="mt-2 break-words text-[28px] font-extrabold">{{ $company->name }}</h1>
            <p class="mt-3 text-[14px] leading-relaxed text-white/85">Confirmez uniquement les relations professionnelles dont vous avez connaissance. La vérification administrative de l'entreprise ne confirme pas automatiquement les parcours individuels.</p>
        </header>
        @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
        @if ($errors->any())<div class="rounded-card border border-danger bg-card p-4" role="alert">@foreach ($errors->all() as $message)<p class="text-[13px] font-semibold text-danger">{{ $message }}</p>@endforeach</div>@endif
        <section class="rounded-card border border-line bg-card p-5 md:p-6">
            <h2 class="text-[19px] font-extrabold">Demandes de confirmation</h2>
            <p class="mt-2 text-[13px] text-muted">Cette liste reste privée. Seul le compte gestionnaire de cette page peut prendre une décision pendant le pilote.</p>
            <div class="mt-4 space-y-4">
                @forelse ($requests as $experience)
                    <article class="rounded-button border border-line p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="break-words text-[16px] font-extrabold">{{ $experience->user?->name }}</p>
                                <h3 class="mt-1 text-[14px] font-bold">{{ $experience->title }}</h3>
                                <p class="mt-1 text-[12px] text-muted">{{ $experience->relationshipLabel() }} · {{ $experience->period }}</p>
                            </div>
                            <span class="rounded-chip bg-ground px-3 py-1 text-[12px] font-semibold text-muted">À confirmer</span>
                        </div>
                        @if ($experience->description)<p class="mt-3 whitespace-pre-line break-words text-[13px]">{{ $experience->description }}</p>@endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (['confirm' => 'Confirmer cette expérience', 'decline' => 'Ne pas confirmer'] as $decision => $label)
                                <form action="{{ route('entreprises.experiences.decision', [$company, $experience]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="revision" value="{{ $experience->revision }}">
                                    <input type="hidden" name="decision" value="{{ $decision }}">
                                    <x-button type="submit" :variant="$decision === 'confirm' ? 'accent' : 'secondary'">{{ $label }}</x-button>
                                </form>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="text-[14px] text-muted">Aucune demande de confirmation en attente pour cette entreprise.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
