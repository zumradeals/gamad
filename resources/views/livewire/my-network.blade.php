<section id="mes-liens" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-6" wire:poll.30s>
    <div><h2 class="text-[20px] font-extrabold">Mes liens</h2><p class="mt-1 text-[13px] text-muted">Vos relations et suivis sont privés. {{ $pending ? $pending.' demande(s) à découvrir.' : 'Faites connaissance à votre rythme.' }}</p></div>
    <nav aria-label="Mes liens" class="flex flex-wrap gap-2">
        @foreach (['requests' => 'Demandes', 'relations' => 'Relations', 'follows' => 'Mes suivis', 'blocks' => 'Blocages'] as $key => $label)
            <button type="button" wire:click="select('{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}" class="min-h-11 rounded-chip border border-line px-3 text-[13px] font-bold {{ $tab === $key ? 'bg-ink text-white' : 'bg-card text-ink' }}">{{ $label }}</button>
        @endforeach
    </nav>
    @forelse ($rows as $row)
        @php
            $otherId = in_array($tab, ['requests', 'relations'], true) ? $row->otherId($user) : ($tab === 'blocks' ? $row->blocked_id : $row->followed_id);
            $person = $people->get($otherId);
        @endphp
        @if ($person)
        <article wire:key="link-{{ $tab }}-{{ $otherId }}" class="flex min-w-0 flex-col gap-2 border-t border-line py-3">
            <div class="flex items-center gap-3">
                @if ($tab !== 'blocks' && Gate::allows('view', $person->profile))
                    <x-avatar :profile="$person->profile" :size="40" />
                    <a href="{{ route('personnes.show', $person->profile) }}" class="inline-flex min-h-11 min-w-0 items-center break-words text-[15px] font-bold">{{ $person->name }}</a>
                @else <span class="break-words text-[15px] font-bold">{{ $person->name }}</span>@endif
            </div>
            @if ($tab === 'requests')
                <p class="whitespace-pre-line break-words text-[14px]">{{ $row->note }}</p>
                <p class="text-[12px] text-muted">{{ $row->sender_id === $user->id ? 'Demande envoyée, en attente de son accord.' : 'Souhaite entrer en relation avec vous.' }}</p>
            @endif
            <div class="flex flex-wrap gap-2">
                @if ($tab === 'requests')
                    @if ($row->sender_id !== $user->id)
                        <x-button type="button" wire:click="act('{{ $otherId }}', 'accept', {{ $row->revision }})">Accepter</x-button>
                        <x-button type="button" variant="secondary" wire:click="act('{{ $otherId }}', 'decline', {{ $row->revision }})">Décliner</x-button>
                        <x-button type="button" variant="secondary" wire:click="act('{{ $otherId }}', 'block')" wire:confirm="Bloquer cette personne et empêcher tout nouveau contact ?">Bloquer</x-button>
                    @else <x-button type="button" variant="secondary" wire:click="act('{{ $otherId }}', 'withdraw', {{ $row->revision }})">Retirer ma demande</x-button>@endif
                @elseif ($tab === 'relations')
                    <x-button :href="route('conversations.show', $row->conversation_id)">Écrire</x-button>
                    <x-button type="button" variant="secondary" wire:click="act('{{ $otherId }}', 'remove', {{ $row->revision }})" wire:confirm="Retirer cette relation ?">Retirer la relation</x-button>
                @elseif ($tab === 'follows') <x-button type="button" variant="secondary" wire:click="act('{{ $otherId }}', 'unfollow')">Ne plus suivre</x-button>
                @else <x-button type="button" variant="secondary" wire:click="act('{{ $otherId }}', 'unblock')">Débloquer</x-button>@endif
            </div>
        </article>
        @endif
    @empty <p class="py-3 text-[14px] text-muted">{{ match ($tab) { 'requests' => 'Aucune demande en attente.', 'relations' => 'Vos relations acceptées apparaîtront ici.', 'follows' => 'Suivez une personne depuis son profil pour retrouver ses publications publiques dans le Fil.', default => 'Vous n’avez bloqué personne.' } }}</p>@endforelse
    @if ($hasMore)<x-button type="button" variant="secondary" wire:click="more">Voir plus</x-button>@endif
</section>
