@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
])

@php
    $classes = [
        'inline-flex items-center justify-center rounded-sm px-3 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'bg-brand text-text-inverse hover:bg-brand-dark' => $variant === 'primary',
        'border border-border-strong bg-surface text-text hover:bg-surface-muted' => $variant === 'secondary',
        'bg-danger text-text-inverse hover:bg-danger-dark' => $variant === 'danger',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
