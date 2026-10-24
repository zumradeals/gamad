@props(['publication'])
@if ($publication->media_path)
    <div class="my-3 min-w-0 overflow-hidden rounded-card border border-line bg-ground">
        @if ($publication->media_kind === 'image')
            <img src="{{ route('publications.media', $publication) }}" alt="Image jointe à la publication" loading="lazy" class="max-h-[560px] w-full object-contain">
        @elseif ($publication->media_kind === 'video')
            <video controls preload="none" playsinline class="max-h-[560px] w-full" aria-label="Vidéo jointe à la publication"><source src="{{ route('publications.media', $publication) }}" type="{{ $publication->media_mime }}">Votre navigateur ne lit pas cette vidéo.</video>
            <a href="{{ route('publications.media', $publication) }}" class="inline-flex min-h-11 items-center px-4 text-[13px] font-bold">Ouvrir la vidéo</a>
        @else
            <a href="{{ route('publications.media', $publication) }}" class="flex min-h-14 items-center gap-3 p-4 text-[14px] font-bold"><x-icon name="book" :size="20" />Télécharger le document PDF</a>
        @endif
    </div>
@endif
