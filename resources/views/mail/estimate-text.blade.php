{{ __('mail.estimate.greeting', ['name' => $customer->name]) }}

{{ __('mail.estimate.body', ['make' => $vehicle->make, 'model' => $vehicle->model, 'plate' => $vehicle->registration_plate]) }}
{{ __('mail.estimate.reply') }}

{{ __('mail.pair', ['label' => __('mail.estimate.number'), 'value' => $estimate->number]) }}
{{ __('mail.pair', ['label' => __('mail.estimate.created'), 'value' => $estimate->created_at->translatedFormat('j M Y')]) }}

@foreach ($lines as $line)
{{ __('mail.pair', ['label' => $line['percent'] === null ? __('mail.estimate.line', ['description' => $line['description'], 'quantity' => $line['quantity']]) : __('mail.estimate.line_discounted', ['description' => $line['description'], 'quantity' => $line['quantity'], 'percent' => $line['percent']]), 'value' => $line['total']]) }}
@endforeach

<x-mail.totals-text :money="$money" />

<x-mail.shop-footer-text />
