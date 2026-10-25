@php($viewerProfile = auth()->user()->ensureProfile())
<section id="composer" aria-label="Publier" class="feed-panel p-4"
    x-data="{
        open: $wire.entangle('expanded'),
        dirty() { return ($refs.body.value || '').trim() !== '' || ($refs.file.files && $refs.file.files.length > 0); },
        close() { if (! this.dirty()) { this.open = false; } },
        start() { this.open = true; this.$nextTick(() => this.$refs.body.focus()); },
    }"
    x-on:click.outside="close()"
    x-on:keydown.escape.window="close()"
    x-on:open-composer.window="start(); $el.scrollIntoView({ behavior: 'smooth', block: 'center' })">
    <form wire:submit="publish" class="space-y-3">
        <div class="flex items-start gap-3">
            <a href="{{ route('personnes.show', $viewerProfile->public_id) }}" class="shrink-0" aria-label="Mon profil"><x-avatar :profile="$viewerProfile" :size="48" /></a>
            <div class="min-w-0 flex-1">
                <label for="fil-compose-body" class="sr-only">Que souhaitez-vous partager ?</label>
                <textarea id="fil-compose-body" x-ref="body" wire:model="body" x-on:click="open && ! dirty() ? close() : start()" x-bind:rows="open ? 4 : 1" rows="1" maxlength="{{ \App\Models\Publication::BODY_MAX }}" placeholder="Que souhaitez-vous partager ?" class="min-h-12 w-full resize-none rounded-button border border-chip-line bg-card px-4 py-3 text-[15px]"></textarea>
                @error('body') <p role="alert" class="text-danger mt-1 text-[13px]">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" x-on:click="start(); $nextTick(() => $refs.file.click())" class="inline-flex min-h-11 items-center gap-2 rounded-button border border-line bg-ground px-4 text-[14px] font-semibold"><x-icon name="document" :size="18" class="text-link" />Photo</button>
            <button type="button" x-on:click="$wire.set('kind', 'personal_work'); start()" class="inline-flex min-h-11 items-center gap-2 rounded-button border border-line bg-ground px-4 text-[14px] font-semibold"><x-icon name="project" :size="18" class="text-link" />Réalisation</button>
            <a href="{{ route('agir.publier', 'savoir') }}" class="inline-flex min-h-11 items-center gap-2 rounded-button border border-line bg-ground px-4 text-[14px] font-semibold text-ink no-underline"><x-icon name="idea" :size="18" class="text-link" />Savoir</a>
            <a href="{{ route('agir.publier', 'besoin') }}" class="inline-flex min-h-11 items-center gap-2 rounded-button border border-line bg-ground px-4 text-[14px] font-semibold text-ink no-underline"><x-icon name="heart" :size="18" class="text-type-need-fg" />Besoin</a>
        </div>
        <div x-show="open" x-cloak class="space-y-3 border-t border-line-soft pt-3">
            <fieldset class="flex flex-wrap gap-3 text-[14px]">
                <legend class="mb-2 font-bold">Type de publication</legend>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" wire:model="kind" value="share"> Partager</label>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" wire:model="kind" value="personal_work"> Ma réalisation</label>
            </fieldset>
            <label for="fil-compose-media" class="block text-[13px] font-bold">Photo, vidéo ou document (facultatif)</label>
            <input id="fil-compose-media" x-ref="file" type="file" wire:model="media" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" class="min-h-11 w-full text-[13px]">
            <p wire:loading wire:target="media" role="status" class="text-[13px] text-muted">Chargement du fichier…</p>
            @if ($media) <p class="break-words text-[13px]">{{ $media->getClientOriginalName() }} <button type="button" wire:click="$set('media', null)" class="underline">Retirer</button></p> @endif
            @error('media') <p role="alert" class="text-danger text-[13px]">{{ $message }}</p> @enderror
            <label for="fil-compose-visibility" class="block text-[13px] font-bold">Visibilité</label>
            <select id="fil-compose-visibility" wire:model="visibility" class="min-h-11 rounded-button border border-chip-line bg-card px-3"><option value="public">Public</option><option value="members">Inscrits seulement</option></select>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <button type="button" x-on:click="if (dirty() && ! confirm('Abandonner cette publication ? Ce que vous avez saisi sera perdu.')) { return; } $wire.cancel()" class="min-h-11 rounded-button border border-line px-5 font-bold">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="publish,media" class="min-h-11 rounded-button bg-accent px-6 font-bold text-ink">Publier</button>
            </div>
        </div>
        @if ($status) <p role="status" class="text-[13px]">{{ $status }}</p> @endif
    </form>
</section>
