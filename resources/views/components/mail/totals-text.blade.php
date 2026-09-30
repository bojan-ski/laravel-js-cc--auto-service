@props(['money'])
@if ($money['has_discount'])
{{ __('mail.pair', ['label' => __('mail.totals.before_discount'), 'value' => $money['before_discount']]) }}
{{ __('mail.pair', ['label' => __('mail.totals.discount'), 'value' => $money['discount']]) }}
@endif
{{ __('mail.pair', ['label' => __('mail.totals.subtotal'), 'value' => $money['subtotal']]) }}
{{ __('mail.pair', ['label' => __('mail.totals.vat', ['rate' => $money['rate']]), 'value' => $money['tax']]) }}
{{ __('mail.pair', ['label' => __('mail.totals.total'), 'value' => $money['total']]) }}
