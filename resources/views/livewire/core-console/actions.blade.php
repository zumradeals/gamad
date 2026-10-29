<section id="execution" class="{{ $card }} scroll-mt-40" aria-labelledby="t-actions">
        <h2 id="t-actions" class="text-[18px] font-extrabold">Execution — interventions limitées</h2>
        <label for="console-reason" class="mt-3 block text-[13px] font-bold">Justification de l’intervention (10 à 500 caractères)</label>
        <textarea id="console-reason" wire:model="reason" maxlength="500" rows="2" class="mt-2 w-full rounded-button border border-line p-3 text-[14px]" placeholder="Pourquoi demander ce passage ou cette relance ?"></textarea>

        @error('reason')<p role="alert" class="text-[13px] text-red-800">{{ $message }}</p>
        @enderror
        <button type="button" wire:click="requestRunner" wire:loading.attr="disabled" class="mt-2 min-h-11 rounded-button bg-ink px-3 text-[13px] font-bold text-white">Demander un passage borné du lanceur</button>

        @if (isset($runner))
            @php($runnerMetrics = json_decode($runner->metrics ?? '{}', true))
            <p class="mt-3 text-[13px]">Dernier passage réel : {{ $runner->ran_at }} · origine {{ str_replace('Runner:', '', $runner->job) }} · résultat {{ $runner->outcome === 'success' ? 'passage terminé' : 'passage échoué' }} · {{ $runnerMetrics['processed'] ?? 0 }} tâche(s) traitée(s) · {{ ($runnerMetrics['contended'] ?? 0) ? 'verrou occupé, aucun travail entrepris' : (($runnerMetrics['idle'] ?? 0) ? 'actif, aucune tâche disponible' : 'passage avec travail') }}.</p>

        @else<p class="mt-3 text-[13px] text-muted">Aucun passage du lanceur encore observé.</p>
        @endif

        @if (isset($last_console_run))
            @php($consoleMetrics = json_decode($last_console_run->metrics ?? '{}', true))
            <p class="mt-2 text-[13px] font-bold">Dernier passage demandé depuis la Console : {{ $last_console_run->ran_at }} · {{ $last_console_run->outcome === 'success' ? 'passage terminé' : 'échec du passage' }} · {{ $consoleMetrics['processed'] ?? 0 }} tâche(s) traitée(s) · {{ ($consoleMetrics['contended'] ?? 0) ? 'verrou occupé, aucune tâche entreprise' : (($consoleMetrics['idle'] ?? 0) ? 'aucune tâche disponible' : 'travail effectué') }}.</p>
        @endif
        @if (isset($purges))<p class="mt-2 text-[13px]">Purges de fichiers : {{ $purges['retryable'] }} à reprendre, {{ $purges['failed'] }} abandonnée(s). Aucune remise à zéro automatique.</p>
        @endif

        @foreach ($abandoned_purges ?? [] as $purge)<p class="mt-1 break-all text-[12px] text-muted">Purge {{ $purge->id }} · {{ $purge->attempts }} essais · dernier essai {{ $purge->last_attempt_at }} · chemin et contenu non exposés.</p>
        @endforeach
    </section>
