@props(['item', 'block' => false])

<a
    href="{{ $item['url'] }}"
    @class([
        'rounded-sm px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'block' => $block,
        'bg-text-inverse/10 text-text-inverse' => $item['active'],
        'text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse' => ! $item['active'],
    ])
    @if ($item['active']) aria-current="page" @endif
>
    {{ $item['label'] }}
</a>
