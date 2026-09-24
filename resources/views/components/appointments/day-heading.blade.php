@props(['date'])

<tr role="row" class="border-b border-border bg-surface-muted">
    <th role="rowheader" scope="rowgroup" colspan="4" class="px-3 py-2 text-start text-sm font-semibold uppercase tracking-wider">
        {{ $date->isoFormat('ddd D MMM') }}
    </th>
</tr>
