{{-- Motif et actes d'une publication dans la file. Variables : $publication, $mode ('review' ou 'hidden'). --}}
@php $id = $publication->id; @endphp

<div class="flex flex-col gap-2">
    <label for="motif-{{ $id }}" class="text-[13px] font-extrabold">
        {{ $mode === 'review' ? 'Motif (obligatoire pour masquer : l’auteur le verra)' : 'Raison du rétablissement (facultative, inscrite au journal)' }}
    </label>
    <textarea id="motif-{{ $id }}" wire:model="reasons.{{ $id }}" rows="2" maxlength="{{ \App\Services\Moderation::REASON_MAX }}"
              @class(['w-full rounded-button border bg-card px-3.5 py-3 text-[15px]', $errors->has("reasons.$id") ? 'border-danger' : 'border-chip-line'])
              @if ($errors->has("reasons.$id")) aria-invalid="true" aria-describedby="motif-erreur-{{ $id }}" @endif></textarea>
    @error("reasons.$id")
        <p id="motif-erreur-{{ $id }}" role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
    @enderror

    <div class="flex flex-col gap-2 sm:flex-row">
        @if ($mode === 'review')
            <x-button type="button" wire:click="hide('{{ $id }}')" wire:loading.attr="disabled" wire:target="hide, dismiss" class="sm:flex-1">Masquer</x-button>
            <x-button type="button" variant="secondary" wire:click="dismiss('{{ $id }}')" wire:loading.attr="disabled" wire:target="hide, dismiss" class="sm:flex-1">Classer sans suite</x-button>
        @else
            <x-button type="button" variant="secondary" wire:click="restore('{{ $id }}')" wire:loading.attr="disabled" wire:target="restore" class="sm:flex-1">Rétablir</x-button>
        @endif
    </div>
</div>
