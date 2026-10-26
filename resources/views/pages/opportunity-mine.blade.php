<x-layouts.app title="Mes opportunités" narrow>
<div class="flex flex-col gap-4">
    <div class="-mx-4 -mt-4 flex min-h-14 items-center gap-1 border-b border-line bg-card px-2 py-1.5 md:mx-0 md:mt-0 md:rounded-card md:border">
        <a href="{{ route('espace') }}" aria-label="Retour à Mon espace" class="flex size-11 items-center justify-center text-ink hover:text-ink"><x-icon name="back" /></a>
        <h1 class="flex-1 text-[17px] font-extrabold">Mes opportunités</h1>
    </div>
    @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif

    @if ($companies->isNotEmpty())
        <section class="rounded-card border border-line bg-card p-4" aria-labelledby="entreprises-gestion"><h2 id="entreprises-gestion" class="text-[17px] font-extrabold">Opportunités de mon entreprise</h2>
            <ul class="mt-2 space-y-1">@foreach ($companies as $company)<li><a href="{{ route('entreprises.opportunities', $company) }}" class="inline-flex min-h-11 items-center text-[14px] font-bold">Gérer les opportunités de {{ $company->name }}</a></li>@endforeach</ul></section>
    @endif

    <section class="rounded-card border border-line bg-card p-4" aria-labelledby="mes-reponses"><h2 id="mes-reponses" class="text-[17px] font-extrabold">Mes réponses</h2>
        <ul class="mt-2 divide-y divide-line-soft">
            @forelse ($responses as $response)
                <li class="flex min-w-0 items-center gap-3 py-3"><x-company-logo :company="$response->opportunity->company" :size="44" />
                    <div class="min-w-0 flex-1"><a href="{{ route('opportunites.show', $response->opportunity) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $response->opportunity->title }}</a><p class="-mt-2 text-[12px] text-muted">{{ $response->opportunity->company->name }} · {{ $response->stateLabel() }}</p></div></li>
            @empty
                <li class="py-3 text-[14px] text-muted">Vous n’avez répondu à aucune opportunité pour le moment.</li>
            @endforelse
        </ul></section>

    <section class="rounded-card border border-line bg-card p-4" aria-labelledby="mes-sauvegardes"><h2 id="mes-sauvegardes" class="text-[17px] font-extrabold">Opportunités sauvegardées</h2>
        <ul class="mt-2 divide-y divide-line-soft">
            @forelse ($saved as $item)
                <li class="flex min-w-0 items-center gap-3 py-3"><x-company-logo :company="$item->company" :size="44" />
                    <div class="min-w-0 flex-1"><a href="{{ route('opportunites.show', $item) }}" class="inline-flex min-h-11 items-center break-words text-[14px] font-bold">{{ $item->title }}</a><p class="-mt-2 text-[12px] text-muted">{{ $item->company->name }} · {{ $item->statusLabel() }}</p></div></li>
            @empty
                <li class="py-3 text-[14px] text-muted">Aucune opportunité sauvegardée. <a href="{{ route('opportunites.index') }}" class="inline-flex min-h-11 items-center font-bold">Parcourir les opportunités</a></li>
            @endforelse
        </ul></section>
</div>
</x-layouts.app>
