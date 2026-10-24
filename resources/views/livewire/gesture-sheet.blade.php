<div>
    @if ($publication)
        @php
            $type = $publication->type;
            // Pour une publication de ZUMRA, c'est la ZUMRA qui reçoit la réponse (ses responsables décident).
            $firstName = $publication->zumra?->name ?? Str::before(trim($publication->author->name), ' ');
        @endphp
        <div class="fixed inset-0 z-30 flex items-end justify-center md:items-center" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-ink/55" wire:click="close" aria-hidden="true"></div>

            <div role="dialog" aria-modal="true" aria-labelledby="geste-titre" x-trap.noscroll="true"
                 class="relative flex max-h-full w-full max-w-[520px] flex-col gap-3.5 overflow-y-auto rounded-t-[20px] bg-card px-4 pb-6 pt-5 md:rounded-card">
                <span class="mx-auto h-1 w-10 rounded-full bg-chip-line md:hidden" aria-hidden="true"></span>

                <div class="flex items-center justify-between">
                    <h2 id="geste-titre" class="text-[18px] font-extrabold">{{ $publication->gestureLabel() }}</h2>
                    <button type="button" wire:click="close" aria-label="Fermer" class="flex size-11 items-center justify-center rounded-full text-ink">
                        <x-icon name="close" />
                    </button>
                </div>

                <p class="text-[14px] leading-[1.55]">{{ $firstName }} recevra votre {{ $type->isExchange() ? 'demande d’échange' : ($type->isNeed() ? 'proposition' : 'réponse') }} et pourra <strong>l’accepter</strong>.</p>

                <x-opportunity-conditions :publication="$publication" />

                <form wire:submit="send" class="flex flex-col gap-3.5">
                    <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.message].length }">
                        <label for="geste-mot" class="text-[13px] font-bold text-muted">{{ $type->isExchange() ? 'De quoi aimeriez-vous échanger ?' : 'Un mot pour '.$firstName.' (facultatif)' }}</label>
                        <textarea id="geste-mot" wire:model="message" rows="3" maxlength="{{ \App\Models\Gesture::MESSAGE_MAX }}"
                                  x-on:input="count = [...$event.target.value].length"
                                  @class(['min-h-24 w-full resize-none rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal', $errors->has('message') ? 'border-danger' : 'border-chip-line'])
                                  @if ($errors->has('message')) aria-invalid="true" aria-describedby="geste-erreur" @endif></textarea>
                        <span class="self-end text-[12px] text-muted"><span x-text="count">{{ mb_strlen($message) }}</span> / {{ \App\Models\Gesture::MESSAGE_MAX }}</span>
                        @error('message')
                            <p id="geste-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    @unless ($type->isExchange())
                    <details class="rounded-button border border-line p-3" @if ($errors->has('availability') || $errors->has('helpFormat')) open @endif>
                        <summary class="min-h-11 cursor-pointer content-center text-[14px] font-bold">Préciser ma proposition (facultatif)</summary>
                        <div class="mt-2 flex flex-col gap-3">
                            <x-wire-field name="availability" label="Mes disponibilités" maxlength="100" placeholder="Ex. Samedi matin, pendant une heure" />
                            <div class="flex flex-col gap-1.5"><label for="help-format" class="text-[13px] font-semibold">Comment puis-je aider ?</label><select id="help-format" wire:model="helpFormat" class="zumra-filter"><option value="">À convenir ensemble</option><option value="remote">À distance</option><option value="onsite">Sur place</option></select>@error('helpFormat')<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror</div>
                            <p class="text-[12px] text-muted">Ces précisions accompagnent votre proposition, dans la limite totale de {{ \App\Models\Gesture::MESSAGE_MAX }} caractères. Vous conviendrez de la suite après acceptation.</p>
                        </div>
                    </details>
                    @endunless
                    @if ($type->isExchange())<p class="text-[12px] text-muted">Votre demande porte sur cette publication. L’échange privé s’ouvrira après son accord, sans créer automatiquement une relation.</p>@endif

                    <p class="text-[12px] leading-normal text-muted">Votre profil (nom, ville, capacités) sera visible par {{ $firstName }}. Jamais votre e-mail ni votre téléphone.</p>

                    <x-button wire:loading.attr="disabled" wire:target="send" class="min-h-[52px]">
                        <span wire:loading.remove wire:target="send">{{ $type->isExchange() ? 'Envoyer ma demande d’échange' : ($type->isNeed() ? 'Envoyer ma proposition' : 'Envoyer ma réponse') }}</span>
                        <span wire:loading wire:target="send">Envoi…</span>
                    </x-button>
                    <x-button type="button" variant="secondary" wire:click="close" class="!border-transparent !text-muted">Annuler</x-button>
                    <p class="text-[12px] leading-normal text-muted">Vous pourrez retirer votre {{ $type->isExchange() ? 'demande d’échange' : ($type->isNeed() ? 'proposition' : 'réponse') }} tant qu’elle n’est pas acceptée.</p>
                </form>
            </div>
        </div>
    @endif
</div>
