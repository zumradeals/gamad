<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Espace</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Paramètres</h1>
        <p class="text-muted">Réservé au super administrateur. Chaque modification est inscrite au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    <section aria-labelledby="listes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="listes" class="text-[18px] font-extrabold">Listes de référence</h2>
        <p class="text-[13px] text-muted">Les choix proposés dans les profils. On ajoute, on renomme, on désactive ; on ne supprime jamais.</p>
        <div class="flex flex-col gap-2 sm:flex-row">
            <x-button :href="route('espace.parametres.domaines')" variant="secondary" class="sm:flex-1">Domaines</x-button>
            <x-button :href="route('espace.parametres.territoires')" variant="secondary" class="sm:flex-1">Territoires</x-button>
        </div>
    </section>

    <section aria-labelledby="textes" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="textes" class="text-[18px] font-extrabold">Charte et mentions légales</h2>
        <p class="text-[13px] text-muted">Relire, compléter et adopter les textes publics. Tant qu’ils ne sont pas adoptés, rien n’est publié.</p>
        <x-button :href="route('espace.parametres.textes')" variant="secondary">Ouvrir</x-button>
    </section>

    <section aria-labelledby="copie" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="copie" class="text-[18px] font-extrabold">Copie hors serveur</h2>
        <p class="text-[13px] text-muted">Les accès au stockage où la sauvegarde nocturne est copiée, chiffrée, hors de ce serveur.</p>
        <x-button :href="route('espace.parametres.copie')" variant="secondary">Ouvrir</x-button>
    </section>

    @if (\App\Support\Experience::initial())
    <details class="rounded-card border border-line bg-card p-4 md:p-[18px]">
        <summary class="min-h-11 cursor-pointer content-center font-extrabold">Suivi financier antérieur — administration</summary>
        <p class="mb-4 text-[14px] text-muted">Le parcours initial reste centré sur le Fil et les Personnes. Ces réglages servent au suivi des engagements existants ; ils ne réactivent pas les nouvelles recharges ou contributions.</p>
    @endif
    <section aria-labelledby="portefeuille" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="portefeuille" class="text-[18px] font-extrabold">Portefeuilles</h2>
        <p class="text-[13px] text-muted">Ouvrir ou fermer les portefeuilles ZAHAB et fixer les plafonds de solde.</p>
        <x-button :href="route('espace.parametres.portefeuille')" variant="secondary">Ouvrir</x-button>
    </section>

    <section aria-labelledby="contributions" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="contributions" class="text-[18px] font-extrabold">Contributions</h2>
        <p class="text-[13px] text-muted">Le fonds de contribution : dépenses de ZUMRA à valider, affectation aux projets, versements.</p>
        <x-button :href="route('espace.contributions')" variant="secondary">Ouvrir</x-button>
    </section>

    <section aria-labelledby="paiement" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="paiement" class="text-[18px] font-extrabold">Paiement</h2>
        <p class="text-[13px] text-muted">Les clés GeniusPay, le mode (essai ou réel) et le suivi des recharges.</p>
        <x-button :href="route('espace.parametres.paiement')" variant="secondary">Ouvrir</x-button>
    </section>

    @if (\App\Support\Experience::initial())</details>@endif

    <form wire:submit="save" class="flex flex-col gap-4">
        <section aria-labelledby="courriel" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="courriel" class="text-[18px] font-extrabold">Courriel</h2>
            <p class="text-[13px] text-muted">Tant que le serveur SMTP n’est pas renseigné, aucun courriel n’est envoyé.</p>

            <x-wire-field name="mailHost" label="Serveur SMTP" autocomplete="off" />
            <x-wire-field name="mailPort" label="Port" inputmode="numeric" autocomplete="off" />

            <div class="flex flex-col gap-1.5">
                <label for="mailEncryption" class="text-[13px] font-semibold text-ink">Chiffrement</label>
                <select id="mailEncryption" wire:model="mailEncryption"
                        @class(['min-h-11 w-full rounded-button border bg-card px-3 text-[15px] text-ink', $errors->has('mailEncryption') ? 'border-danger' : 'border-chip-line'])>
                    @foreach ($encryptions as $value => $libelle)
                        <option value="{{ $value }}">{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('mailEncryption')
                    <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>

            <x-wire-field name="mailUsername" label="Identifiant" autocomplete="off" />

            <x-wire-field name="mailPassword" label="Mot de passe" type="password" autocomplete="new-password"
                          :hint="$hasMailPassword ? 'Un mot de passe est enregistré. Laissez ce champ vide pour le conserver.' : 'Aucun mot de passe enregistré.'"
                          placeholder="{{ $hasMailPassword ? '••••••••' : '' }}" />
            @if ($hasMailPassword)
                <label class="flex min-h-11 items-center gap-3 text-[14px]">
                    <input type="checkbox" wire:model="clearMailPassword" class="size-5 rounded border-chip-line accent-ink">
                    Retirer le mot de passe enregistré
                </label>
            @endif

            <x-wire-field name="mailFromAddress" label="Adresse de l’expéditeur" type="email" autocomplete="off" />
            <x-wire-field name="mailFromName" label="Nom de l’expéditeur" autocomplete="off" />
        </section>

        <section aria-labelledby="plateforme" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="plateforme" class="text-[18px] font-extrabold">Plateforme</h2>
            <x-wire-field name="appName" label="Nom de la plateforme" autocomplete="off" />
            <x-wire-field name="appUrl" label="Adresse publique" type="url" autocomplete="off"
                          hint="Exemple : https://reseau.dgafrique.com" />
        </section>

        <section aria-labelledby="securite" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="securite" class="text-[18px] font-extrabold">Sécurité</h2>
            <x-wire-field name="limitRegister" label="Inscriptions par heure et par adresse IP" inputmode="numeric" autocomplete="off"
                          hint="Une même adresse IP est souvent partagée (réseau mobile, réunion sur un même Wi-Fi)." />
            <x-wire-field name="limitLogin" label="Tentatives de connexion par minute" inputmode="numeric" autocomplete="off" />
        </section>

        <x-button wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>

    <section aria-labelledby="test" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="test" class="text-[18px] font-extrabold">Tester l’envoi de courriels</h2>
        <p class="text-muted">Envoie un message à votre adresse, avec les réglages enregistrés (enregistrez d’abord vos modifications).</p>

        @if ($testMessage)
            @if ($testFailed)
                <p role="alert" class="rounded-button border border-danger bg-card px-4 py-3 text-[14px] font-semibold text-danger">{{ $testMessage }}</p>
            @else
                <x-status>{{ $testMessage }}</x-status>
            @endif
        @endif

        <x-button type="button" variant="secondary" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail">
            <span wire:loading.remove wire:target="sendTestEmail">Envoyer un e-mail de test</span>
            <span wire:loading wire:target="sendTestEmail">Envoi en cours…</span>
        </x-button>
    </section>
</div>
