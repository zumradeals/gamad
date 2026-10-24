<x-layouts.app title="Agir" narrow>
    <div class="flex flex-col gap-3.5">
        <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
            <a href="{{ route('fil') }}" aria-label="Retour à l’accueil" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
            <h1 class="flex-1 text-[17px] font-extrabold">Que voulez-vous faire avancer ?</h1>
        </div>

        <p class="text-[14px] leading-normal text-muted">Choisissez ce que vous voulez faire. Votre publication apparaîtra dans les publications, avec le bouton d’action qui convient.</p>

        @php
            $intentions = [
                ['Trouver de l’aide', 'Faire réaliser quelque chose ou résoudre une difficulté.', ['besoin' => 'Exprimer mon besoin']],
                ['Apprendre quelque chose', 'Acquérir un savoir-faire pour pouvoir le faire vous-même.', ['apprendre' => 'Dire ce que je veux apprendre']],
                ['Proposer quelque chose', 'Mettre votre temps, vos services ou vos connaissances à disposition.', ['aide' => 'Proposer une aide ou un service', 'savoir' => 'Transmettre un savoir']],
                ['Partager', 'Montrer une réalisation, raconter une expérience ou partager une idée.', ['partage' => 'Partager une publication', 'ma-realisation' => 'Montrer ma réalisation']],
            ];
        @endphp
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($intentions as [$title, $description, $choices])
                <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                    <h2 class="text-[18px] font-extrabold">{{ $title }}</h2>
                    <p class="text-[14px] leading-normal text-muted">{{ $description }}</p>
                    @foreach ($choices as $slug => $label)
                        <a href="{{ route('agir.publier', $slug) }}" class="inline-flex min-h-11 items-center justify-between gap-2 rounded-button border border-chip-line px-3 py-2 text-[14px] font-bold">{{ $label }} <x-icon name="next" :size="20" /></a>
                    @endforeach
                </section>
            @endforeach
        </div>

        <p class="text-[12px] leading-normal text-muted">Vous publiez en tant que vous-même ({{ auth()->user()->name }}).@if (! \App\Support\Experience::initial() && auth()->user()->managedZumraIds() !== []) Pour publier au nom d’une ZUMRA que vous gérez, ouvrez-la dans l’onglet ZUMRA.@endif</p>
    </div>
</x-layouts.app>
