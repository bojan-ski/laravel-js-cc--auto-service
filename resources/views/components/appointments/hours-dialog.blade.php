@props(['hours'])

<dialog
    id="appointment-hours"
    aria-labelledby="appointment-hours-title"
    class="m-auto w-[calc(100%-2rem)] max-w-md border border-border-strong bg-surface p-0 text-text shadow-lg backdrop:bg-surface-dark/60"
    data-dialog
    @if ($errors->hasAny(['open_at', 'close_at'])) data-dialog-open-on-load @endif
>
    <div class="p-4">
        <h2 id="appointment-hours-title" class="text-lg font-semibold">{{ __('appointments.hours.title') }}</h2>

        <x-appointments.hours-form :hours="$hours" :in-dialog="true" class="mt-3" />
    </div>
</dialog>
