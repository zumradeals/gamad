@props(['tagline' => false])

<span {{ $attributes->class('flex flex-col') }}>
    @if ($brandLogo = app(\App\Services\Settings::class)->get('brand_logo'))
        <img src="{{ route('branding.image', 'logo') }}?v={{ md5($brandLogo) }}" alt="GAMAD" width="120" height="44" class="h-11 w-auto max-w-[140px] object-contain">
    @else
    <span class="text-[22px] font-extrabold tracking-[-0.5px] leading-tight text-ink">GAMAD</span>
    @endif
    @if ($tagline)
        <span class="text-[11px] font-medium text-muted">Des personnes. Des actions. Un impact réel.</span>
    @endif
</span>
