<x-layouts.public title="Charte de conduite">
    <article class="flex flex-col gap-4">
        <header class="flex flex-col gap-1.5">
            @if ($projet)
                <p class="rounded-button border-2 border-ink bg-type-call-bg px-3 py-2.5 text-[13px] font-bold text-type-call-fg">Projet de charte, non adopté : vous seul le lisez. Relisez-le, puis adoptez-le dans Espace → Paramètres → Charte et mentions légales.</p>
            @endif
            <h1 class="text-[26px] font-extrabold leading-tight">Charte de conduite</h1>
            @if ($adoptee)
                <p class="text-[13px] text-muted">Adoptée le {{ $adoptee->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}.</p>
            @endif
        </header>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">1. L’esprit de GAMAD</h2>
            <p class="leading-relaxed">GAMAD est un réseau social ouvert à tous. On y présente ses capacités et ses expériences, on partage ses réalisations et on rencontre des personnes pour apprendre, s’entraider ou travailler ensemble.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">2. Le respect des personnes et des croyances</h2>
            <p class="leading-relaxed">Toutes les croyances sont respectées. Une ZUMRA religieuse a exactement les mêmes droits qu’une autre. Ne publiez rien d’offensant, de haineux ou de harcelant envers une personne, un groupe ou une conviction.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">3. L’honnêteté</h2>
            <p class="leading-relaxed">Ne diffusez pas de fausse information, ne vous faites pas passer pour quelqu’un d’autre, et ne demandez pas d’argent par des moyens douteux. La publicité et le courrier indésirable n’ont pas leur place ici.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">4. La vie privée</h2>
            <p class="leading-relaxed">Ne publiez ni le téléphone, ni l’adresse e-mail, ni les données personnelles d’autrui. GAMAD n’affiche jamais l’adresse e-mail ni le téléphone d’une personne à quelqu’un d’autre. Les textes sont des textes simples : les adresses web n’y sont pas cliquables.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">5. Ce que GAMAD ne fait pas</h2>
            <ul class="flex list-disc flex-col gap-1.5 pl-5 leading-relaxed">
                <li>Il n’y a pas de compteur de « j’aime » : seuls des engagements réels sont comptés, comme « 4 personnes proposent leur aide ».</li>
                <li>Il n’y a ni note, ni classement de personnes.</li>
                <li>L’argent n’achète jamais la visibilité.</li>
            </ul>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">6. Signaler et modérer</h2>
            <p class="leading-relaxed">Toute personne connectée peut signaler une publication (contenu offensant ou haineux, arnaque ou demande d’argent suspecte, fausse information, publicité ou spam, atteinte à la vie privée). La modération peut masquer une publication : son auteur voit le motif et peut s’expliquer, et la modération décide de la rétablir ou de la maintenir. Chaque acte de modération est inscrit dans un journal que personne ne peut modifier ni effacer.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-[18px] font-extrabold">7. Les responsables de ZUMRA</h2>
            <p class="leading-relaxed">Le fondateur et les responsables d’une ZUMRA gèrent ses demandes, ses invitations, ses publications et ses projets, et répondent de la façon dont ils le font. Ils appliquent la présente charte dans leur ZUMRA.</p>
        </section>
    </article>
</x-layouts.public>
