@props(['job', 'invoice' => null])

@php
    $context = [
        'reference' => $job->reference,
        'plate' => $job->vehicle->registration_plate,
    ];

    $partLines = $job->partLineCount();

    $confirmCancel = $partLines > 0
        ? trans_choice('jobs.actions.confirm_cancel_with_parts', $partLines, [...$context, 'count' => $partLines])
        : __('jobs.actions.confirm_cancel', $context);
@endphp

<x-jobs.lock-banner :job="$job" :invoice-number="$invoice?->number" />

@canany(['start', 'pause', 'resume', 'complete', 'cancel', 'reopen', 'issue'], $job)
    <div class="mt-4 flex flex-wrap gap-2">
        @can('start', $job)
            <x-ui.action-form
                :action="route('jobs.start', $job)"
                :aria-label="__('jobs.actions.start_for', $context)"
            >
                {{ __('jobs.actions.start') }}
            </x-ui.action-form>
        @endcan

        @can('pause', $job)
            <x-ui.action-form
                :action="route('jobs.pause', $job)"
                :aria-label="__('jobs.actions.pause_for', $context)"
            >
                {{ __('jobs.actions.pause') }}
            </x-ui.action-form>
        @endcan

        @can('resume', $job)
            <x-ui.action-form
                :action="route('jobs.resume', $job)"
                :aria-label="__('jobs.actions.resume_for', $context)"
            >
                {{ __('jobs.actions.resume') }}
            </x-ui.action-form>
        @endcan

        @can('complete', $job)
            <x-ui.action-form
                :action="route('jobs.complete', $job)"
                :aria-label="__('jobs.actions.complete_for', $context)"
            >
                {{ __('jobs.actions.complete') }}
            </x-ui.action-form>
        @endcan

        @can('issue', $job)
            <x-ui.button
                :href="route('jobs.invoice.create', $job)"
                :aria-label="__('jobs.actions.issue_for', $context)"
            >
                {{ __('jobs.actions.issue') }}
            </x-ui.button>
        @endcan

        @can('reopen', $job)
            <x-ui.action-form
                :action="route('jobs.reopen', $job)"
                :aria-label="__('jobs.actions.reopen_for', $context)"
            >
                {{ __('jobs.actions.reopen') }}
            </x-ui.action-form>
        @endcan

        @can('cancel', $job)
            <x-ui.action-form
                variant="danger"
                :action="route('jobs.cancel', $job)"
                :confirm="$confirmCancel"
                :aria-label="__('jobs.actions.cancel_for', $context)"
            >
                {{ __('jobs.actions.cancel') }}
            </x-ui.action-form>
        @endcan
    </div>
@endcanany
