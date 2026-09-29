@props(['label', 'amount'])

<tr role="row">
    <td role="cell" class="hidden md:table-cell"></td>

    <th role="rowheader" scope="row" colspan="3" class="px-3 py-2 text-end text-xs font-medium uppercase tracking-wider">
        {{ $label }}
    </th>

    <td role="cell" class="hidden md:table-cell"></td>

    <td role="cell" class="px-3 py-2 text-end font-medium">
        <x-ui.money :value="$amount" />
    </td>

    <td role="cell" colspan="2"></td>
</tr>
