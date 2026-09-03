@if (filled(config('shop.identity.name')))
{{ config('shop.identity.name') }}
@endif
@if (filled(config('shop.identity.address')))
{{ config('shop.identity.address') }}
@endif
@if (filled(config('shop.identity.phone')))
{{ config('shop.identity.phone') }}
@endif
@if (filled(config('shop.identity.tax_number')))
{{ config('shop.identity.tax_number') }}
@endif
