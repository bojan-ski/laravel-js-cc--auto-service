@props(['vehicle'])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; background-color: #EFEDEC; border: 1px solid #D6D3D1;">
    <tr>
        <td style="padding: 14px 16px;">
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #57534E;">{{ __('mail.vehicle') }}</div>
            <div style="margin-top: 4px; font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #1C1917;">{{ $vehicle->registration_plate }}</div>
            <div style="color: #44403C;">{{ $vehicle->make }} {{ $vehicle->model }}</div>
        </td>
    </tr>
</table>
