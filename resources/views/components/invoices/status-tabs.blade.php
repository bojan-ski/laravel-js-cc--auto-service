<nav class="mt-4 flex flex-wrap gap-1" aria-label="{{ __('invoices.tabs.label') }}">
    @foreach ($tabs as $tab)
        <a
            href="{{ $tab['url'] }}"
            @if ($tab['active']) aria-current="page" @endif
            @class([
                'inline-flex items-center gap-2 rounded-sm px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
                'bg-surface-dark text-text-inverse' => $tab['active'],
                'border border-border text-text-soft hover:bg-surface-muted' => ! $tab['active'],
            ])
        >
            {{ $tab['label'] }}
            <span class="tabular-nums text-xs opacity-80" aria-hidden="true">{{ $tab['count'] }}</span>
            <span class="sr-only">{{ trans_choice('invoices.count', $tab['count'], ['count' => $tab['count']]) }}</span>
        </a>
    @endforeach
</nav>
