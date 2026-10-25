<x-layouts.app title="Identité visuelle" narrow>
    <div class="flex flex-col gap-4">
        <a href="{{ route('espace.parametres') }}" class="inline-flex min-h-11 items-center font-bold">← Paramètres</a>
        <h1 class="text-[24px] font-extrabold">Identité visuelle</h1>
        <p class="text-muted">Logo du site et icône du navigateur ou du raccourci mobile. Les modifications sont publiques et inscrites au journal.</p>
        @if (session('status'))<x-status>{{ session('status') }}</x-status>@endif
        @foreach ($errors->all() as $error)<p role="alert" class="text-danger">{{ $error }}</p>@endforeach
        @foreach (['logo' => 'Logo du site', 'icon' => 'Favicon et icône mobile'] as $kind => $label)
            <section class="flex flex-col gap-3 rounded-card border border-line bg-card p-4">
                <h2 class="text-[18px] font-extrabold">{{ $label }}</h2>
                @if (app(\App\Services\Settings::class)->get('brand_'.$kind))
                    <img src="{{ route('branding.image', $kind) }}?v={{ md5(app(\App\Services\Settings::class)->get('brand_'.$kind)) }}" alt="{{ $label }} actuel" class="h-20 max-w-full self-start object-contain">
                @else
                    <p class="text-[13px] text-muted">{{ $kind === 'logo' ? 'Le nom GAMAD est utilisé par défaut.' : 'Aucune icône personnalisée.' }}</p>
                @endif
                <p class="text-[13px] text-muted">PNG transparent conseillé, JPEG ou WebP acceptés. 2 Mo maximum, 4096 × 4096 pixels maximum. {{ $kind === 'icon' ? 'Image carrée : 512 × 512 pixels conseillé.' : 'Symbole carré ou logo horizontal, proportions conservées.' }}</p>
                <form method="POST" action="{{ route('espace.parametres.identite.save') }}" enctype="multipart/form-data" class="flex flex-col gap-3" x-data="{ preview: null }">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <label for="brand-{{ $kind }}" class="text-[13px] font-bold">Choisir une image</label>
                    <input id="brand-{{ $kind }}" type="file" name="image" required accept="image/png,image/jpeg,image/webp" class="min-h-11 w-full min-w-0" x-on:change="if(preview) URL.revokeObjectURL(preview); preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                    <img x-show="preview" x-cloak :src="preview" alt="Aperçu avant enregistrement" class="h-20 max-w-full self-start object-contain">
                    <x-button>Enregistrer {{ $kind === 'logo' ? 'le logo' : 'l’icône' }}</x-button>
                </form>
                @if (app(\App\Services\Settings::class)->get('brand_'.$kind))
                    <form method="POST" action="{{ route('espace.parametres.identite.save') }}">
                        @csrf<input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="remove" value="1">
                        <x-button variant="secondary">Rétablir l’affichage par défaut</x-button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.app>
