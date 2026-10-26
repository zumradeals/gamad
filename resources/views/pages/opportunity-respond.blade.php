@php $apply = $opportunity->kind->applyLabel(); @endphp
<x-layouts.app :title="$apply.' — '.$opportunity->title" narrow>
<div class="flex flex-col gap-3.5">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('opportunites.show', $opportunity) }}" aria-label="Retour à l’opportunité" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">{{ $apply === 'Postuler' ? 'Postuler' : 'Je suis intéressé' }}</h1>
    </div>

    <section class="flex min-w-0 items-center gap-3 rounded-card border border-line bg-card p-4">
        <x-company-logo :company="$opportunity->company" :size="52" />
        <div class="min-w-0"><p class="break-words text-[16px] font-extrabold">{{ $opportunity->title }}</p><p class="text-[13px] text-muted">{{ $opportunity->company->name }}@if ($opportunity->city) · {{ $opportunity->city->label() }}@endif</p></div>
    </section>

    <form method="POST" action="{{ route('opportunites.respond.store', $opportunity) }}" enctype="multipart/form-data" class="flex flex-col gap-4 rounded-card border border-line bg-card p-4" x-data="{ sending: false, count: 0 }" x-on:submit="sending = true">
        @csrf
        <p class="rounded-button bg-soft-blue px-3 py-2.5 text-[13px] leading-normal">L’entreprise verra votre nom et pourra ouvrir votre profil public GAMAD. Votre téléphone et votre e-mail ne lui sont jamais montrés. Votre message et votre document restent privés.</p>
        <div class="flex flex-col gap-1.5">
            <label for="message" class="text-[15px] font-extrabold">Un mot de présentation <span class="text-[13px] font-semibold text-muted">(facultatif)</span></label>
            <textarea id="message" name="message" rows="5" maxlength="1000" x-on:input="count = [...$event.target.value].length" placeholder="Ex. Bonjour, je suis électricien depuis 5 ans à Abidjan et je serais heureux de vous aider." @class(['min-h-[140px] w-full resize-y rounded-button border bg-card px-3.5 py-3 text-[16px] leading-normal', $errors->has('message') ? 'border-danger' : 'border-chip-line'])>{{ old('message') }}</textarea>
            <span class="self-end text-[12px] text-muted"><span x-text="count">0</span> / 1000</span>
            @error('message')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-col gap-1.5" x-data="{ name: '' }">
            <span class="text-[15px] font-extrabold">Un document <span class="text-[13px] font-semibold text-muted">(facultatif, seulement si utile)</span></span>
            <label for="attachment" class="relative inline-flex min-h-11 cursor-pointer items-center gap-2 self-start rounded-button border border-ink bg-card px-4 text-[14px] font-bold focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-link">
                <x-icon name="plus" :size="18" />Ajouter un CV ou un document PDF
                <input id="attachment" name="attachment" type="file" accept="application/pdf" x-on:change="name = $event.target.files[0]?.name ?? ''" class="absolute inset-0 size-full cursor-pointer opacity-0">
            </label>
            <p x-show="name" x-cloak class="break-words text-[13px] font-semibold" x-text="name"></p>
            <p class="text-[12px] text-muted">PDF de 5 Mo au plus. Aucun CV n’est exigé.</p>
            @error('attachment')<p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>@enderror
        </div>
        <x-button variant="accent" class="min-h-[52px]" x-bind:disabled="sending"><span x-show="! sending">Envoyer {{ $apply === 'Postuler' ? 'ma candidature' : 'mon intérêt' }}</span><span x-show="sending" x-cloak>Envoi…</span></x-button>
    </form>
</div>
</x-layouts.app>
