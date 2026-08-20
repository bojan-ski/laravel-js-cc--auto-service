@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="inline-flex items-center px-4 py-2 text-sm font-medium text-text-muted bg-surface-muted border border-border cursor-not-allowed leading-5 rounded-sm">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-text bg-surface border border-border leading-5 rounded-sm hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-text bg-surface border border-border leading-5 rounded-sm hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span aria-disabled="true" class="inline-flex items-center px-4 py-2 text-sm font-medium text-text-muted bg-surface-muted border border-border cursor-not-allowed leading-5 rounded-sm">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
