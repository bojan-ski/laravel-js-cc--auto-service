@props(['customer'])

<tr role="row" class="grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 font-medium md:table-cell md:px-3 md:py-2">
        {{ $customer->name }}
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm tabular-nums text-text-muted md:table-cell md:px-3 md:py-2 md:text-base md:text-text">
        {{ $customer->phone }}
    </td>

    <td role="cell" @class([
        'hidden xl:table-cell xl:px-3 xl:py-2',
        'text-text-muted' => $customer->email === null,
    ])>
        <x-ui.value :value="$customer->email" />
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2">
        <x-ui.button
            variant="secondary"
            :href="route('customers.show', $customer)"
            :aria-label="__('customers.index.view_customer', ['name' => $customer->name])"
        >
            {{ __('app.actions.view') }}
        </x-ui.button>
    </td>
</tr>
