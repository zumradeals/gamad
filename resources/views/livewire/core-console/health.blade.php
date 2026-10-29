<section id="sante" class="{{ $card }} scroll-mt-40" aria-labelledby="t-sante">
        <h2 id="t-sante" class="text-[18px] font-extrabold">Santé</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">

        @foreach ($health as $engine => $signal)
                <article class="rounded-button border border-line p-3" data-testid="health-{{ $engine }}">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold">{{ $engine }}</h3><strong @class(['rounded-button px-2 py-1 text-[12px]', 'bg-soft-blue text-ink' => $signal['state'] === 'sain', 'bg-amber-100 text-amber-950' => $signal['state'] === 'dégradé', 'bg-red-100 text-red-900' => $signal['state'] === 'en panne', 'bg-gray-100 text-gray-800' => in_array($signal['state'], ['inconnu', 'non configuré'])])>{{ ucfirst($signal['state']) }}</strong></div>
                    <p class="mt-2 text-[13px]">{{ $signal['reason'] }}</p>
                    <p class="mt-2 break-words text-[12px] text-muted">Source : {{ $signal['source'] }}<br>Fraîcheur du signal : {{ $signal['freshness'] ?? 'aucun signal reçu' }}</p>
                </article>

        @endforeach
        </div>
    </section>
