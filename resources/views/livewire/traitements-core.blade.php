@php
    $dur = fn (?int $ms) => $ms === null ? '—' : ($ms >= 1000 ? number_format($ms / 1000, 1, ',', ' ').' s' : $ms.' ms');
    $label = ['hidden' => 'Publication masquée', 'closed' => 'Fermée ou close', 'resolved' => 'Besoin résolu', 'expired' => 'Période terminée', 'blocked' => 'Blocage', 'consent' => 'Consentement retiré', 'unavailable' => 'Indisponibilité', 'suspended' => 'Compte suspendu'];
    $accessOutcomes = ['success' => 'Connexion réussie', 'failure' => 'Échec', 'limited' => 'Limitation', 'refused' => 'Refus'];
    $card = 'rounded-card border border-line bg-card p-4';
@endphp
<div class="flex flex-col gap-4" x-data x-on:console-investigation-opened.window="$nextTick(() => { const panel = document.getElementById('console-investigation'); panel?.scrollIntoView({ block: 'start' }); panel?.focus({ preventScroll: true }); })">
    <header class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-[22px] font-extrabold">GAMAD Console</h1>
            <p class="text-[14px] font-bold">Traitements du Core</p>
            <p class="text-[13px] text-muted">Santé et interventions limitées sur les moteurs réels. Les détails identifiants sont réservés à l’investigation des accès.</p>
        </div>
        <div class="flex gap-1" role="group" aria-label="Période">

        @foreach ([1 => '1 h', 24 => '24 h', 168 => '7 jours'] as $h => $text)
                <button type="button" wire:click="setHours({{ $h }})" @class(['min-h-11 rounded-button border px-3 text-[13px] font-bold', $hours === $h ? 'border-ink bg-ink text-white' : 'border-line bg-card'])>{{ $text }}</button>

        @endforeach
        </div>
    </header>

    <nav class="flex flex-wrap gap-2" aria-label="Espaces de la Console">

        @foreach (['sante' => 'Santé', 'identity' => 'Identity et sécurité', 'execution' => 'Execution', 'matching' => 'Matching', 'incidents' => 'Incidents'] as $anchor => $text)
            <a href="#{{ $anchor }}" class="inline-flex min-h-11 items-center rounded-button border border-line bg-card px-3 text-[13px] font-bold">{{ $text }}</a>

        @endforeach
        <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="min-h-11 rounded-button bg-ink px-3 text-[13px] font-bold text-white">Actualiser</button>
    </nav>
    <p class="text-[12px] text-muted">Lecture effectuée le {{ $observed_at }} · fuseau Africa/Abidjan.</p>
    <p wire:loading role="status" class="text-[13px]">Lecture des mesures…</p>

        @if ($feedback)<p role="status" class="rounded-card border border-line bg-soft-blue p-4 text-[14px]">{{ $feedback }}</p>
        @endif
    @include('livewire.core-console.health')
    @include('livewire.core-console.identity')
    @include('livewire.core-console.actions')
        @if (isset($queue, $failed, $runs))
    <section class="{{ $card }}" aria-labelledby="t-file">
        <h2 id="t-file" class="text-[16px] font-extrabold">File d’exécution (maintenant)</h2>
        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><dt class="text-[12px] text-muted">À traiter</dt><dd class="text-[22px] font-extrabold" data-testid="queue-due">{{ $queue['due'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">En attente de reprise</dt><dd class="text-[22px] font-extrabold" data-testid="queue-waiting">{{ $queue['waiting'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">En cours</dt><dd class="text-[22px] font-extrabold" data-testid="queue-reserved">{{ $queue['reserved'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Plus ancienne attente</dt><dd class="text-[22px] font-extrabold">{{ $queue['oldest_due_seconds'] === null ? '—' : $dur($queue['oldest_due_seconds'] * 1000) }}</dd></div>
        </dl>
        <p class="mt-2 text-[12px] text-muted">Les tâches avancent après les réponses du site (au plus 12 par passage, 8 secondes), ou par la commande <code>gamad:traiter</code>. Sans visite, rien ne s’exécute : ce n’est pas un worker permanent.</p>
    </section>

    <section class="{{ $card }}" aria-labelledby="t-runs">
        <h2 id="t-runs" class="text-[16px] font-extrabold">Exécutions sur la période</h2>

        @forelse ($runs as $run)
            <div class="mt-3 border-t border-line-soft pt-3 first:mt-2 first:border-0 first:pt-0" data-testid="run-{{ $run['job'] }}">
                <p class="text-[14px] font-bold">{{ $run['job'] }}</p>
                <p class="text-[13px] text-muted">{{ $run['runs'] }} tentative(s) : {{ $run['success'] }} réussie(s), {{ $run['failure'] }} échouée(s), {{ $run['retries'] }} reprise(s) · durée médiane {{ $dur($run['p50_ms']) }}, p95 {{ $dur($run['p95_ms']) }}
        @foreach ($run['metrics'] as $name => $value) · {{ $name }} : {{ $value }}
        @endforeach</p>
            </div>

        @empty
            <p class="mt-2 text-[14px] text-muted">Aucune exécution sur cette période.</p>

        @endforelse
        <h3 class="mt-4 text-[14px] font-extrabold">Échecs définitifs ({{ $failed['total'] }})</h3>

        @forelse ($failed['recent'] as $failure)
            <p class="text-[13px] text-muted">{{ $failure['failed_at'] }} · {{ $failure['job'] }} · {{ $failure['error'] }}</p>

        @empty
            <p class="text-[13px] text-muted">Aucun échec définitif.</p>

        @endforelse
    </section>


        @endif

        @if (isset($searches, $suggestions))
    <section id="matching" class="{{ $card }} scroll-mt-40" aria-labelledby="t-matching">
        <h2 id="t-matching" class="text-[16px] font-extrabold">Matching : recherches</h2>
        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><dt class="text-[12px] text-muted">En attente</dt><dd class="text-[22px] font-extrabold">{{ $searches['queued'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">En cours</dt><dd class="text-[22px] font-extrabold">{{ $searches['running'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Terminées</dt><dd class="text-[22px] font-extrabold" data-testid="searches-done">{{ $searches['done'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Échouées</dt><dd class="text-[22px] font-extrabold">{{ $searches['failed'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Annulées (obsolètes)</dt><dd class="text-[22px] font-extrabold">{{ $searches['cancelled'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Avec reprise</dt><dd class="text-[22px] font-extrabold">{{ $searches['retried'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Pistes produites</dt><dd class="text-[22px] font-extrabold">{{ $searches['produced'] }}</dd></div>
            <div><dt class="text-[12px] text-muted">Interrompues (sans signe de vie)</dt><dd class="text-[22px] font-extrabold">{{ $searches['stale'] }}</dd></div>
        </dl>
        <p class="mt-2 text-[13px] text-muted">Attente avant démarrage : médiane {{ $dur($searches['wait_p50_ms']) }}, p95 {{ $dur($searches['wait_p95_ms']) }} · durée d’une recherche terminée : médiane {{ $dur($searches['run_p50_ms']) }}, p95 {{ $dur($searches['run_p95_ms']) }}</p>
    </section>

    <section class="{{ $card }}" aria-labelledby="t-pistes">
        <h2 id="t-pistes" class="text-[16px] font-extrabold">Matching : pistes</h2>
        <p class="mt-2 text-[14px]" data-testid="pistes">{{ $suggestions['active'] }} active(s) · {{ $suggestions['ignored'] }} ignorée(s) par les personnes · {{ $suggestions['withdrawn'] }} retirée(s)</p>

        @forelse ($suggestions['by_reason'] as $reason => $total)
            <p class="text-[13px] text-muted">{{ $label[$reason] ?? $reason }} : {{ $total }}</p>

        @empty
            <p class="text-[13px] text-muted">Aucun retrait enregistré.</p>

        @endforelse
    </section>
    <section class="{{ $card }}" aria-label="Coût du calcul Matching">
        <h3 class="font-extrabold">Coût par candidat — évolution horaire</h3>
        <p class="mt-2 text-[12px] text-muted">Durée totale des tranches réussies / candidats réellement examinés ; comprend les accès à la base et les écritures. Ce n’est pas une mesure CPU pure. Sans candidat, aucun coût unitaire n’est déduit.</p>
        <div class="mt-2 max-h-72 overflow-auto"><table class="w-full text-left text-[12px]"><thead><tr><th class="p-2">Heure</th><th class="p-2">Examinés</th><th class="p-2">Durée</th><th class="p-2">ms/candidat</th></tr></thead><tbody>

        @forelse ($matching_cost ?? [] as $cost)<tr class="border-t border-line"><td class="p-2">{{ $cost->hour }}</td><td class="p-2">{{ $cost->examined }}</td><td class="p-2">{{ $cost->duration_ms }} ms</td><td class="p-2">{{ $cost->ms_per_candidate === null ? 'non mesuré' : number_format((float) $cost->ms_per_candidate, 2, ',', ' ') }}</td></tr>
        @empty<tr><td colspan="4" class="p-2">Aucun calcul instrumenté sur la période.</td></tr>
        @endforelse
        </tbody></table></div>

        @foreach ($cancelled_reasons ?? [] as $cancel)<p class="mt-2 text-[12px]">Abandon {{ $label[$cancel->cancel_reason] ?? 'sujet obsolète' }} : {{ $cancel->total }}</p>
        @endforeach

        @foreach ($failed_searches ?? [] as $search)<div class="mt-3 border-t border-line pt-3"><p class="text-[12px]">Recherche échouée le {{ $search->finished_at }} · référence {{ substr($search->id, 0, 8) }}</p><button type="button" wire:click="retryMatching('{{ $search->id }}')" wire:loading.attr="disabled" class="mt-2 min-h-11 rounded-button border border-line px-3 text-[13px] font-bold">Relancer après vérification du sujet actuel</button></div>
        @endforeach
        <p class="mt-2 text-[12px] text-muted">La justification du bloc Execution est obligatoire pour chaque relance.</p>
    </section>

        @endif
    <section class="{{ $card }}" aria-label="Évolution horaire des exécutions">
        <h3 class="font-extrabold">Execution — évolution horaire</h3>
        <div class="mt-2 max-h-72 overflow-auto"><table class="w-full text-left text-[12px]"><thead><tr><th class="p-2">Heure</th><th class="p-2">Passages de tâches</th><th class="p-2">Échecs</th><th class="p-2">Reprises</th><th class="p-2">Durée</th></tr></thead><tbody>

        @forelse ($execution_hours ?? [] as $hour)<tr class="border-t border-line"><td class="p-2">{{ $hour->hour }}</td><td class="p-2">{{ $hour->runs }}</td><td class="p-2">{{ $hour->failures }}</td><td class="p-2">{{ $hour->retries }}</td><td class="p-2">{{ $hour->duration_ms }} ms</td></tr>
        @empty<tr><td colspan="5" class="p-2">Aucune exécution instrumentée sur la période.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
    <section id="incidents" class="{{ $card }} scroll-mt-40" aria-labelledby="t-incidents">
        <h2 id="t-incidents" class="text-[18px] font-extrabold">Incidents</h2>
        <p class="mt-2 text-[12px] text-muted">Anomalies issues des mesures existantes, sans registre administratif supplémentaire. Les signaux historiques ne prouvent pas une anomalie encore actuelle.</p>

        @forelse ($incidents as $incident)
            <article class="mt-3 border-t border-line pt-3"><p class="text-[13px] font-bold">{{ $incident['engine'] }} · {{ $incident['current'] ? 'Actuel' : 'Historique' }} · {{ $incident['title'] }}</p><p class="mt-1 text-[12px] text-muted">{{ $incident['last_at'] }}</p>
        @if (isset($incident['anchor']))<button type="button" wire:click="investigate('{{ $incident['anchor'] }}', '{{ $incident['dimension'] }}')" class="mt-2 min-h-11 rounded-button border border-line px-3 text-[13px] font-bold">Examiner les événements associés</button>
        @endif</article>

        @empty<p class="mt-2 text-[13px] text-muted">Aucune anomalie remontée par les sources disponibles.</p>
        @endforelse
    </section>
</div>
