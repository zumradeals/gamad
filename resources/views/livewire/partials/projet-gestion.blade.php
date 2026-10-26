{{-- Onglets de gestion du porteur (P1) : Accueil, Compétences, Présentation publique, Entreprise. Les collaborateurs lisent, le porteur seul modifie. --}}
@php
    $box = 'flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]';
    $field = 'min-h-12 w-full rounded-button border border-chip-line bg-card px-3.5 text-[15px]';
    $area = 'w-full rounded-button border border-chip-line bg-card px-3.5 py-3 text-[15px] leading-normal';
@endphp

@if ($tab === 'accueil')
    <div class="flex flex-col gap-3.5 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6">
        <section aria-labelledby="accueil-zumra" class="{{ $box }}">
            <h2 id="accueil-zumra" class="text-[17px] font-extrabold">ZUMRA d’accueil</h2>
            <p class="text-[15px]"><strong>{{ $project->zumra->name }}</strong> — {{ $project->hosting_state->label() }}.</p>
            @if ($project->hosting_state === \App\Enums\ProjectHostingState::Pending)
                <p class="text-[14px] text-muted">Le projet reste privé et n’est pas encore rattaché : une responsable ou un responsable de cette ZUMRA doit accepter. Elle ne voit que le nom, le but, le domaine et votre nom.</p>
            @elseif ($project->isHosted())
                <p class="text-[14px] text-muted">Le rattachement est confirmé. L’accueil ne transfère ni la propriété, ni la direction, ni les ressources du projet : le projet reste à son porteur.</p>
            @else
                <p class="text-[14px] text-muted">Aucune ZUMRA n’accueille ce projet pour l’instant. Il reste privé ; vous pouvez en proposer une autre.</p>
            @endif
            @if ($project->hosting_decided_at && ! $project->isHosted())<p class="text-[12px] text-muted">Réponse du {{ $date($project->hosting_decided_at) }}.</p>@endif
            @if ($project->origin_zumra_id && $project->origin_zumra_id !== $project->zumra_id)<p class="text-[12px] text-muted">ZUMRA d’origine conservée : {{ $project->originZumra?->name }}.</p>@endif
            @if ($manages && $project->hosting_state === \App\Enums\ProjectHostingState::Pending)
                <x-button type="button" variant="secondary" wire:click="withdrawHosting" wire:loading.attr="disabled" wire:target="withdrawHosting">Retirer ma demande</x-button>
            @endif
        </section>

        @if ($manages)
            <section aria-labelledby="changer-zumra" class="{{ $box }}">
                <h2 id="changer-zumra" class="text-[17px] font-extrabold">{{ $project->isHosted() ? 'Changer de ZUMRA' : 'Demander l’accueil d’une ZUMRA' }}</h2>
                @error('zumra_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                <form wire:submit="requestHosting" class="flex flex-col gap-2.5">
                    <label for="zumra-accueil" class="text-[13px] font-bold text-muted">ZUMRA</label>
                    <select id="zumra-accueil" wire:model="hostZumraId" class="{{ $field }}">
                        @foreach ($hostOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}{{ in_array($option->id, $mine ?? [], true) ? ' (dont je suis membre)' : '' }}</option>
                        @endforeach
                    </select>
                    <label for="mot-accueil" class="text-[13px] font-bold text-muted">Un mot pour la ZUMRA (facultatif)</label>
                    <textarea id="mot-accueil" wire:model="hostMessage" rows="3" maxlength="280" class="{{ $area }}"></textarea>
                    @error('hosting_message')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    <p class="text-[12px] text-muted">Une ZUMRA dont vous n’êtes pas membre doit accepter : vous n’avez pas à y adhérer. Changer de ZUMRA retire la présentation publique.</p>
                    <x-button wire:loading.attr="disabled" wire:target="requestHosting">Envoyer</x-button>
                </form>
            </section>
        @endif
    </div>
@elseif ($tab === 'competences')
    <section aria-labelledby="competences-projet" class="{{ $box }} lg:max-w-2xl">
        <h2 id="competences-projet" class="text-[17px] font-extrabold">Compétences recherchées</h2>
        @if ($manages)
            <p class="text-[13px] text-muted">Elles viennent du même référentiel que les profils. Elles ne deviennent publiques que si vous le choisissez dans « Présentation publique ».</p>
            @include('livewire.partials.skill-picker', [
                'list' => 'needs', 'items' => $needs, 'input' => $needsInput, 'suggestions' => $suggestions, 'tone' => 'neutral', 'plain' => true,
                'max' => \App\Models\Project::MAX_SKILLS, 'title' => 'Ce dont le projet a besoin',
                'help' => 'Touchez une suggestion ou écrivez la vôtre.', 'placeholder' => 'Ex. Couture, Comptabilité…',
            ])
            @error('needs')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            <x-button type="button" wire:click="saveNeeds" wire:loading.attr="disabled" wire:target="saveNeeds">Enregistrer</x-button>
        @else
            <ul class="flex flex-wrap gap-2">@forelse ($project->neededSkills as $skill)<li class="rounded-chip bg-type-need-bg px-3 py-1 text-[13px] font-bold text-type-need-fg">{{ $skill->name }}</li>@empty<li class="text-[14px] text-muted">Aucune compétence indiquée.</li>@endforelse</ul>
        @endif
    </section>
@elseif ($tab === 'vitrine')
    <div class="flex flex-col gap-3.5 lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start lg:gap-6">
        <section aria-labelledby="vitrine-projet" class="{{ $box }}">
            <h2 id="vitrine-projet" class="text-[17px] font-extrabold">Présentation publique</h2>
            @if ($project->isShowcasePublished())
                <p class="text-[14px] font-bold text-type-knowledge-fg">Publiée le {{ $date($project->showcase_published_at) }}.</p>
            @else
                <p class="text-[14px] text-muted">Pour l’instant, personne ne peut voir ce projet en dehors de vous et de votre équipe.</p>
            @endif
            @if (! $project->isHosted())
                <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] font-semibold">Une ZUMRA doit d’abord accepter d’accueillir le projet pour que vous puissiez le présenter.</p>
            @elseif ($manages)
                <form wire:submit="publishShowcase" class="flex flex-col gap-2.5">
                    <label for="resume-public" class="text-[13px] font-bold text-muted">Résumé public (ce que tout le monde lira)</label>
                    <textarea id="resume-public" wire:model="showSummary" rows="4" maxlength="{{ \App\Models\Project::SUMMARY_MAX }}" class="{{ $area }}"></textarea>
                    @error('summary')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    <label for="contribuer-public" class="text-[13px] font-bold text-muted">Comment contribuer ? (facultatif)</label>
                    <textarea id="contribuer-public" wire:model="showContribute" rows="3" maxlength="{{ \App\Models\Project::CONTRIBUTE_MAX }}" class="{{ $area }}"></textarea>
                    @error('contribute')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    <label class="flex min-h-11 items-center gap-3 text-[14px]"><input type="checkbox" wire:model="showOwner" class="size-5 shrink-0 accent-ink"><span>Montrer mon nom comme porteur du projet</span></label>
                    <label class="flex min-h-11 items-center gap-3 text-[14px]"><input type="checkbox" wire:model="showNeeds" class="size-5 shrink-0 accent-ink"><span>Montrer les compétences recherchées</span></label>
                    <p class="text-[12px] text-muted">Ne mettez ni téléphone, ni e-mail, ni budget, ni information confidentielle. Seuls ces textes et ces choix sont publiés.</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-button wire:loading.attr="disabled" wire:target="publishShowcase">{{ $project->isShowcasePublished() ? 'Mettre à jour la présentation' : 'Publier la présentation' }}</x-button>
                        @if ($project->isShowcasePublished())<x-button type="button" variant="secondary" wire:click="unpublishShowcase" wire:loading.attr="disabled" wire:target="unpublishShowcase">Retirer la présentation publique</x-button>@endif
                    </div>
                </form>
            @endif
        </section>

        <section aria-labelledby="vitrine-contenu" class="{{ $box }}">
            <h2 id="vitrine-contenu" class="text-[16px] font-extrabold">Ce qui est public, ce qui reste privé</h2>
            <p class="text-[13px] font-bold text-type-knowledge-fg">Public, seulement si vous le publiez</p>
            <ul class="list-disc pl-5 text-[14px]"><li>Nom du projet, domaine, localité</li><li>Le résumé et « Comment contribuer »</li><li>La ZUMRA d’accueil, l’étape du projet</li><li>Votre nom et les compétences, si vous le choisissez</li></ul>
            <p class="text-[13px] font-bold">Toujours privé</p>
            <ul class="list-disc pl-5 text-[14px]"><li>Le but détaillé, les étapes, l’équipe</li><li>Les publications et les documents du projet</li><li>Tout budget ou information stratégique</li></ul>
        </section>
    </div>
@elseif ($tab === 'entreprise')
    <div class="flex flex-col gap-3.5 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6">
        <section aria-labelledby="liens-entreprise" class="{{ $box }}">
            <h2 id="liens-entreprise" class="text-[17px] font-extrabold">Projet et entreprise</h2>
            <p class="text-[13px] text-muted">Un projet peut un jour être porté ou accompagné par une entreprise GAMAD. Le lien est proposé par le porteur et confirmé par l’entreprise ; la ZUMRA d’origine reste notée. Ce n’est ni une constitution de société, ni une contribution financière.</p>
            @error('company')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            @forelse ($companyLinks as $link)
                <div wire:key="lien-{{ $link->id }}" class="flex flex-col gap-1 border-t border-line-soft pt-3 first:border-0 first:pt-0">
                    <span class="text-[15px] font-extrabold">{{ $link->kindLabel() }} {{ $link->company->name }}</span>
                    <span class="text-[13px] text-muted">{{ ['requested' => 'En attente de confirmation', 'confirmed' => 'Confirmé', 'declined' => 'Refusé', 'ended' => 'Terminé'][$link->state] }}@if ($link->originZumra) · ZUMRA d’origine : {{ $link->originZumra->name }}@endif</span>
                    @if ($manages && in_array($link->state, ['requested', 'confirmed'], true))<x-button type="button" variant="secondary" wire:click="endCompanyLink('{{ $link->id }}')" wire:loading.attr="disabled" wire:target="endCompanyLink" class="self-start !px-4 !text-[14px]">{{ $link->state === 'requested' ? 'Retirer la demande' : 'Mettre fin au lien' }}</x-button>@endif
                </div>
            @empty
                <p class="text-[14px] text-muted">Aucune entreprise reliée à ce projet.</p>
            @endforelse
        </section>

        @if ($manages)
            <section aria-labelledby="relier-entreprise" class="{{ $box }}">
                <h2 id="relier-entreprise" class="text-[17px] font-extrabold">Relier une entreprise</h2>
                <form wire:submit="proposeCompany" class="flex flex-col gap-2.5">
                    <label for="entreprise-lien" class="text-[13px] font-bold text-muted">Entreprise vérifiée</label>
                    <select id="entreprise-lien" wire:model="companyId" class="{{ $field }}"><option value="">Choisir une entreprise</option>@foreach ($companyOptions as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                    <label for="type-lien" class="text-[13px] font-bold text-muted">Relation</label>
                    <select id="type-lien" wire:model="companyKind" class="{{ $field }}">@foreach (\App\Models\ProjectCompanyLink::KINDS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <x-button wire:loading.attr="disabled" wire:target="proposeCompany">Envoyer la demande</x-button>
                </form>
            </section>
        @endif
    </div>
@endif
