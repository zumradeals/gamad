{{-- Carte « Sponsorisé » : $name, $verified, $title, $body, $imageUrl (ou null), $ctaLabel ; en diffusion aussi $ctaUrl, $why (texte « Pourquoi cette annonce ? ») et $menu (true : Masquer, Signaler). Étiquetée, sans aucun nombre de « j'aime ». --}}
<article class="overflow-hidden rounded-card border border-line bg-card" aria-label="Annonce sponsorisée">
    <header class="flex items-center gap-2.5 p-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-type-call-bg text-[13px] font-extrabold text-type-call-fg" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        <span class="min-w-0 flex-1">
            <strong class="block truncate text-[13.5px]">{{ $name }}@if ($verified) <span class="text-type-knowledge-fg" title="Entreprise vérifiée">✓</span>@endif</strong>
            <span class="inline-flex items-center rounded-chip bg-line-soft px-2 py-0.5 text-[11px] font-extrabold text-muted">Sponsorisé</span>
        </span>
        @if ($menu ?? false)
            <details class="relative" wire:ignore.self>
                <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-button text-muted hover:bg-soft-blue" aria-label="Options de l’annonce"><x-icon name="dots" :size="20" /></summary>
                <div class="absolute right-0 top-full z-10 mt-1 flex w-60 flex-col gap-1 rounded-card border border-line bg-card p-2 shadow-lg">
                    <button type="button" wire:click="hideAd('{{ $adId }}')" class="flex min-h-11 items-center rounded-button px-3 text-left text-[14px] font-semibold hover:bg-soft-blue">Masquer cette annonce</button>
                    <p class="px-3 pt-1 text-[12px] font-bold text-muted">Signaler cette annonce</p>
                    @foreach (\App\Services\Publicite\AdFeedback::REASONS as $key => $label)
                        <button type="button" wire:click="reportAd('{{ $adId }}', '{{ $key }}')" class="flex min-h-11 items-center rounded-button px-3 text-left text-[13px] hover:bg-soft-blue">{{ $label }}</button>
                    @endforeach
                </div>
            </details>
        @endif
    </header>
    @if ($imageUrl)
        <img src="{{ $imageUrl }}" alt="" class="h-40 w-full object-cover">
    @else
        <div class="flex h-32 items-center justify-center bg-gradient-to-br from-accent via-type-call-bg to-soft-blue text-ink/50" aria-hidden="true"><x-icon name="megaphone" :size="40" /></div>
    @endif
    <div class="p-3">
        <h3 class="text-[15px] font-extrabold">{{ $title !== '' ? $title : 'Titre de votre annonce' }}</h3>
        <p class="mt-0.5 text-[13px] leading-snug text-muted">{{ $body !== '' ? $body : 'Le texte de votre annonce apparaîtra ici.' }}</p>
    </div>
    <footer class="flex items-center justify-between gap-2 px-3 pb-3">
        @if ($why ?? null)
            <details class="min-w-0 flex-1" wire:ignore.self><summary class="inline-flex min-h-11 cursor-pointer list-none items-center text-[12px] font-bold text-link">Pourquoi cette annonce ?</summary><p class="mb-2 text-[12px] leading-snug text-muted">{{ $why }}</p></details>
        @else
            <span class="text-[12px] font-bold text-link">Pourquoi cette annonce ?</span>
        @endif
        @if ($ctaUrl ?? null)
            <a href="{{ $ctaUrl }}" @if (($external ?? false)) rel="noopener noreferrer" @endif class="inline-flex min-h-11 shrink-0 items-center rounded-button bg-ink px-4 text-[13px] font-bold text-white no-underline hover:text-white">{{ $ctaLabel }}</a>
        @else
            <span class="inline-flex min-h-10 items-center rounded-button bg-ink px-4 text-[13px] font-bold text-white">{{ $ctaLabel }}</span>
        @endif
    </footer>
</article>
