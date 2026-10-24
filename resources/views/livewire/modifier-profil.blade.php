<div class="flex flex-col gap-4">
    <div class="-mx-4 -mt-4 flex items-center gap-1 border-b border-line bg-card px-2 py-2 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('personnes.show', $profile->public_id) }}" aria-label="Retour à mon profil" class="flex size-11 items-center justify-center text-ink hover:text-ink">
            <x-icon name="back" />
        </a>
        <h1 class="text-[18px] font-extrabold">Modifier mon profil</h1>
    </div>

    @if ($saved)
        <x-status>
            {{ $saved }}
            <a href="{{ route('personnes.show', $profile->public_id) }}" class="ml-1 inline-flex min-h-11 items-center font-bold">Voir mon profil</a>
        </x-status>
    @endif

    <a href="{{ route('espace.attestations') }}" class="inline-flex min-h-11 items-center gap-2 self-start text-[14px] font-bold"><x-icon name="shield" :size="18" />Identité et expériences : mes attestations</a>
    <form wire:submit="save" class="flex flex-col gap-3.5">
        <section aria-label="Identité" class="flex flex-col gap-3.5 rounded-card border border-line bg-card p-4 md:p-[18px]">
            @if ($photoAvailable)
                <div class="flex items-center gap-3.5">
                    @if ($pendingPhotoUrl)
                        <img src="{{ $pendingPhotoUrl }}" alt="Aperçu de la nouvelle photo" class="size-[72px] shrink-0 rounded-full object-cover">
                    @elseif ($showPhoto)
                        <x-avatar :profile="$profile" :size="72" />
                    @else
                        <span aria-hidden="true" class="flex size-[72px] shrink-0 items-center justify-center rounded-full bg-type-learning-bg text-[26px] font-extrabold text-type-learning-fg">{{ $profile->user->initials() }}</span>
                    @endif

                    <div class="flex min-w-0 flex-col gap-1.5">
                        <div class="flex flex-wrap gap-2">
                            <label class="relative inline-flex min-h-11 cursor-pointer items-center justify-center rounded-button border border-ink bg-card px-3.5 text-[14px] font-bold has-[:focus-visible]:outline has-[:focus-visible]:outline-[3px] has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-link">
                                <span wire:loading.remove wire:target="photo">{{ $showPhoto ? 'Changer la photo' : 'Ajouter une photo' }}</span>
                                <span wire:loading wire:target="photo">Envoi…</span>
                                <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 size-full cursor-pointer opacity-0">
                            </label>
                            @if ($showPhoto)
                                <x-button type="button" variant="secondary" wire:click="dropPhoto" class="!px-3.5 !text-[14px]">Retirer</x-button>
                            @endif
                        </div>
                        <span class="text-[12px] text-muted">Facultatif. JPEG, PNG ou WebP, 5 Mo au plus. Sinon, vos initiales.</span>
                    </div>
                </div>
                @error('photo')
                    <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            @endif

            <x-wire-field name="name" label="Nom affiché" autocomplete="name" maxlength="255" />
            <x-wire-field name="headline" label="Mon métier ou ce qui me définit (facultatif)" maxlength="100" placeholder="Ex. Plombier · Installation et dépannage" :value="$headline" hint="En une phrase, ce que vous savez faire ou souhaitez apporter. Aucun diplôme n’est nécessaire." />

            <div class="flex flex-col gap-1.5" x-data="{ count: [...$wire.bio].length }">
                <label for="bio" class="text-[13px] font-semibold">Présentation courte</label>
                <textarea id="bio" wire:model="bio" rows="3" maxlength="{{ $bioMax }}"
                          x-on:input="count = [...$event.target.value].length"
                          @class(['w-full resize-none rounded-button border bg-card px-3.5 py-3 text-[15px] leading-normal', $errors->has('bio') ? 'border-danger' : 'border-chip-line'])
                          @if ($errors->has('bio')) aria-invalid="true" aria-describedby="bio-error" @endif></textarea>
                <span class="self-end text-[12px] text-muted" aria-live="off"><span x-text="count">{{ mb_strlen($bio) }}</span> / {{ $bioMax }}</span>
                @error('bio')
                    <p id="bio-error" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="cover-publication" class="text-[13px] font-semibold">Image de couverture (facultative)</label>
                <select id="cover-publication" wire:model="cover_publication_id" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3 text-[14px]">
                    <option value="" @selected($cover_publication_id === '')>Fond sobre, sans image</option>
                    @foreach ($ownPosts->where('media_kind', 'image')->filter(fn ($post) => filled($post->media_path)) as $post)
                        <option value="{{ $post->id }}" @selected($cover_publication_id === $post->id)>{{ Str::limit($post->body, 70) }} · {{ $post->visibility === 'public' ? 'Public' : 'Inscrits seulement' }}</option>
                    @endforeach
                </select>
                <p class="text-[12px] text-muted">Choisissez l’image d’une de vos publications. Elle sera recadrée en bandeau, sans changer sa visibilité : les visiteurs non autorisés verront un fond sobre.</p>
                <a href="{{ route('agir.publier', 'ma-realisation') }}" class="inline-flex min-h-11 items-center self-start text-[13px] font-bold">Publier une réalisation avec une image →</a>
                @error('cover_publication_id')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="country_id" class="text-[13px] font-semibold">Pays</label>
                    <select id="country_id" wire:model.live="country_id"
                            @class(['min-h-12 w-full rounded-button border bg-card px-2.5 text-[15px]', $errors->has('country_id') ? 'border-danger' : 'border-chip-line'])>
                        <option value="">Choisir un pays</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id')
                        <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="city_id" class="text-[13px] font-semibold">Ville</label>
                    <select id="city_id" wire:model="city_id" @disabled($country_id === '')
                            @class(['min-h-12 w-full rounded-button border bg-card px-2.5 text-[15px] disabled:opacity-60', $errors->has('city_id') ? 'border-danger' : 'border-chip-line'])>
                        <option value="">{{ $country_id === '' ? 'Choisissez d’abord un pays' : 'Choisir une ville' }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->label() }}</option>
                        @endforeach
                    </select>
                    @error('city_id')
                        <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        @include('livewire.partials.skill-picker', [
            'list' => 'offered', 'items' => $offered, 'input' => $offeredInput, 'suggestions' => $suggestions['offered'], 'tone' => 'green',
            'title' => 'Je peux apporter', 'help' => 'Ce que vous savez faire et pouvez mettre au service des autres.', 'placeholder' => 'Ex. couture, comptabilité…',
        ])

        @include('livewire.partials.skill-picker', [
            'list' => 'wanted', 'items' => $wanted, 'input' => $wantedInput, 'suggestions' => $suggestions['wanted'], 'tone' => 'blue',
            'title' => 'Je veux apprendre', 'help' => 'Ce que vous aimeriez savoir faire.', 'placeholder' => 'Ex. réparation de panneaux solaires',
        ])

        @include('livewire.partials.matching-profile-editor')
        @include('livewire.partials.profile-details-editor')

        <section aria-labelledby="domaines-titre" class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]" x-data="{ all: false }">
            <div class="flex flex-col gap-1">
                <h2 id="domaines-titre" class="text-[16px] font-extrabold">Domaines qui m’intéressent</h2>
                <span class="text-[13px] text-muted">Trois au plus ({{ count($domain_ids) }} choisi{{ count($domain_ids) > 1 ? 's' : '' }}).</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($domains as $index => $domain)
                    @php($chosen = in_array($domain->id, $domain_ids, true))
                    <button type="button" wire:key="domaine-{{ $domain->id }}" wire:click="toggleDomain('{{ $domain->id }}')" aria-pressed="{{ $chosen ? 'true' : 'false' }}"
                            @if (! $chosen && $index >= 5) x-show="all" x-cloak @endif
                            @class(['min-h-11 rounded-chip px-3 text-[13px]', 'border border-ink bg-ink font-bold text-white' => $chosen, 'border border-chip-line bg-card font-semibold text-ink' => ! $chosen])>
                        {{ $domain->name }}
                    </button>
                @endforeach
                @if ($domains->count() > 5)
                    <button type="button" x-on:click="all = !all" x-bind:aria-expanded="all.toString()"
                            class="min-h-11 rounded-chip px-3 text-[13px] font-bold text-link">
                        <span x-show="!all">Voir tous les domaines</span><span x-show="all" x-cloak>Voir moins</span>
                    </button>
                @endif
            </div>
            @error('domain_ids')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </section>

        <fieldset class="flex flex-col gap-2.5 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <legend class="px-1 text-[16px] font-extrabold">Qui voit mon profil ?</legend>
            @foreach ([
                \App\Models\Profile::PUBLIC => ['Tout le monde', 'Même sans compte. Votre profil peut être trouvé par ceux qui cherchent votre savoir-faire.'],
                \App\Models\Profile::MEMBERS => ['Les personnes inscrites seulement', 'Votre profil n’est visible qu’une fois connecté.'],
            ] as $value => [$label, $explication])
                <label wire:key="visibilite-{{ $value }}" @class(['flex min-h-11 cursor-pointer items-start gap-3 rounded-button p-3', 'border-2 border-ink' => $visibility === $value, 'border border-chip-line' => $visibility !== $value])>
                    <input type="radio" name="visibility" value="{{ $value }}" wire:model.live="visibility" class="mt-0.5 size-5 accent-ink">
                    <span class="flex flex-col gap-0.5">
                        <span class="text-[15px] font-bold">{{ $label }}</span>
                        <span class="text-[13px] text-muted">{{ $explication }}</span>
                    </span>
                </label>
            @endforeach
            @error('visibility')
                <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
            <p class="text-[12px] text-muted">Votre e-mail et votre téléphone ne sont jamais affichés.</p>
        </fieldset>

        <x-button wire:loading.attr="disabled" wire:target="save" class="min-h-[52px] text-[16px] font-extrabold">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </x-button>
    </form>
</div>
