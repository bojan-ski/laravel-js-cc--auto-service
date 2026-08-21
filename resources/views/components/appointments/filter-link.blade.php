@props(['item'])

<a
    href="{{ $item['url'] }}"
    @if ($item['active']) aria-current="page" @endif
    @class([
        'rounded-sm px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'bg-surface-dark text-text-inverse' => $item['active'],
        'border border-border text-text-soft hover:bg-surface-muted' => ! $item['active'],
    ])
>
    {{ $item['label'] }}
</a>
