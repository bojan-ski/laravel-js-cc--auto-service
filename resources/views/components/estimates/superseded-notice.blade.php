@props(['estimate'])

<div class="mt-4 flex gap-3 border border-border bg-surface-muted p-4" role="note">
    <span aria-hidden="true">&#9432;</span>

    <div>
        <h2 class="font-semibold">{{ __('estimates.statuses.superseded') }}</h2>

        <p class="mt-1 text-sm text-text-muted">
            {{ __('estimates.show.superseded_notice', ['date' => $estimate->superseded_at->isoFormat('D MMM YYYY')]) }}
        </p>
    </div>
</div>
