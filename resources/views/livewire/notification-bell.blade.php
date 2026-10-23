<div wire:poll.30s>
    <a href="{{ route('notifications') }}"
       aria-label="Notifications{{ $count > 0 ? ' : '.$count.' non '.($count > 1 ? 'lues' : 'lue') : '' }}"
       class="relative flex size-11 items-center justify-center rounded-full text-ink no-underline hover:text-ink">
        <x-icon name="bell" />
        @if ($count > 0)
            <span class="absolute right-0.5 top-0.5 flex min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-extrabold leading-5 text-ink" aria-hidden="true">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </a>
</div>
