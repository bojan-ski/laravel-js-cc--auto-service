@props(['item'])

<a
    href="{{ $item['url'] }}"
    @if ($item['active']) aria-current="page" @endif
    @class([
        'inline-flex items-center gap-2 rounded-sm px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'bg-surface-dark text-text-inverse' => $item['active'],
        'border border-border text-text-soft hover:bg-surface-muted' => ! $item['active'],
    ])
>
    {{ $item['label'] }}
    <span class="tabular-nums text-xs opacity-80" aria-hidden="true">{{ $item['count'] }}</span>
    <span class="sr-only">{{ trans_choice('jobs.board.count', $item['count'], ['count' => $item['count']]) }}</span>
</a>
