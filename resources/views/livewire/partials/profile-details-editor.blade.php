<section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-6">
    <h2 class="text-[18px] font-extrabold">Ce qui me fait avancer</h2>
    <p class="text-[13px] text-muted">Votre histoire, vos envies et votre façon d’aider. Votre parcours peut se construire partout.</p>
    <label for="introduction" class="text-[14px] font-bold">Ma présentation libre</label>
    <textarea id="introduction" wire:model="introduction" rows="5" maxlength="1200" class="w-full rounded-button border border-chip-line bg-card p-3 text-[15px]"></textarea>
    <p class="text-[12px] text-muted">1 200 caractères au plus. La même visibilité que votre profil s’applique.</p>
    @error('introduction')<p role="alert" class="text-danger">{{ $message }}</p>@enderror
</section>
<section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-6">
    <h2 class="text-[18px] font-extrabold">Mon savoir-faire en pratique</h2>
    <p class="text-[13px] text-muted">Expliquez ce que vous pouvez apporter et associez, si vous le souhaitez, une publication qui le montre.</p>
    @foreach ($skill_details as $i => $detail)
        <fieldset wire:key="skill-detail-{{ $i }}" class="flex min-w-0 flex-col gap-2 rounded-button border border-line p-3">
            <legend class="text-[14px] font-bold">Savoir-faire {{ $i + 1 }}</legend>
            <label for="detail-skill-{{ $i }}" class="text-[13px] font-semibold">Capacité</label>
            <select id="detail-skill-{{ $i }}" wire:model="skill_details.{{ $i }}.skill" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3">
                <option value="">Choisir une capacité</option>@foreach ($offered as $skill)<option value="{{ $skill }}">{{ $skill }}</option>@endforeach
            </select>
            <label for="detail-description-{{ $i }}" class="text-[13px] font-semibold">Ce que je sais faire concrètement</label>
            <textarea id="detail-description-{{ $i }}" wire:model="skill_details.{{ $i }}.description" maxlength="280" rows="3" class="w-full rounded-button border border-chip-line bg-card p-3"></textarea>
            <label for="detail-proof-{{ $i }}" class="text-[13px] font-semibold">Une publication pour l’illustrer (facultatif)</label>
            <select id="detail-proof-{{ $i }}" wire:model="skill_details.{{ $i }}.publication_id" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3">
                <option value="">Aucune</option>@foreach ($ownPosts as $post)<option value="{{ $post->id }}">{{ Str::limit($post->body, 60) }}</option>@endforeach
            </select>
            <x-button type="button" variant="secondary" wire:click="removeSkillDetail({{ $i }})">Retirer ce savoir-faire</x-button>
        </fieldset>
    @endforeach
    @if (count($skill_details) < 10)<x-button type="button" variant="secondary" wire:click="addSkillDetail">Décrire un savoir-faire</x-button>@endif
    @foreach ($errors->get('skill_details*') as $messages)@foreach ($messages as $message)<p role="alert" class="text-danger">{{ $message }}</p>@endforeach @endforeach
</section>
<section id="profile-experiences" class="flex scroll-mt-44 flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-6">
    <h2 class="text-[18px] font-extrabold">Mon parcours et mes expériences</h2>
    <p class="text-[13px] text-muted">Un apprentissage, un engagement, une expérience de vie ou de travail : racontez ce qui vous a construit. Ces informations sont déclarées par vous.</p>
    @foreach ($experiences as $i => $experience)
        <fieldset wire:key="experience-{{ $i }}" class="flex min-w-0 flex-col gap-2 rounded-button border border-line p-3">
            <legend class="text-[14px] font-bold">Expérience {{ $i + 1 }}</legend>
            <x-wire-field :name="'experiences.'.$i.'.title'" label="Intitulé" maxlength="100" />
            <label for="experience-kind-{{ $i }}" class="text-[13px] font-semibold">Nature de l’expérience</label>
            <select id="experience-kind-{{ $i }}" wire:model="experiences.{{ $i }}.kind" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3">
                @foreach (['personal' => 'Personnelle', 'professional' => 'Professionnelle', 'volunteer' => 'Associative ou bénévole', 'learning' => 'Apprentissage'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <x-wire-field :name="'experiences.'.$i.'.period'" label="Période ou contexte (facultatif)" maxlength="80" />
            <label for="experience-description-{{ $i }}" class="text-[13px] font-semibold">Ce que j’ai fait ou appris</label>
            <textarea id="experience-description-{{ $i }}" wire:model="experiences.{{ $i }}.description" maxlength="500" rows="3" class="w-full rounded-button border border-chip-line bg-card p-3"></textarea>
            <x-button type="button" variant="secondary" wire:click="removeExperience({{ $i }})">Retirer cette expérience</x-button>
        </fieldset>
    @endforeach
    @if (count($experiences) < 10)<x-button type="button" variant="secondary" wire:click="addExperience">Ajouter une expérience</x-button>@endif
    @foreach ($errors->get('experiences*') as $messages)@foreach ($messages as $message)<p role="alert" class="text-danger">{{ $message }}</p>@endforeach @endforeach
</section>
<section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-6">
    <h2 class="text-[18px] font-extrabold">À la une sur mon profil</h2>
    <p class="text-[13px] text-muted">Choisissez jusqu’à trois publications pour mettre vos réalisations et votre savoir-faire en évidence. Leur visibilité est conservée : une publication réservée aux inscrits ne devient pas publique ici.</p>
    <div class="max-h-72 overflow-y-auto">
        @forelse ($ownPosts as $post)
            <label class="flex min-h-11 cursor-pointer items-start gap-3 py-2"><input type="checkbox" wire:model="featured_publications" value="{{ $post->id }}" class="mt-1 size-5 shrink-0 accent-ink"><span class="min-w-0 break-words text-[14px]">{{ Str::limit($post->body, 120) }} <span class="text-[12px] text-muted">· {{ $post->visibility === 'public' ? 'Public' : 'Inscrits seulement' }}</span></span></label>
        @empty <p class="text-[14px] text-muted">Vos publications personnelles apparaîtront ici.</p>@endforelse
    </div>
    @foreach ($errors->get('featured_publications*') as $messages)@foreach ($messages as $message)<p role="alert" class="text-danger">{{ $message }}</p>@endforeach @endforeach
</section>
