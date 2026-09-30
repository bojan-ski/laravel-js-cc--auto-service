@props(['job', 'summary'])

@php
    $estimate = $summary['estimate'] ?? null;
    $open = $estimate !== null && $estimate->status->isOpen();
@endphp

<div class="flex flex-wrap items-center gap-x-2 gap-y-1">
    @if ($estimate)
        <span class="tabular-nums">{{ $estimate->number }}</span>

        <x-ui.status-badge :status="$estimate->status" />

        @if ($estimate->wasDecided())
            <span class="text-sm text-text-muted">
                {{ __('estimates.summary.method', ['method' => $estimate->decision_method->label()]) }}
            </span>
        @endif

        <a
            href="{{ route('estimates.show', $estimate) }}"
            class="font-medium underline underline-offset-2 hover:text-brand-dark"
            aria-label="{{ __('estimates.summary.view_for', ['number' => $estimate->number]) }}"
        >
            {{ __('estimates.summary.view') }}
        </a>
    @else
        <span class="text-text-muted">{{ __('estimates.summary.none') }}</span>
    @endif

    @can('estimate', $job)
        <x-ui.action-form
            :action="route('jobs.estimates.store', $job)"
            method="POST"
            :confirm="$open ? __('estimates.create.revise_confirm', ['number' => $estimate->number]) : null"
            :aria-label="$open
                ? __('estimates.create.revise_for', ['reference' => $job->reference])
                : __('estimates.create.action_for', ['reference' => $job->reference])"
        >
            {{ $open ? __('estimates.create.revise') : __('estimates.create.action') }}
        </x-ui.action-form>
    @endcan
</div>

@if ($summary['warning'] ?? null)
    <x-estimates.warning class="mt-1" :summary="$summary" />
@endif
