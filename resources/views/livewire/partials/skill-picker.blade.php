{{-- Sélecteur de capacités ($plain : sans cadre de carte) : $list ('offered'|'wanted'|'skills'), $items, $input, $suggestions, $tone ('green'|'blue'|'neutral'), $title, $help, $placeholder, $max (facultatif, 10 par défaut) --}}
@php
    $inputName = $list.'Input';
    $max = $max ?? \App\Models\Profile::MAX_SKILLS_PER_KIND;
    $isGreen = $tone === 'green';
    $chip = match ($tone) { 'green' => 'bg-type-knowledge-bg text-type-knowledge-fg', 'blue' => 'bg-type-learning-bg text-type-learning-fg', default => 'bg-type-need-bg text-type-need-fg' };
    $full = count($items) >= $max;
    $typedKey = \App\Support\Normalizer::key($input);
    $typedDisplay = \App\Support\Normalizer::display($input);
    $hasExact = $suggestions->contains(fn ($skill) => $skill->normalized === $typedKey);
    $alreadyChosen = in_array($typedKey, array_map(\App\Support\Normalizer::key(...), $items), true);
@endphp

<section aria-labelledby="{{ $list }}-titre" @class(['flex flex-col gap-3', 'rounded-card border border-line bg-card p-4 md:p-[18px]' => ! ($plain ?? false)])>
    <div class="flex flex-col gap-1">
        <label for="{{ $inputName }}" id="{{ $list }}-titre" @class(['text-[16px] font-extrabold' => ! ($plain ?? false), 'text-[13px] font-bold text-muted' => ($plain ?? false), 'text-type-knowledge-fg' => $tone === 'green' && ! ($plain ?? false), 'text-type-learning-fg' => $tone === 'blue' && ! ($plain ?? false), 'text-ink' => $tone === 'neutral' && ! ($plain ?? false)])>{{ $title }}</label>
        <span class="text-[13px] text-muted">{{ $help }} Jusqu’à {{ $max }} ({{ count($items) }} choisie{{ count($items) > 1 ? 's' : '' }}).</span>
    </div>

    @if ($items !== [])
        <ul class="flex flex-wrap gap-2">
            @foreach ($items as $index => $item)
                <li wire:key="{{ $list }}-{{ $index }}-{{ $item }}" class="inline-flex min-h-11 items-center gap-0.5 rounded-chip pl-3 pr-1 text-[14px] font-bold {{ $chip }}">
                    {{ $item }}
                    <button type="button" wire:click="removeSkill('{{ $list }}', {{ $index }})" aria-label="Retirer {{ $item }}"
                            class="flex size-11 items-center justify-center rounded-full">
                        <x-icon name="close" :size="14" :stroke="2.5" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($full)
        <p class="text-[13px] font-semibold text-muted">{{ $this::skillLimitMessage($max) }}</p>
    @else
        <div class="flex flex-col">
            <input id="{{ $inputName }}" type="text" wire:model.live.debounce.250ms="{{ $inputName }}"
                   wire:keydown.enter.prevent="addSkill('{{ $list }}')"
                   maxlength="{{ \App\Models\Skill::MAX_LENGTH }}" autocomplete="off" placeholder="{{ $placeholder }}"
                   @class(['min-h-12 w-full rounded-button border bg-card px-3.5 text-[15px] text-ink placeholder:text-muted', $errors->has($inputName) ? 'border-danger' : 'border-chip-line'])
                   @if ($errors->has($inputName)) aria-invalid="true" aria-describedby="{{ $inputName }}-error" @endif>

            @if ($typedKey !== '')
                <div role="listbox" aria-label="Suggestions" class="mt-1.5 flex flex-col overflow-hidden rounded-button border border-line bg-card">
                    @foreach ($suggestions as $skill)
                        <button type="button" role="option" wire:key="{{ $list }}-s-{{ $skill->id }}" wire:click="addSkill('{{ $list }}', @js($skill->name))"
                                class="flex min-h-12 items-center justify-between gap-3 border-b border-line-soft px-3.5 text-left text-[15px] last:border-b-0">
                            <span class="min-w-0 break-words">{{ $skill->name }}</span>
                            <span class="shrink-0 text-[12px] text-muted">déjà utilisée</span>
                        </button>
                    @endforeach

                    @if ($alreadyChosen)
                        <p class="px-3.5 py-3 text-[13px] text-muted">Cette capacité est déjà dans votre liste.</p>
                    @elseif (! $hasExact && $typedDisplay !== '')
                        <button type="button" role="option" wire:click="addSkill('{{ $list }}')"
                                class="flex min-h-12 items-center px-3.5 text-left text-[15px] font-bold text-link">
                            Ajouter « {{ $typedDisplay }} »
                        </button>
                    @endif
                </div>
            @endif

            @error($inputName)
                <p id="{{ $inputName }}-error" role="alert" class="mt-1.5 text-[13px] font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @error($list)
        <p role="alert" class="text-[13px] font-semibold text-danger">{{ $message }}</p>
    @enderror
</section>
