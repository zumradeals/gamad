<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Espace" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Avis de la modération</h1>
    </div>

    @if (session('status'))
        <x-status>{{ session('status') }}</x-status>
    @endif

    @if ($notice)
        <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4 md:p-[18px]">
            <p class="text-[13px] text-muted">
                Avis du {{ $notice->created_at->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y') }}{{ $notice->issuer ? ' de la part de '.$notice->issuer->name : '' }}
            </p>
            <p class="whitespace-pre-line break-words text-[15px] leading-normal"><strong>Motif :</strong> {{ $notice->reason }}</p>
            <p class="text-[14px] leading-normal">
                Aucune décision n’est prise sans que vous ayez pu vous expliquer. Écrivez votre explication ci-dessous :
                la modération la lira avant de décider. Vous avez jusqu’au
                <strong>{{ $notice->deadline()->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y, H:i') }}</strong>.
            </p>

            <form wire:submit="explain" class="flex flex-col gap-2">
                <label for="explication" class="text-[13px] font-extrabold">Votre explication</label>
                <textarea id="explication" wire:model="explanation" rows="5" maxlength="{{ \App\Services\PersonModeration::EXPLANATION_MAX }}"
                          @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('explanation') ? 'border-danger' : 'border-chip-line'])
                          @if ($errors->has('explanation')) aria-invalid="true" aria-describedby="explication-erreur" @endif></textarea>
                @error('explanation')
                    <p id="explication-erreur" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
                <x-button wire:loading.attr="disabled" wire:target="explain">
                    <span wire:loading.remove wire:target="explain">{{ $notice->isExplained() ? 'Mettre à jour mon explication' : 'Envoyer mon explication' }}</span>
                    <span wire:loading wire:target="explain">Envoi…</span>
                </x-button>
            </form>
        </section>
    @else
        <section class="rounded-card border border-line bg-card p-5">
            <p class="text-[16px] font-extrabold">Aucun avis de la modération ne vous attend.</p>
            <p class="mt-1 text-muted">Si la modération vous envoie un avis, vous serez prévenu ici et vous pourrez vous expliquer.</p>
        </section>
    @endif
</div>
