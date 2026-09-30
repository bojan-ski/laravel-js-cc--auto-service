{{ __('mail.estimate.greeting', ['name' => $customer->name]) }}

{{ __('mail.estimate.body', ['make' => $vehicle->make, 'model' => $vehicle->model, 'plate' => $vehicle->registration_plate]) }}
{{ __('mail.estimate.reply') }}

{{ __('mail.pair', ['label' => __('mail.estimate.number'), 'value' => $estimate->number]) }}
{{ __('mail.pair', ['label' => __('mail.estimate.created'), 'value' => $estimate->created_at->translatedFormat('j M Y')]) }}

@foreach ($lines as $line)
{{ __('mail.pair', ['label' => $line['percent'] === null ? __('mail.estimate.line', ['description' => $line['description'], 'quantity' => $line['quantity']]) : __('mail.estimate.line_discounted', ['description' => $line['description'], 'quantity' => $line['quantity'], 'percent' => $line['percent']]), 'value' => $line['total']]) }}
@endforeach

@if ($money['has_discount'])
{{ __('mail.pair', ['label' => __('mail.estimate.before_discount'), 'value' => $money['before_discount']]) }}
{{ __('mail.pair', ['label' => __('mail.estimate.discount'), 'value' => $money['discount']]) }}
@endif
{{ __('mail.pair', ['label' => __('mail.estimate.subtotal'), 'value' => $money['subtotal']]) }}
{{ __('mail.pair', ['label' => __('mail.estimate.vat', ['rate' => $money['rate']]), 'value' => $money['tax']]) }}
{{ __('mail.pair', ['label' => __('mail.estimate.total'), 'value' => $money['total']]) }}

<x-mail.shop-footer-text />
