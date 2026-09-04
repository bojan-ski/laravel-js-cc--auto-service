@php
    $supplierNote = $errors->has('supplier_name') ? null : 'supplier-note';
@endphp

<form method="POST" action="{{ route('parts.adjust.store', $part) }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    <div class="border border-border bg-surface-muted px-3 py-2">
        <p class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('parts.adjust.current') }}</p>
        <x-parts.stock class="mt-1" :part="$part" />
    </div>

    <x-forms.radio-group
        name="reason"
        :label="__('parts.adjust.reason_label')"
        :options="$reasons"
        required
    />

    <x-forms.field
        name="quantity_change"
        type="number"
        step="1"
        inputmode="numeric"
        :label="__('parts.adjust.quantity')"
        class="tabular-nums"
        required
    />

    <x-forms.field name="note" :label="__('parts.adjust.note')" maxlength="255" />

    <x-forms.field
        name="supplier_name"
        :label="__('parts.adjust.supplier')"
        maxlength="120"
        list="known-suppliers"
        :aria-describedby="$supplierNote"
    />

    <datalist id="known-suppliers">
        @foreach ($suppliers as $supplier)
            <option value="{{ $supplier }}"></option>
        @endforeach
    </datalist>

    <p id="supplier-note" class="text-sm text-text-soft">{{ __('parts.adjust.supplier_note') }}</p>

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ __('parts.adjust.submit') }}</x-ui.button>

        <x-ui.button variant="secondary" :href="route('parts.show', $part)">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
