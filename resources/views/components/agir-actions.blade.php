@props(['sheet' => false])

{{-- Les cinq actions du bouton Agir : chacune ouvre directement son formulaire (D-184, D-190). --}}
@php
    $actions = [
        ['besoin', 'J’ai besoin d’aide', 'Obtenir de l’aide pour un projet ou une difficulté', 'heart', 'bg-type-need-bg text-type-need-fg'],
        ['apprendre', 'Je veux apprendre', 'Acquérir un nouveau savoir-faire', 'learn', 'bg-type-learning-bg text-type-learning-fg'],
        ['aide', 'Je peux aider', 'Mettre mes compétences à disposition', 'group', 'bg-type-offer-bg text-type-offer-fg'],
        ['savoir', 'Je partage un savoir', 'Écrire une publication, expliquer quelque chose aux autres', 'book', 'bg-type-knowledge-bg text-type-knowledge-fg'],
        ['ma-realisation', 'Je montre une réalisation', 'Présenter un projet ou un résultat', 'check-circle', 'bg-type-project-bg text-type-project-fg'],
    ];
@endphp
<ul {{ $attributes->class(['m-0 flex list-none flex-col p-0', 'gap-2' => $sheet, 'gap-0.5' => ! $sheet]) }}>
    @foreach ($actions as [$slug, $title, $description, $icon, $tone])
        <li>
            <a href="{{ route('agir.publier', $slug) }}" data-agir-action="{{ $slug }}"
               @class(['flex min-h-14 items-center gap-3 rounded-button px-3 py-2.5 text-ink no-underline hover:bg-ground hover:text-ink', 'border border-line' => $sheet])>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $tone }}"><x-icon :name="$icon" :size="20" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[15px] font-extrabold leading-tight">{{ $title }}</span>
                    <span class="mt-0.5 block text-[13px] leading-snug text-muted">{{ $description }}</span>
                </span>
                <x-icon name="next" :size="18" class="shrink-0 text-muted" />
            </a>
        </li>
    @endforeach
</ul>
