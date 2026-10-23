<x-layouts.app title="Découvrir" narrow>
    <div class="flex flex-col gap-3.5">
        <div class="flex flex-col gap-1">
            <h1 class="text-[26px] font-extrabold leading-tight">Que cherchez-vous ?</h1>
            <p class="text-[14px] leading-normal text-muted">Trouvez une personne, un besoin concret ou un projet à découvrir.</p>
        </div>

        @include('partials.discover-cards')
    </div>
</x-layouts.app>
