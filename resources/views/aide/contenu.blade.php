@php
    $groupes = [
        'Mon compte' => [
            ['Comment créer un compte ?', 'Choisissez « Créer un compte », indiquez un nom affiché, une adresse e-mail et un mot de passe de 8 caractères au minimum. Rien d’autre n’est obligatoire : vous pouvez compléter votre profil plus tard. Votre adresse e-mail reste visible par vous seul.'],
            ['Je ne reçois pas l’e-mail de vérification.', 'Regardez d’abord dans vos courriers indésirables. Sur la page de vérification, vous pouvez demander un nouvel envoi. Si rien n’arrive, écrivez-nous depuis la page Contact en indiquant le sujet « Problème de compte ».'],
            ['J’ai oublié mon mot de passe.', 'Sur la page de connexion, choisissez « Mot de passe oublié » et suivez les indications. Pour votre sécurité, ne communiquez jamais votre mot de passe, même à l’équipe de GAMAD.'],
        ],
        'Rencontrer et échanger' => [
            ['Comment demander une relation ?', 'Ouvrez le profil d’une personne et choisissez « Entrer en relation ». La personne décide d’accepter ou non. Vous retrouvez vos demandes reçues dans « À faire maintenant » et dans Mes relations.'],
            ['Qui voit ce que je publie ?', 'Vous choisissez la visibilité de votre profil et de vos publications. Votre adresse e-mail et votre téléphone ne sont jamais montrés à d’autres personnes.'],
            ['Comment publier un besoin ou une proposition ?', 'Utilisez le bouton « Agir », puis choisissez ce que vous voulez faire. Vous pouvez dire ce qui vous manque, proposer ce que vous savez faire ou partager une réalisation.'],
        ],
        'Sécurité et confiance' => [
            ['Comment signaler un contenu ?', 'Sur une publication, un commentaire ou un profil, utilisez « Signaler » et dites en quelques mots pourquoi. Le signalement est examiné par la modération ; la personne concernée peut présenter son explication.'],
            ['Que faire si je reçois un message gênant ?', 'Vous n’avez pas à répondre. Signalez le message, et si besoin écrivez-nous depuis la page Contact avec le sujet « Signaler un problème ».'],
            ['Comment protéger ma vie privée ?', 'Vérifiez ce que vous rendez visible dans votre profil, ne publiez pas les coordonnées d’autrui et n’envoyez jamais d’argent à une personne que vous ne connaissez pas. La charte de conduite précise les règles de la communauté lorsqu’elle est adoptée.'],
        ],
    ];
@endphp
<div class="flex flex-col gap-6">
    <header class="flex flex-col gap-2">
        <h1 class="text-[28px] font-extrabold leading-tight tracking-[-0.5px] md:text-[34px]">Aide</h1>
        <p class="max-w-2xl text-[16px] leading-relaxed text-muted">Des réponses courtes aux questions les plus fréquentes. Vous ne trouvez pas la vôtre ? <a href="{{ route('contact') }}" class="font-bold underline">Écrivez-nous</a>.</p>
    </header>

    @foreach ($groupes as $titre => $questions)
        <section aria-labelledby="g-{{ $loop->index }}" class="flex flex-col gap-2">
            <h2 id="g-{{ $loop->index }}" class="text-[18px] font-extrabold">{{ $titre }}</h2>
            @foreach ($questions as [$question, $reponse])
                <details class="rounded-card border border-line bg-card px-4 py-1 md:px-5">
                    <summary class="flex min-h-12 cursor-pointer items-center py-2 text-[15px] font-bold">{{ $question }}</summary>
                    <p class="pb-3 pt-1 text-[15px] leading-relaxed">{{ $reponse }}</p>
                </details>
            @endforeach
        </section>
    @endforeach

    <section aria-labelledby="g-contact" class="flex flex-col items-start gap-3 rounded-card border border-line bg-soft-blue p-5">
        <h2 id="g-contact" class="text-[18px] font-extrabold">Besoin d’un coup de main ?</h2>
        <p class="text-[15px] leading-relaxed">Écrivez-nous : votre message est lu par l’équipe de GAMAD.</p>
        <x-button :href="route('contact')" variant="accent">Nous écrire</x-button>
    </section>
</div>
