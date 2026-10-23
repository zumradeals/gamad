@php
    $date = fn ($d) => $d->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y');
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center text-[14px] font-semibold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold leading-tight">Charte et mentions légales</h1>
        <p class="text-muted">Réservé au super administrateur. Ces textes sont des projets tant que vous ne les avez pas adoptés : seul vous les lisez, et l’inscription ne demande rien. Chaque changement est inscrit au journal d’administration.</p>
    </div>

    @if ($saved)
        <x-status>{{ $saved }}</x-status>
    @endif

    <section aria-labelledby="charte" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" x-data="{ confirming: false }">
        <h2 id="charte" class="text-[18px] font-extrabold">Charte de conduite</h2>
        @if ($charterAt)
            <p class="text-[14px] font-bold text-type-knowledge-fg">Adoptée le {{ $date($charterAt) }}. Elle est publique et l’inscription demande de l’accepter.</p>
        @else
            <p class="text-[14px] font-bold text-type-call-fg">Projet : non adopté.</p>
        @endif
        <x-button :href="route('charte')" variant="secondary">Lire la charte</x-button>
        @if ($charterAt)
            <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true">Retirer l’adoption</x-button>
            <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                <span class="text-[13px] font-semibold">Retirer l’adoption de la charte ?</span>
                <x-button type="button" wire:click="withdrawCharter" wire:loading.attr="disabled" wire:target="withdrawCharter" class="!px-4 !text-[14px]">Oui</x-button>
                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
            </div>
        @else
            <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true">Adopter la charte</x-button>
            <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                <span class="text-[13px] font-semibold">Adopter cette charte ? Elle deviendra publique.</span>
                <x-button type="button" wire:click="adoptCharter" wire:loading.attr="disabled" wire:target="adoptCharter" class="!px-4 !text-[14px]">Oui, adopter</x-button>
                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
            </div>
        @endif
    </section>

    <form wire:submit="saveLegal" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" aria-labelledby="mentions">
        <h2 id="mentions" class="text-[18px] font-extrabold">Mentions légales</h2>
        <p class="text-[13px] text-muted">Renseignez ces quatre champs : on ne peut adopter les mentions légales qu’une fois complètes.</p>

        <x-wire-field name="publisher" label="Éditeur du site" />
        <x-wire-field name="director" label="Responsable de la publication" />
        <x-wire-field name="contact" label="Adresse e-mail de contact" type="email" />
        <x-wire-field name="host" label="Hébergeur (nom et adresse)" />

        <x-button wire:loading.attr="disabled" wire:target="saveLegal">
            <span wire:loading.remove wire:target="saveLegal">Enregistrer les champs</span>
            <span wire:loading wire:target="saveLegal">Enregistrement…</span>
        </x-button>
    </form>

    <section aria-labelledby="adoption-mentions" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" x-data="{ confirming: false }">
        <h2 id="adoption-mentions" class="text-[18px] font-extrabold">Adoption des mentions légales</h2>
        @if ($legalAt && $legalComplete)
            <p class="text-[14px] font-bold text-type-knowledge-fg">Adoptées le {{ $date($legalAt) }}. Elles sont publiques.</p>
        @elseif ($legalAt)
            <p class="text-[14px] font-bold text-danger">Adoptées, mais un champ est vide : elles ne sont pas publiques. Complétez les champs.</p>
        @else
            <p class="text-[14px] font-bold text-type-call-fg">Projet : non adoptées.</p>
        @endif
        @error('legal')
            <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
        @enderror
        <x-button :href="route('mentions')" variant="secondary">Lire les mentions légales</x-button>
        @if ($legalAt)
            <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true">Retirer l’adoption</x-button>
            <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                <span class="text-[13px] font-semibold">Retirer l’adoption des mentions légales ?</span>
                <x-button type="button" wire:click="withdrawLegal" wire:loading.attr="disabled" wire:target="withdrawLegal" class="!px-4 !text-[14px]">Oui</x-button>
                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
            </div>
        @else
            <x-button type="button" variant="secondary" x-show="! confirming" x-on:click="confirming = true" :disabled="! $legalComplete">Adopter les mentions légales</x-button>
            <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                <span class="text-[13px] font-semibold">Adopter ces mentions légales ? Elles deviendront publiques.</span>
                <x-button type="button" wire:click="adoptLegal" wire:loading.attr="disabled" wire:target="adoptLegal" class="!px-4 !text-[14px]">Oui, adopter</x-button>
                <x-button type="button" variant="secondary" x-on:click="confirming = false" class="!px-4 !text-[14px]">Non</x-button>
            </div>
        @endif
    </section>
</div>
