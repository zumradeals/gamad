@if ($brandIcon = app(\App\Services\Settings::class)->get('brand_icon'))
    <link rel="icon" type="image/png" href="{{ route('branding.image', 'icon') }}?v={{ md5($brandIcon) }}">
    <link rel="apple-touch-icon" href="{{ route('branding.image', 'icon') }}?v={{ md5($brandIcon) }}">
@endif
