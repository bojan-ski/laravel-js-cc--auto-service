@php
    $body = __('mail.job_completed.body', [
        'make' => $vehicle->make,
        'model' => $vehicle->model,
        'plate' => $vehicle->registration_plate,
    ]);
@endphp

<x-mail.layout
    :title="__('mail.job_completed.subject', ['plate' => $vehicle->registration_plate])"
    :heading="__('mail.job_completed.heading')"
    :preheader="$body"
>
    <p style="margin: 0 0 12px;">{{ __('mail.job_completed.greeting', ['name' => $customer->name]) }}</p>

    <p style="margin: 0;">{{ $body }}</p>

    <x-mail.vehicle :vehicle="$vehicle" />

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
        <x-mail.row :label="__('mail.job_completed.reference')" :value="$job->reference" />
    </table>
</x-mail.layout>
