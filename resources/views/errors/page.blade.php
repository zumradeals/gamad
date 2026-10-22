<x-layouts.guest :title="$title" :heading="$title">
    <p>{{ $message }}</p>
    <x-button :href="url('/')" variant="secondary">Retour à l’accueil</x-button>
</x-layouts.guest>
