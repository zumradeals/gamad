{{-- Carte « Sponsorisé » : $name, $verified, $title, $body, $imageUrl (ou null), $ctaLabel. Étiquetée, sans aucun nombre de « j'aime ». --}}
<article class="overflow-hidden rounded-card border border-line bg-card" aria-label="Annonce sponsorisée">
    <header class="flex items-center gap-2.5 p-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-type-call-bg text-[13px] font-extrabold text-type-call-fg" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        <span class="min-w-0 flex-1">
            <strong class="block truncate text-[13.5px]">{{ $name }}@if ($verified) <span class="text-type-knowledge-fg" title="Entreprise vérifiée">✓</span>@endif</strong>
            <span class="inline-flex items-center rounded-chip bg-line-soft px-2 py-0.5 text-[11px] font-extrabold text-muted">Sponsorisé</span>
        </span>
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
        <span class="text-[12px] font-bold text-link">Pourquoi cette annonce ?</span>
        <span class="inline-flex min-h-10 items-center rounded-button bg-ink px-4 text-[13px] font-bold text-white">{{ $ctaLabel }}</span>
    </footer>
</article>
