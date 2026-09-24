@props(['customer'])

<tr role="row" class="relative grid grid-cols-1 items-center gap-x-3 border-b border-border p-3 last:border-b-0 hover:bg-surface-muted md:table-row md:p-0">
    <td role="cell" class="font-medium md:table-cell md:px-3 md:py-2">
        <a
            href="{{ route('customers.show', $customer) }}"
            class="underline underline-offset-2 after:absolute after:inset-0 hover:text-brand-dark"
        >{{ $customer->name }}</a>
    </td>

    <td role="cell" class="text-sm tabular-nums text-text-muted md:table-cell md:px-3 md:py-2 md:text-base md:text-text">
        {{ $customer->phone }}
    </td>

    <td role="cell" @class([
        'hidden xl:table-cell xl:px-3 xl:py-2',
        'text-text-muted' => $customer->email === null,
    ])>
        <x-ui.value :value="$customer->email" />
    </td>
</tr>
