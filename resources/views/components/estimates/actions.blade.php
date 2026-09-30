@props(['estimate', 'canEmail'])

<div class="mt-4 flex flex-wrap items-end gap-2 print:hidden">
    <h2 class="sr-only">{{ __('estimates.show.actions') }}</h2>

    @if ($canEmail)
        <x-ui.action-form
            :action="route('estimates.email.store', $estimate)"
            method="POST"
            :aria-label="__('estimates.email.action_for', ['number' => $estimate->number])"
        >
            {{ __('estimates.email.action') }}
        </x-ui.action-form>
    @endif

    <span data-print-slot data-print-label="{{ __('estimates.show.print') }}"></span>

    <x-ui.button variant="secondary" :href="route('jobs.show', $estimate->job)">
        {{ __('estimates.show.back') }}
    </x-ui.button>
</div>

@can('decide', $estimate)
    <x-estimates.decision-form :estimate="$estimate" />
@endcan
