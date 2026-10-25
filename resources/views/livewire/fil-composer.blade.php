<section aria-label="Publier" class="feed-panel p-4" x-data="{ open: $wire.entangle('expanded') }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <form wire:submit="publish" class="space-y-3">
        <label for="fil-compose-body" class="block text-[14px] font-bold">Que souhaitez-vous partager ?</label>
        <textarea id="fil-compose-body" wire:model="body" x-on:click="open = !open" rows="2" maxlength="{{ \App\Models\Publication::BODY_MAX }}" placeholder="Écrivez votre publication ici…" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3 py-3 text-[15px]"></textarea>
        @error('body') <p role="alert" class="text-danger text-[13px]">{{ $message }}</p> @enderror
        <div x-show="open" x-cloak class="space-y-3">
            <fieldset class="flex flex-wrap gap-3 text-[14px]">
                <legend class="mb-2 font-bold">Type de publication</legend>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" wire:model="kind" value="share"> Partager</label>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" wire:model="kind" value="personal_work"> Ma réalisation</label>
            </fieldset>
            <label for="fil-compose-media" class="block text-[13px] font-bold">Photo, vidéo ou document (facultatif)</label>
            <input id="fil-compose-media" type="file" wire:model="media" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" class="min-h-11 w-full text-[13px]">
            <p wire:loading wire:target="media" role="status" class="text-[13px] text-muted">Chargement du fichier…</p>
            @if ($media) <p class="break-words text-[13px]">{{ $media->getClientOriginalName() }} <button type="button" wire:click="$set('media', null)" class="underline">Retirer</button></p> @endif
            @error('media') <p role="alert" class="text-danger text-[13px]">{{ $message }}</p> @enderror
            <label for="fil-compose-visibility" class="block text-[13px] font-bold">Visibilité</label>
            <select id="fil-compose-visibility" wire:model="visibility" class="min-h-11 rounded-button border border-chip-line bg-card px-3"><option value="public">Public</option><option value="members">Inscrits seulement</option></select>
            <button type="submit" wire:loading.attr="disabled" wire:target="publish,media" class="min-h-11 rounded-button bg-ink px-5 font-bold text-white">Publier</button>
        </div>
        @if ($status) <p role="status" class="text-[13px]">{{ $status }}</p> @endif
    </form>
</section>
