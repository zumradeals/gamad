<div class="flex flex-col gap-4">
    <h1 class="text-[24px] font-extrabold leading-tight">Espace</h1>

    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    <section aria-labelledby="profil" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="profil" class="text-[18px] font-extrabold">Mon profil</h2>
        <p class="text-muted">Ce que vous pouvez apporter, ce que vous voulez apprendre, où vous êtes : c’est ce qui permet aux autres de vous trouver.</p>
        <div class="flex flex-col gap-2 sm:flex-row">
            <x-button :href="route('espace.profil')" class="sm:flex-1">Modifier mon profil</x-button>
            <x-button :href="route('personnes.show', $user->ensureProfile()->public_id)" variant="secondary" class="sm:flex-1">Voir mon profil</x-button>
        </div>
    </section>

    @if ($publications->isNotEmpty())
        <section aria-labelledby="publications" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="publications" class="text-[18px] font-extrabold">Mes publications</h2>
            <ul class="flex flex-col divide-y divide-line-soft">
                @foreach ($publications as $publication)
                    <li>
                        <a href="{{ route('publications.show', $publication) }}" class="flex min-h-11 flex-col gap-1 py-2.5 text-ink no-underline hover:text-ink">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="rounded-chip px-2.5 py-0.5 text-[12px] font-bold {{ $publication->type->badgeClasses() }}">{{ $publication->type->label() }}</span>
                                @if ($publication->need_state && $publication->need_state !== \App\Enums\NeedState::Open)
                                    <span class="text-[12px] font-extrabold text-muted">{{ $publication->need_state->label() }}</span>
                                @endif
                            </span>
                            <span class="text-[14px] font-semibold">{{ Str::limit($publication->body, 90) }}</span>
                            @if ($publication->live_gestures_count > 0)
                                <span class="text-[12px] font-semibold text-muted">{{ $publication->type->factLabel($publication->live_gestures_count) }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="compte" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
        <h2 id="compte" class="text-[18px] font-extrabold">Mon compte</h2>
        <div class="flex items-center gap-3">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-ink text-[18px] font-extrabold text-white" aria-hidden="true">{{ $user->initial() }}</span>
            <dl class="min-w-0">
                <dt class="sr-only">Nom affiché</dt>
                <dd class="truncate text-[16px] font-bold">{{ $user->name }}</dd>
                <dt class="sr-only">Adresse e-mail</dt>
                <dd class="truncate text-[13px] text-muted">{{ $user->email }}</dd>
            </dl>
        </div>
        <p class="text-[13px] text-muted">Votre adresse e-mail n’est visible que par vous.</p>

        <form wire:submit="updateName" class="flex flex-col gap-3 border-t border-line-soft pt-3">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-[13px] font-semibold">Nom affiché</label>
                <input id="name" type="text" wire:model="name" autocomplete="name" required maxlength="255"
                       @class(['min-h-11 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('name') ? 'border-danger' : 'border-chip-line'])
                       @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                @error('name')
                    <p id="name-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>
            <x-button variant="secondary" wire:loading.attr="disabled" wire:target="updateName">
                <span wire:loading.remove wire:target="updateName">Enregistrer le nom</span>
                <span wire:loading wire:target="updateName">Enregistrement…</span>
            </x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="flex flex-col pt-1">
            @csrf
            <x-button variant="secondary">Se déconnecter</x-button>
        </form>
    </section>

    @can('super-admin')
        <section aria-labelledby="administration" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <h2 id="administration" class="text-[18px] font-extrabold">Administration</h2>
            <p class="text-muted">Courriel, nom et adresse de la plateforme, limites de sécurité, domaines et territoires.</p>
            <x-button :href="route('espace.parametres')">Paramètres</x-button>
        </section>
    @endcan

    <section aria-labelledby="suppression" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" x-data="{ confirming: {{ $errors->has('password') ? 'true' : 'false' }} }">
        <h2 id="suppression" class="text-[18px] font-extrabold">Supprimer mon compte</h2>
        <p class="text-muted">La suppression est définitive : votre compte et vos données personnelles sont effacés et ne peuvent pas être restaurés.</p>

        <div x-show="!confirming">
            <x-button type="button" variant="secondary" x-on:click="confirming = true">Supprimer mon compte</x-button>
        </div>

        <form wire:submit="deleteAccount" x-show="confirming" x-cloak class="flex flex-col gap-3">
            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-[13px] font-semibold">Confirmez avec votre mot de passe</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                       @class(['min-h-11 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has('password') ? 'border-danger' : 'border-chip-line'])
                       @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                @error('password')
                    <p id="password-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button wire:loading.attr="disabled" wire:target="deleteAccount" class="sm:flex-1">
                    <span wire:loading.remove wire:target="deleteAccount">Supprimer définitivement</span>
                    <span wire:loading wire:target="deleteAccount">Suppression…</span>
                </x-button>
                <x-button type="button" variant="secondary" class="sm:flex-1" x-on:click="confirming = false; $wire.set('password', '')">Annuler</x-button>
            </div>
        </form>
    </section>
</div>
