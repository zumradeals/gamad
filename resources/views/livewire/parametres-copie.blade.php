@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y à H:i');
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Copie hors serveur</h1>
        <p class="text-muted">Réservé au super administrateur. Chaque nuit, la sauvegarde (base, fichier de configuration, photos de profil) est copiée <strong>chiffrée</strong> vers le stockage ci-dessous. Chaque modification est inscrite au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    @if ($shownPassphrase)
        <section aria-labelledby="phrase-titre" class="flex flex-col gap-3 rounded-card border-2 border-ink bg-type-call-bg p-4 md:p-[18px]">
            <h2 id="phrase-titre" class="text-[18px] font-extrabold text-type-call-fg">Phrase de secours : à noter maintenant</h2>
            <p class="break-all rounded-button bg-card px-3.5 py-3 font-mono text-[18px] font-extrabold tracking-wide" data-testid="phrase">{{ $shownPassphrase }}</p>
            <p class="text-[14px] leading-normal">Notez-la <strong>hors de ce serveur</strong> (gestionnaire de mots de passe, papier en lieu sûr). C’est la seule clé des copies : sans elle, elles sont illisibles pour tout le monde, y compris pour GAMAD. Avec elle, un nouveau serveur peut tout relire.</p>
            <x-button type="button" wire:click="noted">J’ai noté la phrase</x-button>
        </section>
    @endif

    <section aria-labelledby="etat" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="etat" class="text-[18px] font-extrabold">État de la copie</h2>
        @if (! $configured)
            <p class="text-[14px] font-bold text-type-call-fg">Non configurée : aucune sauvegarde n’existe hors du serveur.</p>
        @else
            <p class="text-[14px] font-bold text-type-knowledge-fg">Configurée : la copie se lance chaque nuit vers 4 h 30.</p>
            @if ($status['derniere_reussite'])
                <p class="text-[14px]"><strong>Dernière copie réussie et vérifiée :</strong> {{ $date($status['derniere_reussite']) }}.</p>
            @else
                <p class="text-[14px]"><strong>Dernière copie réussie :</strong> aucune pour le moment.</p>
            @endif
            @if ($stale)
                <p role="alert" class="rounded-button border-2 border-danger px-3 py-2 text-[14px] font-bold text-danger">
                    Attention : aucune copie réussie depuis plus de {{ \App\Services\BackupStorage::STALE_AFTER_HOURS }} heures. Contrôlez le serveur.
                </p>
            @endif
            @if ($status['dernier_essai'])
                <p class="text-[14px]"><strong>Dernier essai de restauration réussi :</strong> {{ $date($status['dernier_essai']) }}.</p>
            @else
                <p class="text-[14px]"><strong>Essai de restauration :</strong> jamais fait. Il prouve que les copies se relisent vraiment ; il se lance sur le serveur (voir « Essayer le Lot 5c »).</p>
            @endif
            @if ($status['dernier_echec'])
                <p class="text-[13px] text-muted">Dernier incident : {{ $date($status['dernier_echec']['quand']) }} — {{ $status['dernier_echec']['message'] }}@if ($status['dernier_echec']['depasse']) (depuis, une copie a réussi)@endif</p>
            @endif
        @endif
    </section>

    <form wire:submit="save" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 class="text-[18px] font-extrabold">{{ $configured ? 'Changer les accès' : 'Renseigner les accès' }}</h2>
        <p class="text-[13px] text-muted">Stockage objet compatible S3. Les accès sont <strong>essayés pour de bon</strong> (un petit fichier est écrit, relu puis effacé) avant d’être enregistrés ; sinon rien n’est enregistré. Les clés sont chiffrées en base et ne sont jamais réaffichées.</p>

        <x-wire-field name="endpoint" label="Adresse du stockage" type="url" autocomplete="off" hint="Commence par https://" placeholder="https://s3.exemple.org" />
        <x-wire-field name="bucket" label="Nom du bucket" autocomplete="off" />
        <x-wire-field name="region" label="Région (facultative)" autocomplete="off" />
        <x-wire-field name="accessKey" label="Clé d’accès" type="password" autocomplete="off"
                      :hint="$configured ? 'Enregistrée. Laissez vide pour la conserver.' : null" />
        <x-wire-field name="secretKey" label="Clé secrète" type="password" autocomplete="off"
                      :hint="$configured ? 'Enregistrée. Laissez vide pour la conserver.' : null" />

        @if (! $hasPassphrase)
            <x-wire-field name="passphrase" label="Phrase de secours existante (facultative)" autocomplete="off"
                          hint="Laissez vide pour en générer une. À remplir seulement pour relire des copies déjà faites, sur un nouveau serveur." />
        @endif

        @error('storage')
            <p role="alert" class="rounded-button border-2 border-danger px-3 py-2 text-[14px] font-bold text-danger">{{ $message }}</p>
        @enderror

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer et tester la connexion</span>
            <span wire:loading wire:target="save">Essai en cours…</span>
        </x-button>
    </form>

    @if ($hasPassphrase)
        <section aria-labelledby="reafficher" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="reafficher" class="text-[18px] font-extrabold">Phrase de secours</h2>
            <p class="text-[13px] text-muted">Elle est enregistrée (chiffrée) et ne se change pas : les copies déjà faites deviendraient illisibles. Vous pouvez la réafficher, après confirmation de votre mot de passe ; chaque affichage est inscrit au journal.</p>
            @if ($askPassword)
                <form wire:submit="reveal" class="flex flex-col gap-3">
                    <x-wire-field name="password" label="Votre mot de passe" type="password" autocomplete="current-password" />
                    <x-button wire:loading.attr="disabled" wire:target="reveal">Afficher la phrase de secours</x-button>
                </form>
            @else
                <x-button type="button" variant="secondary" wire:click="askToReveal">Réafficher la phrase de secours</x-button>
            @endif
        </section>
    @endif
</div>
