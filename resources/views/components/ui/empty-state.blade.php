@props([
    'title',
    'body' => null,
    'level' => 2,
])

@php
    $titleTag = (int) $level === 0 ? 'p' : 'h'.(int) $level;
@endphp

<div {{ $attributes->class(['border border-border bg-surface px-4 py-8 text-center']) }}>
    <{{ $titleTag }} class="text-sm font-semibold">{{ $title }}</{{ $titleTag }}>

    @if ($body)
        <p class="mt-1 text-sm text-text-muted">{{ $body }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
