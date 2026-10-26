<x-layouts.app :title="$project->name">
    @if ($showcase)
        @livewire('projet-vitrine', ['project' => $project])
    @else
        @livewire('monde-projet', ['project' => $project])
    @endif
</x-layouts.app>
