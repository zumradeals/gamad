@php
    $dur = fn (?int $ms) => $ms === null ? '—' : ($ms >= 1000 ? number_format($ms / 1000, 1, ',', ' ').' s' : $ms.' ms');
    $label = ['hidden' => 'Publication masquée', 'closed' => 'Fermée ou close', 'resolved' => 'Besoin résolu', 'expired' => 'Période terminée', 'blocked' => 'Blocage', 'consent' => 'Consentement retiré', 'unavailable' => 'Indisponibilité', 'suspended' => 'Compte suspendu'];
    $card = 'rounded-card border border-line bg-card p-4';
@endphp
<div class="flex flex-col gap-4">
    <header class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-[22px] font-extrabold">Traitements du Core</h1>
            <p class="text-[13px] text-muted">Mesures réelles de l’exécution et du Matching. Des comptes et des durées : aucun contenu, aucune personne.</p>
        </div>
        <div class="flex gap-1" role="group" aria-label="Période">
            @foreach ([1 => '1 h', 24 => '24 h', 168 => '7 jours'] as $h => $text)
                <button type="button" wire:click="setHours({{ $h }})" @class(['min-h-11 rounded-button border px-3 text-[13px] font-bold', $hours === $h ? 'border-ink bg-ink text-white' : 'border-line bg-card'])>{{ $text }}</button>
            @endforeach
        </div>
    </header>

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
                <p class="text-[13px] text-muted">{{ $run['runs'] }} tentative(s) : {{ $run['success'] }} réussie(s), {{ $run['failure'] }} échouée(s), {{ $run['retries'] }} reprise(s) · durée médiane {{ $dur($run['p50_ms']) }}, p95 {{ $dur($run['p95_ms']) }}@foreach ($run['metrics'] as $name => $value) · {{ $name }} : {{ $value }}@endforeach</p>
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

    <section class="{{ $card }}" aria-labelledby="t-matching">
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
</div>
