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

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ __('parts.adjust.submit') }}</x-ui.button>

        <x-ui.button variant="secondary" :href="route('parts.show', $part)">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
