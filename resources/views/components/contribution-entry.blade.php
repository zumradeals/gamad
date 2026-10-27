@props(['kind', 'model', 'viewer' => null])

{{-- Accès contextuel aux Contributions (C1, D-207) depuis une ZUMRA, un projet, une entreprise ou un profil : les besoins ouverts du bénéficiaire,
     « Proposer une contribution » pour les autres, « Publier un besoin » et les propositions reçues pour son représentant. Rien n'apparaît
     si le module est fermé à la personne. --}}
@if (\App\Support\ContributionsModule::accessibleTo($viewer) && $viewer?->hasVerifiedEmail())
    @php
        $beneficiaryKind = \App\Enums\BeneficiaryKind::from($kind);
        $access = app(\App\Services\ContributionAccess::class);
        $represents = $access->represents($viewer, $beneficiaryKind, $model);
        $exists = $access->exists($viewer, $beneficiaryKind, $model);
        $needs = $exists ? app(\App\Services\ContributionFeed::class)->needsFor($viewer, $beneficiaryKind, $model)->get() : collect();
        $accepts = $beneficiaryKind !== \App\Enums\BeneficiaryKind::Person || (bool) $model->profile?->accepts_contributions;
        $query = match ($beneficiaryKind) {
            \App\Enums\BeneficiaryKind::Zumra => ['zumra' => $model->getKey()],
            \App\Enums\BeneficiaryKind::Project => ['projet' => $model->getKey()],
            \App\Enums\BeneficiaryKind::Company => ['entreprise' => $model->getKey()],
            default => ['personne' => $model->profile?->public_id],
        };
        // Seules les propositions de CE bénéficiaire comptent (recette ZUMRA) : le compteur n'agrège plus tout ce que la personne représente.
        $pending = $represents ? app(\App\Services\ContributionFeed::class)->handledBy($viewer)->where('state', 'proposed')
            ->where(match ($beneficiaryKind) {
                \App\Enums\BeneficiaryKind::Zumra => 'zumra_id',
                \App\Enums\BeneficiaryKind::Project => 'project_id',
                \App\Enums\BeneficiaryKind::Company => 'company_id',
                default => 'person_id',
            }, $model->getKey())->count() : 0;
    @endphp
    @if ($exists && ($represents || $accepts || $needs->isNotEmpty()))
        <section aria-labelledby="contributions-{{ $kind }}" {{ $attributes->class('flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]') }}>
            <h2 id="contributions-{{ $kind }}" class="flex items-center gap-2 text-[17px] font-extrabold"><x-icon name="heart" :size="20" />Besoins et contributions</h2>
            @forelse ($needs as $need)
                <a href="{{ route('publications.show', $need) }}" class="flex min-h-11 flex-col justify-center border-t border-line-soft pt-2 text-ink no-underline first:border-0 first:pt-0 hover:text-ink">
                    <span class="break-words text-[15px] font-extrabold">{{ $need->title ?: \Illuminate\Support\Str::limit($need->body, 60) }}</span>
                    <span class="text-[12px] text-muted">{{ \App\Enums\HelpKind::tryFrom((string) $need->help_kind)?->label() }} · {{ $need->need_state->contributionLabel() }}</span>
                </a>
            @empty
                <p class="text-[14px] text-muted">Aucun besoin ouvert pour l’instant.</p>
            @endforelse
            <div class="flex flex-col gap-2 sm:flex-row">
                @if ($represents)
                    <x-button :href="route('contributions.need', ['pour' => $kind === 'person' ? 'person' : $kind.':'.$model->getKey()])" variant="accent">Publier un besoin</x-button>
                    <x-button :href="route('espace.mes-contributions', ['onglet' => 'recues'])" variant="secondary">Propositions reçues{{ $pending > 0 ? ' ('.$pending.')' : '' }}</x-button>
                @elseif ($accepts)
                    <x-button :href="route('contributions.propose', $query)" variant="accent" class="gap-2"><x-icon name="heart" :size="18" />{{ $beneficiaryKind === \App\Enums\BeneficiaryKind::Person ? 'Proposer une aide' : 'Proposer une contribution' }}</x-button>
                @endif
            </div>
            @if ($beneficiaryKind === \App\Enums\BeneficiaryKind::Company)<p class="text-[12px] text-muted">Une contribution à une entreprise n’est ni un investissement ni une prise de participation.</p>@endif
        </section>
    @endif
@endif
