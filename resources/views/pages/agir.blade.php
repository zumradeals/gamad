<x-layouts.app title="Agir" narrow>
    <div class="flex flex-col gap-3.5">
        <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
            <a href="{{ route('fil') }}" aria-label="Retour à l’accueil" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
            <h1 class="flex-1 text-[17px] font-extrabold">Que voulez-vous faire ?</h1>
        </div>

        {{-- Ancien lien /agir : le choix est le même que celui du bouton Agir. --}}
        <div class="rounded-card border border-line bg-card p-2">
            <x-agir-actions sheet />
        </div>

        <p class="text-[12px] leading-normal text-muted">Vous publiez en tant que vous-même ({{ auth()->user()->name }}).@if (! \App\Support\Experience::initial() && auth()->user()->managedZumraIds() !== []) Pour publier au nom d’une ZUMRA que vous gérez, ouvrez-la dans l’onglet ZUMRA.@endif</p>
    </div>
</x-layouts.app>
