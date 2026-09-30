@php
    $body = __('mail.estimate.body', [
        'make' => $vehicle->make,
        'model' => $vehicle->model,
        'plate' => $vehicle->registration_plate,
    ]);
@endphp

<x-mail.layout
    :title="__('mail.estimate.subject', ['number' => $estimate->number, 'plate' => $vehicle->registration_plate])"
    :heading="__('mail.estimate.heading', ['number' => $estimate->number])"
    :preheader="$body"
>
    <p style="margin: 0 0 12px;">{{ __('mail.estimate.greeting', ['name' => $customer->name]) }}</p>

    <p style="margin: 0 0 12px;">{{ $body }}</p>

    <p style="margin: 0;">{{ __('mail.estimate.reply') }}</p>

    <x-mail.vehicle :vehicle="$vehicle" />

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        <x-mail.row :label="__('mail.estimate.number')" :value="$estimate->number" />
        <x-mail.row :label="__('mail.estimate.created')" :value="$estimate->created_at->translatedFormat('j M Y')" />
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        @foreach ($lines as $line)
            <x-mail.row
                :label="$line['percent'] === null
                    ? __('mail.estimate.line', ['description' => $line['description'], 'quantity' => $line['quantity']])
                    : __('mail.estimate.line_discounted', ['description' => $line['description'], 'quantity' => $line['quantity'], 'percent' => $line['percent']])"
                :value="$line['total']"
            />
        @endforeach
    </table>

    <x-mail.totals :money="$money" />
</x-mail.layout>
