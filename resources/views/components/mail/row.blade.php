@props(['label', 'value', 'total' => false])

@if ($total)
    <tr>
        <td style="padding: 10px 0 0; border-top: 2px solid #1C1917; font-size: 17px; font-weight: bold; color: #1C1917;">{{ $label }}</td>
        <td align="right" style="padding: 10px 0 0; border-top: 2px solid #1C1917; font-size: 17px; font-weight: bold; color: #1C1917; white-space: nowrap; font-variant-numeric: tabular-nums;">{{ $value }}</td>
    </tr>
@else
    <tr>
        <td style="padding: 6px 0; border-top: 1px solid #D6D3D1; color: #57534E;">{{ $label }}</td>
        <td align="right" style="padding: 6px 0; border-top: 1px solid #D6D3D1; color: #1C1917; white-space: nowrap; font-variant-numeric: tabular-nums;">{{ $value }}</td>
    </tr>
@endif
