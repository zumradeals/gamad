<div class="flex flex-col gap-3">
    @if ($viewer?->isActive() && $viewer->id !== $profile->user_id)
        <div class="flex flex-wrap items-center gap-2">
            @if ($pair?->state === 'accepted')
                <x-button :href="route('conversations.show', $pair->conversation_id)" variant="accent"><x-icon name="chat" :size="18" class="mr-2" />Écrire</x-button>
                <span class="inline-flex min-h-11 items-center px-1 text-[14px] font-bold text-type-knowledge-fg">En relation</span>
            @elseif ($pair?->state === 'pending')
                @if ($pair->sender_id === $viewer->id)
                    <x-button type="button" variant="secondary" wire:loading.attr="disabled" wire:click="decide('withdraw', {{ $pair->revision }})">Retirer ma demande</x-button>
                @else
                    <x-button type="button" variant="accent" wire:loading.attr="disabled" wire:click="decide('accept', {{ $pair->revision }})">Accepter la relation</x-button>
                    <x-button type="button" variant="secondary" wire:loading.attr="disabled" wire:click="decide('decline', {{ $pair->revision }})">Décliner</x-button>
                @endif
            @else
                <x-button type="button" variant="accent" wire:click="$toggle('composing')" wire:loading.attr="disabled" :aria-expanded="$composing ? 'true' : 'false'"><x-icon name="chat" :size="18" class="mr-2" />Contacter</x-button>
            @endif
            <x-button type="button" variant="secondary" wire:click="follow({{ $following ? 'false' : 'true' }})" wire:loading.attr="disabled"><x-icon :name="$following ? 'check' : 'plus'" :size="17" class="mr-1.5" />{{ $following ? 'Ne plus suivre' : 'Suivre' }}</x-button>
            <div x-data="{ menu: false }" class="relative" x-on:click.outside="menu = false" x-on:keydown.escape.window="menu = false">
                <button type="button" x-on:click="menu = ! menu" x-bind:aria-expanded="menu" aria-haspopup="menu" aria-label="Autres actions pour ce profil" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-button border border-line text-[18px] font-extrabold">⋯</button>
                <div x-show="menu" x-cloak role="menu" class="absolute right-0 z-20 mt-1 w-64 rounded-card border border-line bg-card p-1 shadow-lg">
                    <x-profile-share :profile="$profile" menu />
                    <a role="menuitem" href="{{ route('personnes.signaler', $profile->public_id) }}" class="flex min-h-11 items-center gap-2 px-3 text-[14px] font-semibold text-ink no-underline"><x-icon name="shield" :size="18" />Signaler ce profil</a>
                    @if ($pair?->state === 'accepted')<button type="button" role="menuitem" wire:loading.attr="disabled" wire:click="decide('remove', {{ $pair->revision }})" wire:confirm="Retirer cette relation ? La conversation reste consultable." class="flex min-h-11 w-full items-center px-3 text-left text-[14px] font-semibold">Retirer la relation</button>@endif
                    <button type="button" role="menuitem" wire:click="block" wire:confirm="Bloquer cette personne ? Vos suivis et votre relation seront retirés et vous ne pourrez plus vous écrire." class="flex min-h-11 w-full items-center px-3 text-left text-[14px] font-semibold text-danger">Bloquer cette personne</button>
                    <p class="px-3 pb-2 text-[12px] text-muted">Suivre permet de retrouver ses publications publiques dans « Mes suivis ». Vos suivis et relations restent privés.</p>
                </div>
            </div>
        </div>
        @if ($pair?->state === 'pending')
            <p class="whitespace-pre-line break-words rounded-button bg-ground p-3 text-[14px]">{{ $pair->note }}</p>
            <p class="text-[12px] text-muted">{{ $pair->sender_id === $viewer->id ? 'Votre demande attend son accord.' : 'Cette personne souhaite faire votre connaissance.' }}</p>
        @endif
        @if ($composing && ! in_array($pair?->state, ['accepted', 'pending'], true))
            <form wire:submit="request" class="flex flex-col gap-2 rounded-card border border-line p-4">
                <label for="contact-{{ $profileId }}" class="text-[14px] font-bold">Qu’aimeriez-vous partager ou faire ensemble ?</label>
                <textarea id="contact-{{ $profileId }}" wire:model="note" maxlength="280" rows="3" required class="w-full rounded-button border border-chip-line bg-card p-3 text-[15px]"></textarea>
                <p class="text-[12px] text-muted">Un mot personnel, 280 caractères au plus. La conversation s’ouvrira après son accord.</p>
                <x-button wire:loading.attr="disabled">Envoyer ma demande</x-button>
            </form>
        @endif
        @error('note')<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@enderror
        <p wire:loading role="status" class="text-[12px] text-muted">Mise à jour…</p>
    @elseif (! $viewer)
        <div class="flex flex-wrap items-center gap-2">
            <x-button :href="route('login')" variant="accent">Se connecter pour suivre ou entrer en relation</x-button>
            <x-profile-share :profile="$profile" />
        </div>
    @endif
</div>
