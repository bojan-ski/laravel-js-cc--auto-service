<div style="margin-top: 24px; padding-top: 12px; border-top: 1px solid #D6D3D1; color: #44403C;">
    @if (filled(config('shop.identity.name')))
        <div style="font-weight: bold;">{{ config('shop.identity.name') }}</div>
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
