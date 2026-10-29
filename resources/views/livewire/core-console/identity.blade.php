<section id="identity" class="{{ $card }} scroll-mt-40" aria-labelledby="t-identity">
        <h2 id="t-identity" class="text-[18px] font-extrabold">Identity et sécurité</h2>

        @if (isset($security))
            <p class="mt-2 text-[13px]">Situation sur la période : {{ $security['totals']['success'] }} connexion(s) réussie(s) · {{ $security['totals']['failure'] }} échec(s) · {{ $security['totals']['limited'] }} limitation(s) · {{ $security['totals']['refused'] }} refus.</p>
            <p class="mt-2 text-[13px] text-muted">{{ $security['targets'] }} adresse(s) visée(s), connues ou inconnues · {{ $security['sources'] }} source(s) apparente(s) · {{ $security['unknown_sources'] }} événement(s) sans origine réseau disponible. Les origines absentes ne sont jamais assimilées à une même source.</p>
            <p class="mt-2 text-[12px] text-muted">Détails : {{ config('core_console.security.detail_days') }} jours maximum ; comptes horaires sans identifiant : {{ config('core_console.security.aggregate_days') }} jours. IP et navigateur chiffrés au repos. Aucun export ni blocage manuel.</p>
            <details class="mt-3">
                <summary class="min-h-11 cursor-pointer py-3 text-[14px] font-bold">Tendances horaires des accès</summary>
                <div class="max-h-72 overflow-auto">
                    <table class="w-full text-left text-[12px]"><caption class="sr-only">Événements réels par heure ; heures absentes sans événement enregistré</caption><thead><tr><th class="p-2">Heure</th><th class="p-2">Réussies</th><th class="p-2">Échecs</th><th class="p-2">Limitées</th><th class="p-2">Refus</th></tr></thead><tbody>

        @forelse ($security['hours'] as $hour)<tr class="border-t border-line"><td class="p-2">{{ $hour['hour'] }}</td><td class="p-2">{{ $hour['success'] }}</td><td class="p-2">{{ $hour['failure'] }}</td><td class="p-2">{{ $hour['limited'] }}</td><td class="p-2">{{ $hour['refused'] }}</td></tr>
        @empty<tr><td colspan="5" class="p-2">Aucun événement enregistré ; cela ne prouve pas le bon fonctionnement des accès.</td></tr>
        @endforelse
                    </tbody></table>
                </div>
            </details>
            <details class="mt-3">
                <summary class="min-h-11 cursor-pointer py-3 text-[14px] font-bold">Sources récurrentes et comptes visés — dix premières références</summary>
                <p class="text-[12px] text-muted">Corrélations limitées aux événements d’authentification. Une source apparente peut être partagée ; une adresse visée peut être inconnue.</p>
                @foreach ($security['recurrent_sources'] as $source)
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2"><span class="text-[12px]">Source apparente {{ substr($source->source_key, 0, 10) }} · {{ $source->total }} événement(s)</span><button type="button" wire:click="investigate('{{ $source->anchor }}', 'source')" class="min-h-11 rounded-button border border-line px-3 text-[13px] font-bold">Examiner la source</button></div>
                @endforeach
                @foreach ($security['recurrent_targets'] as $target)
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2"><span class="text-[12px]">{{ $target->known ? 'Compte visé connu' : 'Adresse visée inconnue' }} · {{ substr($target->target_key, 0, 10) }} · {{ $target->total }} événement(s)</span><button type="button" wire:click="investigate('{{ $target->anchor }}', 'target')" class="min-h-11 rounded-button border border-line px-3 text-[13px] font-bold">Examiner le compte visé</button></div>
                @endforeach
            </details>
            <h3 class="mt-3 font-bold">Signaux actuels</h3>

        @forelse ($current_security_incidents as $incident)
                <article class="mt-2 rounded-button border border-line p-3"><p class="text-[14px] font-bold">{{ $incident['title'] }}</p><p class="mt-1 text-[12px] text-muted">{{ $incident['first_at'] }} → {{ $incident['last_at'] }} · {{ $incident['certainty'] }}
        @if ($incident['attempts'] !== null) · {{ $incident['attempts'] }} tentative(s), {{ $incident['sources'] }} source(s), {{ $incident['targets'] }} adresse(s) visée(s)
        @endif</p><button type="button" wire:click="investigate('{{ $incident['anchor'] }}', '{{ $incident['dimension'] }}')" class="mt-2 min-h-11 rounded-button border border-line px-3 text-[13px] font-bold">Ouvrir l’investigation</button></article>

        @empty
                <p class="mt-2 text-[13px] text-muted">Aucun signal anormal dans la fenêtre de détection.</p>

        @endforelse
            <h3 class="mt-3 font-bold">Événements récents</h3>
            <p class="text-[12px] text-muted">Références opaques uniquement. Toute ouverture des détails identifiants est inscrite au journal.</p>

        @foreach ($recent_security_events ?? [] as $event)
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2"><p class="text-[12px]">{{ $event->occurred_at }} · {{ $accessOutcomes[$event->outcome] ?? 'État inconnu' }} · {{ $event->authenticated_id !== null ? 'Compte authentifié' : ($event->target_id !== null ? 'Compte visé, auteur non identifié' : 'Adresse inconnue, auteur non identifié') }}</p><button type="button" wire:click="investigate('{{ $event->id }}')" class="min-h-11 rounded-button border border-line px-3 text-[13px] font-bold">Détail</button></div>

        @endforeach

        @else
            <p class="mt-2 text-[13px]">Mesures de sécurité indisponibles. Aucun état sain déduit.</p>

        @endif

        @if ($investigation !== null)
            <div id="console-investigation" tabindex="-1" class="mt-4 scroll-mt-40 rounded-card border-2 border-ink p-3" role="region" aria-label="Détail d’investigation">
                <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-extrabold">Détail d’investigation — consultation journalisée</h3><button type="button" wire:click="closeInvestigation" class="min-h-11 px-3 font-bold">Fermer</button></div>
                <p class="text-[12px] text-muted">{{ $investigation['geolocation'] }}</p>
                <p class="mt-2 text-[12px]">Rapprochement par {{ $investigation['dimension'] === 'target' ? 'adresse de connexion visée' : 'origine réseau apparente' }} à ± {{ config('core_console.security.window_minutes') }} minutes. Une connexion partagée peut réunir plusieurs personnes.</p>

        @if ($investigation['truncated'])<p class="mt-2 text-[13px] font-bold">Affichage borné aux 100 événements les plus proches de l’événement ouvert, en ordre chronologique.</p>
        @endif

        @foreach ($investigation['events'] as $event)
                    <article class="mt-3 break-words border-t border-line pt-3 text-[12px]">
                        <p class="font-bold">{{ $event->occurred_at->format('Y-m-d H:i:s.uP') }} · {{ $accessOutcomes[$event->outcome] ?? 'État inconnu' }}</p>
                        <p>Personne authentifiée : {{ $event->authenticated_id ?? 'aucune identité authentifiée dans cet événement' }}</p>
                        <p>Compte visé : {{ $event->target_id ?? 'adresse inconnue ou non disponible' }}
        @if ($event->sensitive_target) · compte sensible
        @endif</p>
                        <p>Origine réseau apparente : {{ $event->ip ?? 'non disponible' }}</p>
                        <p>Navigateur déclaré (non vérifié) : {{ $event->user_agent ?? 'non disponible' }}</p>
                        <p>Corrélation protégée de l’adresse visée : {{ $event->target_key === null ? 'non disponible' : substr($event->target_key, 0, 12) }}</p>
                        <p class="mt-1 text-muted">{{ $event->authenticated_id !== null ? 'Authentification du compte observée ; identité physique et légitimité de la connexion non démontrées.' : 'Auteur non identifié. Le compte visé n’est pas accusé.' }}</p>
                    </article>

        @endforeach
            </div>

        @endif
    </section>
