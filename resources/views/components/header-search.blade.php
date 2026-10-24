{{-- Recherche native : les mêmes destinations et autorisations que celles du Fil. --}}
<form method="GET" action="{{ route('fil') }}" role="search" aria-label="Rechercher dans GAMAD"
      class="header-search order-last basis-full lg:order-none lg:basis-auto"
      x-data x-on:submit="$refs.feedFilter.value = new URLSearchParams(window.location.search).get('filtre') || 'tout'">
    <input x-ref="feedFilter" type="hidden" name="filtre" value="{{ is_string(request()->query('filtre')) ? request()->query('filtre') : 'tout' }}">
    <label for="header-query" class="sr-only">Rechercher un savoir, un besoin ou une personne</label>
    <input id="header-query" type="search" name="q" maxlength="100" autocomplete="off"
           value="{{ is_string(request()->query('q')) ? \Illuminate\Support\Str::limit(request()->query('q'), 100, '') : '' }}"
           placeholder="Un savoir, un besoin, une personne…"
           x-on:feed-search-cleared.window="$el.value = ''"
           x-on:popstate.window="$el.value = new URLSearchParams(window.location.search).get('q') || ''"
           class="min-h-11 min-w-0 flex-1 border-0 bg-transparent px-2 text-[13px] text-ink outline-none placeholder:text-muted">
    <button type="submit" title="Rechercher dans les publications" class="flex size-11 shrink-0 items-center justify-center rounded-button bg-accent text-ink">
        <x-icon name="search" :size="20" /><span class="sr-only">Dans les publications</span>
    </button>
    <button type="submit" formaction="{{ route('personnes.index') }}" title="Trouver une personne" class="flex size-11 shrink-0 items-center justify-center rounded-button text-ink hover:bg-soft-blue">
        <x-icon name="group" :size="21" /><span class="sr-only">Trouver une personne</span>
    </button>
</form>
