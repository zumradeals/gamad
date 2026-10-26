<section aria-labelledby="pub-consent" class="space-panel flex flex-col gap-3 p-4 md:p-5">
    <h2 id="pub-consent" class="flex items-center gap-2.5 text-[18px] font-extrabold"><x-icon name="megaphone" :size="23" />Annonces personnalisées</h2>
    <label class="flex min-h-11 cursor-pointer items-start gap-3 text-[14px] leading-snug">
        <input type="checkbox" role="switch" wire:model.live="enabled" class="mt-0.5 size-5 shrink-0 accent-ink" aria-describedby="pub-consent-aide">
        <span><strong>Autoriser des annonces choisies d’après mon profil</strong><span id="pub-consent-aide" class="mt-1 block text-[13px] font-normal text-muted">GAMAD pourrait utiliser mes compétences, mes domaines d’intérêt et ma localité pour me montrer des annonces plus pertinentes, toujours signalées « Sponsorisé ». C’est désactivé tant que je ne l’autorise pas, et je peux changer d’avis à tout moment.</span></span>
    </label>
    <ul class="list-disc pl-5 text-[12.5px] leading-relaxed text-muted">
        <li>Aucun annonceur ne voit mon nom, mon profil ni mes données.</li>
        <li>Mes conversations privées et les données sensibles ne sont jamais utilisées.</li>
        <li>Sans cette autorisation, je peux voir des annonces qui ne dépendent pas de mon profil.</li>
    </ul>
    @if ($saved)<x-status>{{ $saved }}</x-status>@endif
</section>
