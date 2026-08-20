@props([
    'title',
    'body' => null,
])

<div {{ $attributes->class(['border border-border bg-surface px-4 py-8 text-center']) }}>
    <p class="text-sm font-semibold">{{ $title }}</p>

    @if ($body)
        <p class="mt-1 text-sm text-text-muted">{{ $body }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
