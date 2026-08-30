@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'inline-flex items-center rounded-sm px-3 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'bg-surface-dark text-text-inverse' => $active,
        'border border-border-strong bg-surface text-text hover:bg-surface-muted' => ! $active,
    ]) }}
>{{ $slot }}</a>
