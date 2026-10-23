<x-layouts.guest wide bare>
    <div class="flex flex-col gap-10 py-2 md:gap-14">
        <section class="flex flex-col gap-5 md:max-w-3xl" aria-labelledby="accroche">
            <h1 id="accroche" class="text-[30px] font-extrabold leading-[1.15] tracking-[-0.5px] md:text-[44px]">Des personnes. Des actions. Un impact réel.</h1>
            <p class="text-[17px] leading-relaxed md:text-[19px]">Apportez ce que vous savez faire, apprenez ce qui vous manque, agissez avec d’autres.</p>

            <div class="flex flex-col gap-3 md:max-w-md md:flex-row">
                <x-button :href="route('register')" class="md:flex-1">Créer un compte</x-button>
                <x-button :href="route('login')" variant="secondary" class="md:flex-1">Se connecter</x-button>
            </div>

            <p class="text-[13px] font-semibold text-muted">Formation — Travail — Adoration</p>
        </section>

        <section aria-labelledby="comment" class="flex flex-col gap-4">
            <h2 id="comment" class="text-[22px] font-extrabold md:text-[26px]">Comment cela marche</h2>
            <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-4">
                <article class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
                    <h3 class="text-[17px] font-extrabold">Vous venez avec ce que vous êtes</h3>
                    <p class="text-[14px] leading-normal">Vous dites ce que vous pouvez apporter, ce que vous voulez apprendre, et ce qui vous manque. Les autres vous trouvent grâce à vos capacités.</p>
                </article>
                <article class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
                    <h3 class="text-[17px] font-extrabold">Des ZUMRA regroupent les personnes</h3>
                    <p class="text-[14px] leading-normal">Une ZUMRA est une communauté qui apprend, travaille et agit ensemble autour d’un seul domaine. Toute personne peut en créer une.</p>
                </article>
                <article class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
                    <h3 class="text-[17px] font-extrabold">Des projets naissent dans les ZUMRA</h3>
                    <p class="text-[14px] leading-normal">Un projet a un but, des étapes, une équipe et une page publique : chacun voit où il en est, sans compte.</p>
                </article>
                <article class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
                    <h3 class="text-[17px] font-extrabold">Le Fil montre ce qui se fait</h3>
                    <p class="text-[14px] leading-normal">À la place des « j’aime », des boutons d’action : « Je peux aider », « Je veux apprendre », « Je soutiens ».</p>
                </article>
            </div>
        </section>

        @if ($figures !== [] || $recentZumras->isNotEmpty() || $recentProjects->isNotEmpty())
            <section aria-labelledby="reel" class="flex flex-col gap-4">
                <h2 id="reel" class="text-[22px] font-extrabold md:text-[26px]">Ce qui se passe en ce moment</h2>

                @if ($figures !== [])
                    <ul class="flex flex-wrap gap-3" aria-label="GAMAD en chiffres">
                        @foreach ($figures as $label => $n)
                            <li class="flex min-w-[130px] flex-col items-center gap-0.5 rounded-card border border-line bg-card px-5 py-3.5">
                                <span class="text-[26px] font-extrabold leading-tight">{{ $n }}</span>
                                <span class="text-[12px] text-muted">{{ $label === 'personnes' && $n === 1 ? 'personne' : ($label === 'projets' && $n === 1 ? 'projet' : $label) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($recentZumras as $zumra)
                        <a href="{{ route('zumras.show', $zumra) }}" class="flex items-center gap-3 rounded-card border border-line bg-card p-4 text-ink no-underline hover:text-ink">
                            <x-zumra-initials :zumra="$zumra" :size="48" />
                            <span class="flex min-w-0 flex-col">
                                <span class="break-words text-[15px] font-extrabold">{{ $zumra->name }}</span>
                                <span class="text-[12px] text-muted">ZUMRA · {{ collect([$zumra->domain->name, $zumra->city?->label()])->filter()->implode(' · ') }}</span>
                            </span>
                        </a>
                    @endforeach
                    @foreach ($recentProjects as $project)
                        <a href="{{ route('projets.show', $project) }}" class="flex flex-col gap-1.5 rounded-card border border-line bg-card p-4 text-ink no-underline hover:text-ink">
                            <span class="self-start rounded-chip px-2.5 py-0.5 text-[12px] font-bold {{ $project->state->badgeClasses() }}">{{ $project->state->label() }}</span>
                            <span class="break-words text-[15px] font-extrabold">{{ $project->name }}</span>
                            <span class="text-[12px] text-muted">Projet de {{ $project->zumra->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="promesses" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5 md:p-6">
            <h2 id="promesses" class="text-[22px] font-extrabold">Ce que GAMAD ne fait pas</h2>
            <ul class="flex list-disc flex-col gap-1.5 pl-5 leading-relaxed">
                <li>Pas de compteur de « j’aime », pas de note, pas de classement de personnes.</li>
                <li>Votre adresse e-mail et votre téléphone ne sont jamais montrés aux autres.</li>
                <li>Un compte ne fait pas de vous un membre du Mouvement GAMAD.</li>
            </ul>
        </section>

        <section class="flex flex-col gap-3 md:max-w-md" aria-label="Rejoindre GAMAD">
            <x-button :href="route('register')">Créer un compte</x-button>
        </section>
    </div>
</x-layouts.guest>
