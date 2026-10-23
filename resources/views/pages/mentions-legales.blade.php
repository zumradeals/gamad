<x-layouts.public title="Mentions légales">
    <article class="flex flex-col gap-4">
        <header class="flex flex-col gap-1.5">
            @if ($projet)
                <p class="rounded-button border-2 border-ink bg-type-call-bg px-3 py-2.5 text-[13px] font-bold text-type-call-fg">Projet de mentions légales, non adopté : vous seul le lisez. Complétez les champs, relisez, puis adoptez-le dans Espace → Paramètres → Charte et mentions légales.</p>
            @endif
            <h1 class="text-[26px] font-extrabold leading-tight">Mentions légales</h1>
            @if ($adoptees)
                <p class="text-[13px] text-muted">Adoptées le {{ $adoptees->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}.</p>
            @endif
        </header>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">Éditeur du site</h2>
            <p class="leading-relaxed">{{ $valeurs['legal_publisher'] ?: '[à compléter]' }}</p>
            <p class="leading-relaxed"><strong>Responsable de la publication :</strong> {{ $valeurs['legal_director'] ?: '[à compléter]' }}</p>
            <p class="leading-relaxed"><strong>Contact :</strong> {{ $valeurs['legal_contact'] ?: '[à compléter]' }}</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">Hébergement</h2>
            <p class="whitespace-pre-line leading-relaxed">{{ $valeurs['legal_host'] ?: '[à compléter]' }}</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">Les données que GAMAD garde</h2>
            <p class="leading-relaxed">À l’inscription : votre nom affiché, votre adresse e-mail (que vous seul voyez) et votre mot de passe, gardé sous une forme qui ne permet pas de le relire. Ensuite, ce que vous choisissez d’ajouter : profil, capacités, publications, réponses, messages, ZUMRA et projets. Votre adresse e-mail ne sert qu’à votre compte et aux messages que GAMAD vous envoie à propos de ce compte.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">Vos droits</h2>
            <p class="leading-relaxed">Vous pouvez modifier vos informations depuis votre profil, et supprimer votre compte depuis Espace : vos publications, vos réponses et vos messages sont alors supprimés. Les sauvegardes de sécurité, conservées pendant une durée limitée, s’effacent ensuite d’elles-mêmes. Le journal d’administration garde seulement le nom affiché des personnes qui ont accompli un acte de modération ou d’administration. Pour toute question sur vos données, écrivez à {{ $valeurs['legal_contact'] ?: '[adresse de contact à compléter]' }}.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">Témoins de connexion</h2>
            <p class="leading-relaxed">GAMAD n’utilise que les témoins nécessaires à votre connexion et à la sécurité des formulaires. Il n’y a ni publicité ni suivi de votre navigation.</p>
        </section>
    </article>
</x-layouts.public>
