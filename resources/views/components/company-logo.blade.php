@props(['company', 'size' => 56])

{{-- Logo de l'entreprise, ou ses initiales : jamais un document administratif. --}}
<span {{ $attributes->class(['flex shrink-0 items-center justify-center overflow-hidden rounded-button border border-line bg-soft-blue text-[14px] font-extrabold']) }} style="width: {{ $size }}px; height: {{ $size }}px">
    @if ($company->logo_path)<img src="{{ route('entreprises.visual', [$company, 'logo']) }}" alt="" loading="lazy" class="size-full bg-white object-contain">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}</span>@endif
</span>
