<x-layouts.app title="Agir" narrow>
    <div class="flex flex-col gap-3.5">
        <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
            <a href="{{ route('fil') }}" aria-label="Retour au Fil" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
            <h1 class="flex-1 text-[17px] font-extrabold">Que voulez-vous faire avancer ?</h1>
        </div>

        <p class="text-[14px] leading-normal text-muted">Choisissez ce que vous voulez faire. Votre publication apparaîtra dans le Fil, avec le bouton d’action qui convient.</p>

        @foreach (\App\Enums\PublicationType::forPerson() as $type)
            <a href="{{ route('agir.publier', $type->slug()) }}" class="flex min-h-[72px] items-center gap-3.5 rounded-card border border-line bg-card px-4 py-3.5 text-ink no-underline hover:text-ink">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-button {{ $type->badgeClasses() }}">
                    <x-icon :name="$type->isNeed() ? 'chat' : 'heart'" />
                </span>
                <span class="flex flex-1 flex-col gap-0.5">
                    <span class="text-[16px] font-extrabold">{{ $type->label() }}</span>
                    <span class="text-[13px] leading-[1.4] text-muted">{{ $type->pitch() }}</span>
                </span>
                <x-icon name="next" :size="20" />
            </a>
        @endforeach

        <p class="text-[12px] leading-normal text-muted">Vous publiez en tant que vous-même ({{ auth()->user()->name }}).@if (! \App\Support\Experience::initial() && auth()->user()->managedZumraIds() !== []) Pour publier au nom d’une ZUMRA que vous gérez, ouvrez-la dans l’onglet ZUMRA.@endif</p>
    </div>
</x-layouts.app>
