@props(['customers'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('customers.index.count', $customers->total(), ['count' => $customers->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface">
    <table role="table" class="w-full text-left">
        <x-customers.table-head />

        <tbody role="rowgroup">
            @foreach ($customers as $customer)
                <x-customers.row :customer="$customer" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $customers->links() }}</div>
