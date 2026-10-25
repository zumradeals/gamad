@props(['profile', 'label' => 'Partager le profil', 'menu' => false])

{{-- Partage du seul lien public du profil : il ne donne aucun droit supplémentaire (le profil garde sa règle de visibilité). --}}
<span x-data="{ copied: false, url: @js(route('personnes.show', $profile->public_id)) }" class="inline-flex items-center">
    <button type="button"
        x-on:click="if (navigator.share) { navigator.share({ url }).catch(() => {}); } else if (navigator.clipboard) { navigator.clipboard.writeText(url).then(() => copied = true).catch(() => window.prompt('Copiez ce lien', url)); } else { window.prompt('Copiez ce lien', url); }"
        {{ $attributes->class($menu ? ['flex min-h-11 w-full items-center gap-2 px-3 text-left text-[14px] font-semibold'] : ['inline-flex min-h-11 items-center justify-center gap-2 rounded-button border border-ink bg-card px-5 text-[15px] font-bold']) }}><x-icon name="external" :size="18" />{{ $label }}</button>
    <span x-show="copied" x-cloak role="status" class="ml-2 text-[12px] font-semibold text-type-knowledge-fg">Lien copié.</span>
</span>
