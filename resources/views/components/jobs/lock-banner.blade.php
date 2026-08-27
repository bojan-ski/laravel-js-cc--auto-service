@props(['job'])

@if ($job->isLocked())
    <div class="mt-4 flex gap-3 border border-border bg-surface-muted p-4">
        <span aria-hidden="true">&#128274;</span>

        <div>
            <p class="font-semibold">
                {{ __('jobs.show.lock.title', ['date' => $job->locked_at->isoFormat('D MMM')]) }}
            </p>

            <p class="mt-1 text-sm text-text-muted">{{ __('jobs.show.lock.body') }}</p>
        </div>
    </div>
@endif
