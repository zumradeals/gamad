<section class="flex min-w-0 flex-col gap-3 rounded-card border border-line bg-card p-4">
    <h2 class="text-[17px] font-extrabold">Conditions et matching</h2>
    <label class="flex min-h-11 cursor-pointer items-center gap-3"><input type="checkbox" wire:model.live="matching.enabled" @checked($matching['enabled']) class="size-5 shrink-0 accent-ink"><span class="text-[14px] font-semibold">Proposer cette publication aux personnes compatibles</span></label>
    @if ($matching['enabled'])
        @if ($type === \App\Enums\PublicationType::Offer)<div><label for="matching-activity" class="text-[13px] font-bold">Ce que je propose</label><select id="matching-activity" wire:model.live="matching.activity" class="mt-1 min-h-12 w-full rounded-button border border-chip-line bg-card px-3"><option value="service" @selected($matching['activity'] === 'service')>Une prestation ou une aide</option><option value="training" @selected($matching['activity'] === 'training')>Une formation ou une transmission</option></select></div>@endif
        <div><label for="matching-terms" class="text-[13px] font-bold">Conditions financières</label><select id="matching-terms" wire:model.live="matching.terms" class="mt-1 min-h-12 w-full rounded-button border border-chip-line bg-card px-3">@foreach (\App\Services\MatchingConditions::TERMS as $value => $label)<option value="{{ $value }}" @selected($matching['terms'] === $value)>{{ $label }}</option>@endforeach</select></div>
        @if ($matching['terms'] === 'paid')<x-wire-field name="matching.budget" label="Budget ou prix indicatif (facultatif)" maxlength="100" placeholder="Ex. À discuter, ou montant et unité" />@endif
        <p class="text-[12px] text-muted">Un besoin de travail rémunéré est payé par le demandeur à l’intervenant ; une formation payante est payée par l’apprenant. Un prix indicatif ne crée aucun accord.</p>
        <label class="flex min-h-11 cursor-pointer items-center gap-3"><input type="checkbox" wire:model="matching.remote" @checked($matching['remote']) class="size-5 shrink-0 accent-ink"><span class="text-[14px]">Possible aussi à distance</span></label>
        @include('livewire.partials.matching-schedule', ['prefix' => 'matching'])
        <p class="text-[12px] text-muted">Renseignez au moins une capacité et une localité ci-dessous, ou la possibilité à distance. Pour recevoir des pistes vous-même, <a class="inline-flex min-h-11 items-center font-bold" href="{{ route('espace.profil') }}#matching-preferences">choisissez vos préférences</a>.</p>
    @endif
    @foreach ($errors->get('matching*') as $messages)@foreach ($messages as $message)<p role="alert" class="text-[13px] text-danger">{{ $message }}</p>@endforeach @endforeach
</section>
