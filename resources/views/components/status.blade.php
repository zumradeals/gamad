@props(['tone' => 'info'])

<div role="status" {{ $attributes->class(['rounded-button border px-4 py-3 text-[14px] font-semibold', $tone === 'error' ? 'border-danger text-danger bg-card' : 'border-line bg-type-knowledge-bg text-type-knowledge-fg']) }}>
    {{ $slot }}
</div>
