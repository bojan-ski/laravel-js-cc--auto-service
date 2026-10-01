{{ __('mail.service_reminder.greeting', ['name' => $customer->name]) }}

{{ $body }}

@foreach ($rows as $row)
{{ __('mail.pair', ['label' => $row['label'], 'value' => $row['value']]) }}
@endforeach

{{ __('mail.service_reminder.book') }}

{{ __('mail.service_reminder.opt_out') }}

<x-mail.shop-footer-text />
