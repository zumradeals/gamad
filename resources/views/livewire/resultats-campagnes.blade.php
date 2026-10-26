@php
    $n = fn ($v) => $v === null ? '—*' : number_format($v, 0, ',', ' ');
    $fcfa = fn ($v) => $v === null ? '—' : number_format($v, 0, ',', ' ').' FCFA';
    $totals = $summary['totals'];
    $kpis = [['Affichages', $n($totals['impressions']), 'personnes différentes, une fois par jour'], ['Interactions', $n($totals['interactions']), 'clics sur le bouton de l’annonce'], ['Contacts reçus', $n($totals['contacts']), 'demandes de relation envoyées après un clic'], ['Dépenses prévisionnelles', $fcfa($totals['spend']), 'aucun paiement effectué']];
@endphp
<div class="flex flex-col gap-4">
    @include('livewire.partials.publicite-entete', ['current' => 'resultats'])

    @unless ($diffusionOpen)
        <x-status class="!border-type-call-bg !bg-type-call-bg !text-type-call-fg"><strong>La diffusion payante n’est pas ouverte.</strong> Tant qu’elle est fermée, aucune annonce n’est affichée : il n’y a donc encore aucun résultat. Les dépenses seront toujours prévisionnelles avant la validation du dispositif d’encaissement.</x-status>
    @endunless

    <div class="flex flex-wrap items-center gap-2">
        <label for="campagne" class="sr-only">Campagne</label>
        <select id="campagne" wire:model.live="campaign" class="min-h-11 rounded-button border border-chip-line bg-card px-3.5 text-[14px] font-bold">
            <option value="">Toutes les campagnes</option>
            @foreach ($campaigns as $c)<option value="{{ $c->id }}">{{ $c->title ?: 'Sans titre' }}</option>@endforeach
        </select>
        <div class="flex gap-2" role="group" aria-label="Période">
            @foreach (['7' => '7 jours', '30' => '30 jours', 'tout' => 'Toute la durée'] as $key => $label)
                <button type="button" wire:click="setPeriod('{{ $key }}')" aria-pressed="{{ $period === $key ? 'true' : 'false' }}" @class(['inline-flex min-h-11 items-center whitespace-nowrap rounded-chip border px-3.5 text-[13px] font-semibold', 'border-link bg-soft-blue text-link' => $period === $key, 'border-chip-line bg-card' => $period !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div wire:loading.delay class="text-[13px] font-semibold text-muted" role="status">Chargement…</div>

    @if ($campaigns->isEmpty())
        <section class="flex flex-col items-start gap-3 rounded-card border border-line bg-card p-5">
            <h2 class="text-[18px] font-extrabold">Aucun résultat pour l’instant</h2>
            <p class="text-[14px] text-muted">Les résultats apparaissent quand une campagne acceptée par la modération est diffusée.</p>
            <x-button :href="route('publicite.creer')">Créer une campagne</x-button>
        </section>
    @else
        <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
            @foreach ($kpis as [$label, $value, $hint])
                <div class="rounded-card border border-line bg-card p-3.5 md:p-4"><p class="text-[12px] font-bold text-muted">{{ $label }}</p><p class="mt-0.5 text-[20px] font-extrabold tracking-[-0.4px] md:text-[24px]" data-testid="kpi">{{ $value }}</p><p class="mt-0.5 text-[11.5px] text-muted">{{ $hint }}</p></div>
            @endforeach
        </div>

        <div class="grid gap-3 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
            <section aria-labelledby="evol" class="rounded-card border border-line bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="evol" class="text-[16px] font-extrabold">Évolution par jour</h2>
                    <span class="flex gap-3.5 text-[12px] font-bold"><span><i class="mr-1.5 inline-block size-2.5 rounded-full bg-ink"></i>Affichages</span><span><i class="mr-1.5 inline-block size-2.5 rounded-full bg-accent"></i>Contacts</span></span></div>
                @if ($summary['series'])
                    @php
                        $series = $summary['series'];
                        $maxI = max(1, collect($series)->max('impressions'));
                        $maxC = max(1, collect($series)->max('contacts'));
                        $count = max(1, count($series) - 1);
                        $path = fn ($key, $max) => collect($series)->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').round(20 + $i * 600 / $count, 1).' '.round(150 - ($p[$key] / $max) * 120, 1))->implode(' ');
                    @endphp
                    <svg viewBox="0 0 640 170" role="img" aria-label="Affichages et contacts par jour" class="mt-2 h-auto w-full">
                        @foreach ([30, 75, 120] as $y)<line x1="20" x2="620" y1="{{ $y }}" y2="{{ $y }}" stroke="#eeebe4"/>@endforeach
                        <path d="{{ $path('impressions', $maxI) }}" fill="none" stroke="#0e2a3f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="{{ $path('contacts', $maxC) }}" fill="none" stroke="#f2c200" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @else
                    <p class="mt-3 text-[13px] text-muted">{{ $summary['empty'] ? 'Aucune donnée : vos annonces n’ont pas encore été diffusées.' : 'Trop peu de personnes pour afficher la courbe : elle est masquée pour les protéger.' }}</p>
                @endif
            </section>
            <section aria-labelledby="comprendre" class="rounded-card border border-line bg-card p-4">
                <h2 id="comprendre" class="flex items-center gap-2 text-[16px] font-extrabold"><x-icon name="shield" :size="20" />Comprendre les chiffres</h2>
                <p class="mt-2 text-[12.5px] leading-relaxed text-muted"><strong class="text-ink">Contacts</strong> : des personnes ont choisi de vous écrire après avoir cliqué sur votre annonce. Vous les retrouvez dans <a href="{{ route('espace', ['rubrique' => 'relations']) }}" class="font-semibold">Mes relations</a> ; vous ne voyez rien d’autre d’elles.</p>
                <p class="mt-2 text-[12.5px] leading-relaxed text-muted"><strong class="text-ink">Résultats agrégés</strong> : aucun nom, aucun profil, aucune liste de personnes atteintes.</p>
                <p class="mt-2 text-[12.5px] leading-relaxed text-muted"><strong class="text-ink">Petits effectifs</strong> : sous {{ $threshold }}, un chiffre est masqué (—*) pour protéger les personnes.</p>
            </section>
        </div>

        <div class="overflow-x-auto rounded-card border border-line bg-card">
            <table class="w-full min-w-[640px] border-collapse text-[13px]">
                <caption class="sr-only">Résultats par campagne</caption>
                <thead><tr class="border-b border-line text-left text-[11.5px] uppercase tracking-wide text-muted"><th scope="col" class="px-3 py-2.5">Campagne</th><th scope="col" class="px-3 py-2.5 text-right">Affichages</th><th scope="col" class="px-3 py-2.5 text-right">Interactions</th><th scope="col" class="px-3 py-2.5 text-right">Contacts</th><th scope="col" class="px-3 py-2.5 text-right">Dépenses prév.</th><th scope="col" class="px-3 py-2.5 text-right">Par contact</th></tr></thead>
                <tbody>
                    @foreach ($summary['rows'] as $row)
                        <tr wire:key="res-{{ $row['campaign']->id }}" class="border-b border-line-soft last:border-0">
                            <th scope="row" class="px-3 py-2.5 text-left font-bold">{{ $row['campaign']->title ?: 'Sans titre' }}</th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($row['impressions']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($row['interactions']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($row['contacts']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fcfa($row['spend']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fcfa($row['perContact']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-[12px] text-muted">* Trop peu de personnes pour être affiché. Les dépenses sont prévisionnelles : aucun paiement n’est effectué avant la validation du dispositif d’encaissement.</p>
    @endif
</div>
