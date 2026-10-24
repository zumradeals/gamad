<div class="flex flex-col gap-3">
    @if ($viewer?->isActive() && $viewer->id !== $profile->user_id)
        <div class="flex flex-wrap gap-2">
            <x-button type="button" variant="secondary" wire:click="follow({{ $following ? 'false' : 'true' }})" wire:loading.attr="disabled">{{ $following ? 'Ne plus suivre' : 'Suivre' }}</x-button>
            @if ($pair?->state === 'accepted')
                <span class="inline-flex min-h-11 items-center px-3 text-[14px] font-bold text-type-knowledge-fg">En relation</span>
                <x-button :href="route('conversations.show', $pair->conversation_id)">Écrire</x-button>
            @elseif ($pair?->state === 'pending')
                @if ($pair->sender_id === $viewer->id)
                    <x-button type="button" variant="secondary" wire:click="decide('withdraw', {{ $pair->revision }})">Retirer ma demande</x-button>
                @else
                    <x-button type="button" wire:click="decide('accept', {{ $pair->revision }})">Accepter la relation</x-button>
                    <x-button type="button" variant="secondary" wire:click="decide('decline', {{ $pair->revision }})">Décliner</x-button>
                @endif
            @else
                <x-button type="button" variant="accent" wire:click="$toggle('composing')">Entrer en relation</x-button>
            @endif
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
        <p class="text-[12px] text-muted">Suivre permet de retrouver ses publications publiques dans « Mes suivis ». Vos suivis et relations restent privés.</p>
        <details>
            <summary class="min-h-11 cursor-pointer content-center text-[12px] font-semibold text-muted">Gérer ce lien</summary>
            @if ($pair?->state === 'accepted')<x-button type="button" variant="secondary" wire:click="decide('remove', {{ $pair->revision }})" wire:confirm="Retirer cette relation ? La conversation reste consultable.">Retirer la relation</x-button>@endif
            <x-button type="button" variant="secondary" wire:click="block" wire:confirm="Bloquer cette personne ? Vos suivis et votre relation seront retirés et vous ne pourrez plus vous écrire.">Bloquer cette personne</x-button>
        </details>
    @elseif (! $viewer)
        <x-button :href="route('login')" variant="secondary">Se connecter pour suivre ou entrer en relation</x-button>
    @endif
</div>
