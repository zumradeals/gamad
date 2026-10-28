<section aria-labelledby="mon-acces" class="space-panel flex flex-col gap-4 p-4 md:p-5">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line-soft pb-3">
        <h2 id="mon-acces" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="lock" :size="23" />Mon accès</h2>
        <span class="text-[11px] text-muted">Mot de passe et appareils connectés</span>
    </div>

    @if ($status)<x-status>{{ $status }}</x-status>@endif

    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <form wire:submit="changePassword" class="flex min-w-0 flex-col gap-3" aria-labelledby="mdp-titre">
            <h3 id="mdp-titre" class="text-[14px] font-bold">Changer mon mot de passe</h3>
            @foreach ([['current_password', 'Mot de passe actuel', 'current-password'], ['password', 'Nouveau mot de passe (8 caractères au moins)', 'new-password'], ['password_confirmation', 'Confirmer le nouveau mot de passe', 'new-password']] as [$field, $label, $auto])
                <div class="flex flex-col gap-1.5">
                    <label for="acces-{{ $field }}" class="text-[13px] font-semibold">{{ $label }}</label>
                    <input id="acces-{{ $field }}" type="password" wire:model="{{ $field }}" autocomplete="{{ $auto }}" required
                           @class(['min-h-11 w-full rounded-button border bg-card px-3.5 text-[15px]', $errors->has($field) ? 'border-danger' : 'border-chip-line'])
                           @if ($errors->has($field)) aria-invalid="true" aria-describedby="acces-{{ $field }}-error" @endif>
                    @error($field)<p id="acces-{{ $field }}-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <p class="text-[12px] leading-relaxed text-muted">Vos autres appareils seront déconnectés ; celui-ci reste ouvert.</p>
            <x-button wire:loading.attr="disabled" wire:target="changePassword" class="sm:self-start">
                <span wire:loading.remove wire:target="changePassword">Changer le mot de passe</span>
                <span wire:loading wire:target="changePassword">Enregistrement…</span>
            </x-button>
        </form>

        <div class="flex min-w-0 flex-col gap-3 border-t border-line-soft pt-5 xl:border-l xl:border-t-0 xl:pl-6 xl:pt-0">
            <h3 class="text-[14px] font-bold">Appareils connectés</h3>
            @if (! $supported)
                <p class="rounded-button border border-line bg-card p-3 text-[13px] text-muted">La liste des appareils n’est pas disponible sur cette installation.</p>
            @elseif ($sessions->isEmpty())
                <p class="rounded-button border border-line bg-card p-3 text-[13px] text-muted">Aucun appareil connecté à afficher.</p>
            @else
                <ul class="flex flex-col gap-2" data-testid="sessions">
                    @foreach ($sessions as $session)
                        <li wire:key="session-{{ $session->handle }}" class="flex flex-wrap items-center justify-between gap-2 rounded-button border border-line bg-card p-3">
                            <div class="min-w-0">
                                <p class="text-[14px] font-bold">{{ $session->browser }} · {{ $session->platform }}@if ($session->current) <span class="ml-1 rounded-full bg-type-knowledge-bg px-2 py-0.5 text-[11px] font-bold text-type-knowledge-fg">Cet appareil</span>@endif</p>
                                <p class="text-[12px] text-muted">Dernière activité : {{ $session->lastActivity->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i') }}@if ($session->ip) · {{ $session->ip }}@endif</p>
                            </div>
                            @unless ($session->current)
                                <x-button type="button" variant="secondary" wire:click="revoke('{{ $session->handle }}')" wire:loading.attr="disabled" class="!min-h-11 !px-3.5 !text-[13px]">Déconnecter</x-button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
                @if ($sessions->where('current', false)->isNotEmpty())
                    <form wire:submit="revokeOthers" class="flex flex-col gap-2 border-t border-line-soft pt-3">
                        <label for="acces-confirm_password" class="text-[13px] font-semibold">Mot de passe, pour déconnecter tous les autres appareils</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input id="acces-confirm_password" type="password" wire:model="confirm_password" autocomplete="current-password" required
                                   @class(['min-h-11 min-w-0 w-full flex-1 rounded-button border bg-card px-3.5 text-[15px]', $errors->has('confirm_password') ? 'border-danger' : 'border-chip-line'])
                                   @if ($errors->has('confirm_password')) aria-invalid="true" aria-describedby="acces-confirm-error" @endif>
                            <x-button variant="secondary" wire:loading.attr="disabled" wire:target="revokeOthers" class="shrink-0">Déconnecter les autres</x-button>
                        </div>
                        @error('confirm_password')<p id="acces-confirm-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
                    </form>
                @endif
            @endif
        </div>
    </div>
</section>
