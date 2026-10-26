<section id="suggestions" class="min-w-0 rounded-card border border-line bg-card p-4 md:p-5" aria-labelledby="suggestions-title">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div><h2 id="suggestions-title" class="text-[20px] font-extrabold">Des pistes pour avancer</h2><p class="mt-1 text-[13px] leading-relaxed text-muted">Des capacités et des intentions qui se complètent, dans les lieux que vous avez choisis.</p></div>
        <a href="{{ route('espace.profil') }}#matching-preferences" class="inline-flex min-h-11 items-center text-[13px] font-bold">Mes préférences</a>
    </div>
    @if ($status)<p class="mt-3 text-[13px] font-semibold" role="status">{{ $status }}</p>@endif
    @if (! $preferences['enabled'])
        <p class="mt-3 text-[14px] text-muted">Choisissez vos intentions, vos conditions et vos localités pour recevoir des suggestions. Les notifications sont facultatives.</p>
        <x-button :href="route('espace.profil').'#matching-preferences'" variant="secondary" class="mt-3">Choisir mes préférences</x-button>
    @elseif (! $preferences['available'])
        <p class="mt-3 text-[14px] text-muted">Vous avez indiqué être indisponible. Vos suggestions sont en pause jusqu’à votre changement de préférence.</p>
    @else
        <div class="mt-3 flex flex-col gap-3">
        @forelse ($items as $item)
            <article wire:key="match-{{ $item['suggestion']->id }}" class="min-w-0 rounded-card border border-line p-4">
                <div class="flex items-center gap-3">@if ($item['person'])<x-avatar :profile="$item['person']" :size="40" />@endif<h3 class="min-w-0 text-[16px] font-extrabold [overflow-wrap:anywhere]">{{ $item['title'] }}</h3></div>
                @if ($item['company'] ?? null)<p class="mt-1 text-[12px] text-muted [overflow-wrap:anywhere]">Opportunité de {{ $item['company']->name }}</p>@endif
                @if ($item['body'])<p class="mt-2 whitespace-pre-line text-[14px] leading-relaxed [overflow-wrap:anywhere]">{{ Str::limit($item['body'], 280) }}</p>@endif
                @if ($item['context'])<p class="mt-2 text-[12px] text-muted [overflow-wrap:anywhere]">Pour votre publication : {{ Str::limit($item['context']->body, 120) }}</p>@endif
                <details class="mt-2"><summary class="min-h-11 cursor-pointer content-center text-[13px] font-bold">Pourquoi cette suggestion ?</summary><ul class="list-disc space-y-1 pl-5 text-[13px] text-muted">@foreach ($item['reasons'] as $reason)<li class="[overflow-wrap:anywhere]">{{ $reason }}</li>@endforeach</ul></details>
                <div class="mt-2 flex flex-wrap gap-2"><x-button :href="$item['url']" variant="accent">Découvrir</x-button><x-button type="button" variant="secondary" wire:click="ignore('{{ $item['suggestion']->id }}')" wire:loading.attr="disabled">Ne m’intéresse pas</x-button></div>
                <p class="mt-2 text-[12px] text-muted">Une piste à explorer ensemble. Aucun engagement ni paiement n’est créé.</p>
            </article>
        @empty <p class="py-3 text-[14px] text-muted">Aucune correspondance actuelle avec vos préférences. Vous pouvez les ajuster ou publier un besoin avec ses conditions et une capacité.</p>@endforelse
        </div>
        @if ($hasMore)<x-button type="button" variant="secondary" wire:click="more" wire:loading.attr="disabled" class="mt-3">Voir plus de pistes</x-button>@endif
    @endif
</section>
