<x-layouts.guest>
    <div class="-mt-2 flex flex-col gap-6 py-2">
        <h1 class="text-[30px] font-extrabold leading-[1.15] tracking-[-0.5px]">Des personnes. Des actions. Un impact réel.</h1>
        <p class="text-[17px] leading-relaxed">Apportez ce que vous savez faire, apprenez ce qui vous manque, agissez avec d’autres.</p>

        <div class="flex flex-col gap-3">
            <x-button :href="route('register')">Créer un compte</x-button>
            <x-button :href="route('login')" variant="secondary">Se connecter</x-button>
        </div>

        <p class="text-center text-[13px] font-semibold text-muted">Formation — Travail — Adoration</p>
    </div>
</x-layouts.guest>
