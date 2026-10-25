<div wire:key="{{ $showFollow ? 'suggestion' : 'nearby' }}-{{ $profile->id }}" class="mt-4 flex min-w-0 items-start gap-3">
    <a href="{{ route('personnes.show', $profile->public_id) }}" aria-label="Profil de {{ $profile->user->name }}"><x-avatar :profile="$profile" :size="46" /></a>
    <div class="min-w-0 flex-1">
        <a href="{{ route('personnes.show', $profile->public_id) }}" class="block min-h-8 content-center break-words text-[13px] font-extrabold text-ink">{{ $profile->user->name }}</a>
        @if ($profile->headline)<p class="text-[12px] text-muted [overflow-wrap:anywhere]">{{ $profile->headline }}</p>@endif
        @if ($profile->city)<p class="mt-1 text-[11px] text-muted">{{ $profile->city->label() }}</p>@endif
    </div>
        @if ($showFollow && auth()->user()?->isActive())
            @php($isFollowing = in_array($profile->user_id, $following, true))
            <button type="button" wire:click="follow('{{ $profile->id }}', {{ $isFollowing ? 'false' : 'true' }})" wire:loading.attr="disabled" class="mt-2 min-h-11 shrink-0 rounded-button border border-line px-2 text-[11px] font-bold">{{ $isFollowing ? 'Ne plus suivre' : '+ Suivre' }}</button>
        @endif
</div>
