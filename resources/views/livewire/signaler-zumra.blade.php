@php
    $back = route('zumras.show', $post ? ['zumra' => $zumra, 'onglet' => 'discussion'] : $zumra);
    $subject = $post ? 'ce message' : 'cette ZUMRA';
@endphp

<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ $back }}" aria-label="Retour à la ZUMRA" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Signaler</h1>
    </div>

    <section aria-label="{{ $post ? 'Message signalé' : 'ZUMRA signalée' }}" class="flex flex-col gap-2 rounded-card border border-line bg-card p-4">
        <span class="text-[14px] font-extrabold">{{ $post ? $post->author->name.' · '.$zumra->name : $zumra->name }}</span>
        <p class="whitespace-pre-line break-words text-[14px] leading-normal">{{ $post ? \Illuminate\Support\Str::limit($post->body, 200) : \Illuminate\Support\Str::limit($zumra->mission, 200) }}</p>
    </section>

    @if ($already)
        <section class="rounded-card border border-line bg-card p-4">
            <p class="text-[15px] font-extrabold">Vous avez déjà signalé {{ $subject }}.</p>
            <p class="mt-1 text-[14px] text-muted">Un modérateur l’examine. Merci.</p>
            <x-button :href="$back" variant="secondary" class="mt-3">Retour à la ZUMRA</x-button>
        </section>
    @else
        <form wire:submit="send" class="flex flex-col gap-3.5 rounded-card border border-line bg-card p-4">
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-[16px] font-extrabold">Signaler {{ $subject }}</legend>
                @foreach ($categories as $option)
                    <label for="categorie-{{ $option->value }}" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-button border border-chip-line px-3 has-[:checked]:border-ink has-[:checked]:bg-soft-blue">
                        <input id="categorie-{{ $option->value }}" type="radio" wire:model="category" value="{{ $option->value }}" class="size-5 accent-ink">
                        <span class="text-[15px] font-semibold">{{ $option->label() }}</span>
                    </label>
                @endforeach
                @error('category')
                    <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="flex flex-col gap-1.5" x-data="{ n: {{ mb_strlen($details) }} }">
                <label for="precision" class="text-[14px] font-extrabold">Précisez si vous le souhaitez</label>
                <textarea id="precision" wire:model="details" rows="3" maxlength="{{ \App\Models\ZumraReport::DETAILS_MAX }}" x-on:input="n = $el.value.length"
                          @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has('details') ? 'border-danger' : 'border-chip-line'])></textarea>
                <div class="flex justify-between gap-2">
                    @error('details')
                        <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
                    @else
                        <span></span>
                    @enderror
                    <span class="text-[12px] text-muted"><span x-text="n"></span> / {{ \App\Models\ZumraReport::DETAILS_MAX }}</span>
                </div>
            </div>

            <p class="rounded-button bg-type-call-bg px-3 py-2.5 text-[12px] font-semibold leading-normal text-type-call-fg">
                {{ $post ? 'Un modérateur lira ce message, et lui seul : le reste de la Discussion reste privé. La personne ne saura pas qui a signalé.' : 'Les responsables de la ZUMRA ne sauront pas qui a signalé. Un modérateur examine le signalement.' }}
            </p>

            <x-button wire:loading.attr="disabled" wire:target="send">
                <span wire:loading.remove wire:target="send">Envoyer le signalement</span>
                <span wire:loading wire:target="send">Envoi…</span>
            </x-button>
        </form>
    @endif
</div>
