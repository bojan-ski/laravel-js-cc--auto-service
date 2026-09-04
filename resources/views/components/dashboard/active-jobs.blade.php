@props(['jobs', 'count'])

<section class="border border-border bg-surface">
    <h2 class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
        {{ trans_choice('dashboard.active_jobs.title', $count, ['count' => $count]) }}
    </h2>

    @if ($jobs->isEmpty())
        <p class="px-3 py-4 text-sm text-text-muted">{{ __('dashboard.active_jobs.empty') }}</p>
    @else
        <ul>
            @foreach ($jobs as $job)
                <li class="border-b border-border last:border-b-0">
                    <a
                        href="{{ route('jobs.show', $job) }}"
                        class="flex items-baseline gap-2 px-3 py-2 text-sm hover:bg-surface-muted hover:text-brand-dark"
                    >
                        <span class="font-medium tabular-nums underline underline-offset-2">{{ $job->vehicle->registration_plate }}</span>
                        <span class="min-w-0 truncate text-text-muted">{{ $job->vehicle->make }} {{ $job->vehicle->model }}</span>
                        <span class="sr-only">{{ __('dashboard.active_jobs.view_job', ['reference' => $job->reference]) }}</span>

                        <x-ui.status-badge class="ms-auto" :status="$job->status" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="border-t border-border px-3 py-2 text-end text-sm">
        <a
            href="{{ route('jobs.index') }}"
            class="font-medium underline underline-offset-2 hover:text-brand-dark"
        >{{ __('dashboard.active_jobs.view_all') }} <span aria-hidden="true">&rarr;</span></a>
    </p>
</section>
