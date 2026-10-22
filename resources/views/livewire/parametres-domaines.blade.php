<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Domaines</h1>
        <p class="text-muted">Les domaines d’action que chaque personne peut choisir (trois au plus). On ajoute, on renomme, on désactive : on ne supprime jamais. Chaque acte est inscrit au journal d’administration.</p>
    </div>

    @if ($status)
        <x-status>{{ $status }}</x-status>
    @endif

    <form wire:submit="add" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 class="text-[18px] font-extrabold">Ajouter un domaine</h2>
        <x-wire-field name="newName" label="Nom du domaine" autocomplete="off" maxlength="100" />
        <x-button wire:loading.attr="disabled" wire:target="add">Ajouter</x-button>
    </form>

    <section aria-labelledby="liste" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="liste" class="text-[18px] font-extrabold">Liste ({{ $domains->count() }})</h2>
        <ul class="flex flex-col divide-y divide-line-soft">
            @foreach ($domains as $domain)
                <li wire:key="domaine-{{ $domain->id }}" class="flex flex-col gap-2 py-3">
                    @if ($editingId === $domain->id)
                        <form wire:submit="rename" class="flex flex-col gap-2">
                            <x-wire-field name="editName" label="Nouveau nom" autocomplete="off" maxlength="100" />
                            <div class="flex gap-2">
                                <x-button class="flex-1">Enregistrer</x-button>
                                <x-button type="button" variant="secondary" wire:click="cancelEdit" class="flex-1">Annuler</x-button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span @class(['text-[15px] font-bold', 'text-muted line-through' => ! $domain->active])>{{ $domain->name }}</span>
                            @unless ($domain->active)
                                <span class="text-[12px] font-bold text-muted">désactivé</span>
                            @endunless
                            <span class="text-[12px] text-muted">{{ $usage[$domain->id] ?? 0 }} {{ ($usage[$domain->id] ?? 0) > 1 ? 'personnes' : 'personne' }}</span>
                        </div>
                        <div class="flex gap-2">
                            <x-button type="button" variant="secondary" wire:click="startEdit('{{ $domain->id }}')" class="flex-1 !text-[14px]">Renommer</x-button>
                            <x-button type="button" variant="secondary" wire:click="toggle('{{ $domain->id }}')" class="flex-1 !text-[14px]">{{ $domain->active ? 'Désactiver' : 'Réactiver' }}</x-button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="text-[13px] text-muted">Un domaine désactivé n’est plus proposé ; les personnes qui l’ont déjà choisi le gardent jusqu’à ce qu’elles le retirent.</p>
    </section>
</div>
