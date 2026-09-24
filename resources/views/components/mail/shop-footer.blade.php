<div style="padding-top: 16px; border-top: 1px solid #D6D3D1; color: #57534E;">
    @if (filled(config('shop.identity.name')))
        <div style="font-weight: bold; color: #44403C;">{{ config('shop.identity.name') }}</div>
    @endif

    @if (filled(config('shop.identity.address')))
        <div>{{ config('shop.identity.address') }}</div>
    @endif

    @if (filled(config('shop.identity.phone')))
        <div>{{ config('shop.identity.phone') }}</div>
    @endif

    @if (filled(config('shop.identity.tax_number')))
        <div>{{ config('shop.identity.tax_number') }}</div>
    @endif
</div>
