<form
    method="POST"
    action="{{ route('invoices.paid', $invoice) }}"
    data-confirm="{{ __('invoices.show.confirm_paid', ['number' => $invoice->number]) }}"
    class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
>
    @csrf
    @method('PATCH')

    <x-forms.select
        name="payment_method"
        :label="__('invoices.show.payment_method')"
        :options="$methods"
        :placeholder="__('invoices.show.payment_method_placeholder')"
        class="sm:w-48"
        required
    />

    <x-ui.button
        type="submit"
        :aria-label="__('invoices.show.mark_paid_for', ['number' => $invoice->number])"
    >
        {{ __('invoices.show.mark_paid') }}
    </x-ui.button>
</form>
