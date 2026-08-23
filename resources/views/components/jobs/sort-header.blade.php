<th role="columnheader" scope="col" {{ $attributes->merge(['aria-sort' => $ariaSort]) }}>
    <a
        href="{{ $url }}"
        aria-label="{{ $targetLabel }}"
        @class([
            'inline-flex items-center gap-1 rounded-sm hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
            'text-text' => $active,
            'text-text-soft' => ! $active,
        ])
    >
        {{ $label }}
        @if ($active)
            <span aria-hidden="true">{{ $filters['direction'] === 'asc' ? '▲' : '▼' }}</span>
        @endif
    </a>
</th>
