@props(['job'])

@php
    $context = [
        'reference' => $job->reference,
        'plate' => $job->vehicle->registration_plate,
    ];
@endphp

<x-jobs.lock-banner :job="$job" />

@canany(['start', 'pause', 'resume', 'complete', 'cancel', 'reopen'], $job)
    <div class="mt-4 flex flex-wrap gap-2">
        @can('start', $job)
            <form method="POST" action="{{ route('jobs.start', $job) }}">
                @csrf
                @method('PATCH')

                <x-ui.button variant="secondary" :aria-label="__('jobs.actions.start_for', $context)">
                    {{ __('jobs.actions.start') }}
                </x-ui.button>
            </form>
        @endcan

        @can('pause', $job)
            <form method="POST" action="{{ route('jobs.pause', $job) }}">
                @csrf
                @method('PATCH')

                <x-ui.button variant="secondary" :aria-label="__('jobs.actions.pause_for', $context)">
                    {{ __('jobs.actions.pause') }}
                </x-ui.button>
            </form>
        @endcan

        @can('resume', $job)
            <form method="POST" action="{{ route('jobs.resume', $job) }}">
                @csrf
                @method('PATCH')

                <x-ui.button variant="secondary" :aria-label="__('jobs.actions.resume_for', $context)">
                    {{ __('jobs.actions.resume') }}
                </x-ui.button>
            </form>
        @endcan

        @can('complete', $job)
            <form method="POST" action="{{ route('jobs.complete', $job) }}">
                @csrf
                @method('PATCH')

                <x-ui.button variant="secondary" :aria-label="__('jobs.actions.complete_for', $context)">
                    {{ __('jobs.actions.complete') }}
                </x-ui.button>
            </form>
        @endcan

        @can('reopen', $job)
            <form method="POST" action="{{ route('jobs.reopen', $job) }}">
                @csrf
                @method('PATCH')

                <x-ui.button variant="secondary" :aria-label="__('jobs.actions.reopen_for', $context)">
                    {{ __('jobs.actions.reopen') }}
                </x-ui.button>
            </form>
        @endcan

        @can('cancel', $job)
            <form
                method="POST"
                action="{{ route('jobs.cancel', $job) }}"
                data-confirm="{{ __('jobs.actions.confirm_cancel', $context) }}"
            >
                @csrf
                @method('PATCH')

                <x-ui.button variant="danger" :aria-label="__('jobs.actions.cancel_for', $context)">
                    {{ __('jobs.actions.cancel') }}
                </x-ui.button>
            </form>
        @endcan
    </div>
@endcanany
