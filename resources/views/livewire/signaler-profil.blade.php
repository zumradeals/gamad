<div class="flex min-w-0 flex-col gap-4">
    <a href="{{ route('espace.attestations') }}" class="inline-flex min-h-11 items-center self-start font-bold">Mes attestations</a>
    <h1 class="text-[24px] font-extrabold">Signalement ou recours</h1><p class="break-words text-[15px]">Concernant {{ $subject->name }}{{ $work ? ' et une référence précise' : '' }}.</p>
    @if ($sent)<x-status>Votre demande a été enregistrée. Vous retrouverez la réponse dans Mes attestations.</x-status>@else
        <p class="text-[14px] text-muted">Votre nom et votre texte sont réservés à l’examen administratif. Ne joignez ni numéro de pièce ni donnée sensible. Le signalement seul ne retire pas un badge.</p>
        <form wire:submit="submit" class="flex flex-col gap-3 rounded-card border border-line bg-card p-5">
            <label for="trust-category" class="text-[14px] font-bold">Motif</label><select id="trust-category" wire:model="category" class="min-h-12 w-full rounded-button border border-chip-line bg-card px-3">@foreach (\App\Models\TrustReport::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>@endforeach</select>
            <label for="trust-details" class="text-[14px] font-bold">Les faits à examiner</label><textarea id="trust-details" wire:model="details" maxlength="500" required rows="5" class="w-full rounded-button border border-chip-line bg-card p-3"></textarea>
            @foreach ($errors->all() as $error)<p role="alert" class="text-[14px] text-danger">{{ $error }}</p>@endforeach
            <x-button wire:loading.attr="disabled">Transmettre à GAMAD</x-button><p wire:loading role="status" class="text-[13px] text-muted">Envoi…</p>
        </form>
    @endif
</div>
