@props(['title', 'heading', 'preheader'])

@php
    $font = "-apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif";
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F5F5F4;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F5F5F4;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #FFFFFF; border: 1px solid #D6D3D1;">
                    <tr>
                        <td style="background-color: #1C1917; border-bottom: 4px solid #EA580C; padding: 16px 24px; font-family: {{ $font }}; font-size: 14px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #FAFAF9;">
                            {{ config('shop.identity.name') }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 28px 24px 8px; font-family: {{ $font }}; font-size: 15px; line-height: 1.55; color: #1C1917;">
                            <h1 style="margin: 0 0 16px; font-size: 22px; line-height: 1.3; font-weight: bold; color: #1C1917;">{{ $heading }}</h1>

                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 8px 24px 24px; font-family: {{ $font }}; font-size: 13px; line-height: 1.5;">
                            <x-mail.shop-footer />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
