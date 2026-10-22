@props(['tagline' => true])

<span {{ $attributes->class('flex flex-col') }}>
    <span class="text-[22px] font-extrabold tracking-[-0.5px] leading-tight text-ink">GAMAD</span>
    @if ($tagline)
        <span class="text-[11px] font-medium text-muted">Des personnes. Des actions. Un impact réel.</span>
    @endif
</span>
