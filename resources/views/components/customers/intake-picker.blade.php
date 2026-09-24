<fieldset
    class="space-y-4"
    data-customer-picker
    data-customer-picker-url="{{ route('customers.lookup') }}"
    data-customer-picker-picked="{{ json_encode($picked) }}"
    data-customer-picker-vehicle="{{ old('vehicle_id') }}"
    @if (filled(old('new_vehicle_plate'))) data-customer-picker-new-vehicle-chosen @endif
>
    <legend class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('customers.intake.legend') }}</legend>

    <div class="relative" data-customer-picker-search hidden>
        <x-forms.field
            name="customer_search"
            type="search"
            :label="__('customers.intake.search_label')"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="customer-search-results"
            aria-describedby="customer-search-hint"
            data-customer-picker-input
        />

        <p id="customer-search-hint" class="mt-1 text-sm text-text-muted">{{ __('customers.intake.search_hint') }}</p>

        <ul
            id="customer-search-results"
            role="listbox"
            aria-label="{{ __('customers.intake.search_label') }}"
            class="absolute inset-x-0 z-10 mt-1 max-h-72 overflow-y-auto border border-border-strong bg-surface shadow-md"
            hidden
            data-customer-picker-results
        ></ul>

        <p
            class="sr-only"
            role="status"
            aria-live="polite"
            data-customer-picker-status
            data-one="{{ trans_choice('customers.intake.results', 1, ['count' => ':count']) }}"
            data-other="{{ trans_choice('customers.intake.results', 2, ['count' => ':count']) }}"
            data-none="{{ __('customers.intake.results_none') }}"
        ></p>

        @error('customer_id')
            <p class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
        @enderror
    </div>

    <template data-customer-picker-option-template>
        <li role="option" aria-selected="false" class="cursor-pointer border-b border-border px-3 py-2 last:border-b-0 hover:bg-surface-muted aria-selected:bg-accent-soft">
            <span class="block font-medium" data-name></span>
            <span class="block text-sm tabular-nums text-text-muted" data-meta></span>
        </li>
    </template>

    <input type="hidden" name="customer_id" value="" data-customer-picker-id>

    <div class="flex flex-wrap items-start justify-between gap-3 border border-border bg-surface-muted p-3" hidden data-customer-picker-card>
        <div>
            <p class="text-xs font-medium uppercase tracking-wider text-text-soft">{{ __('customers.intake.picked') }}</p>
            <p class="font-semibold" data-customer-picker-card-name></p>
            <p class="text-sm tabular-nums text-text-soft" data-customer-picker-card-contact></p>
        </div>

        <x-ui.button type="button" variant="secondary" data-customer-picker-change>{{ __('customers.intake.change') }}</x-ui.button>
    </div>

    <fieldset class="space-y-3" data-customer-picker-new-customer>
        <legend class="text-sm font-semibold">{{ __('customers.intake.new_customer') }}</legend>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-forms.field name="new_customer_name" :label="__('customers.form.name')" autocomplete="off" data-customer-picker-name />
            <x-forms.field name="new_customer_phone" type="tel" :label="__('customers.form.phone')" autocomplete="off" data-customer-picker-phone />
            <x-forms.field name="new_customer_email" type="email" :label="__('customers.form.email')" autocomplete="off" />
        </div>
    </fieldset>

    <div data-customer-picker-fallback>
        <x-forms.select
            name="vehicle_id"
            :label="__('customers.intake.existing_vehicle')"
            :options="$vehicleOptions"
            :placeholder="__('customers.intake.existing_vehicle_placeholder')"
        />
    </div>

    <fieldset hidden data-customer-picker-vehicles>
        <legend class="block text-sm font-medium">{{ __('customers.intake.vehicle_legend') }}</legend>

        <div class="mt-1 grid gap-2 sm:grid-cols-2" data-customer-picker-vehicle-list></div>

        @error('vehicle_id')
            <p class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
        @enderror
    </fieldset>

    <template data-customer-picker-vehicle-template>
        <label class="flex cursor-pointer items-center gap-2 border border-border bg-surface px-3 py-2 has-checked:border-accent has-checked:bg-accent-soft">
            <input type="radio" name="vehicle_id" required class="size-4 accent-brand">
            <span>
                <span class="font-medium tabular-nums" data-plate></span>
                <span class="text-text-muted" data-label></span>
            </span>
        </label>
    </template>

    <template data-customer-picker-new-vehicle-template>
        <label class="flex cursor-pointer items-center gap-2 border border-dashed border-border-strong bg-surface px-3 py-2 has-checked:border-accent has-checked:bg-accent-soft">
            <input type="radio" name="vehicle_id" value="" required class="size-4 accent-brand">
            <span class="font-medium">{{ __('customers.intake.new_vehicle_option') }}</span>
        </label>
    </template>

    <fieldset class="space-y-3 disabled:opacity-60" data-customer-picker-new-vehicle>
        <legend class="text-sm font-semibold">{{ __('customers.intake.new_vehicle') }}</legend>

        <p class="text-sm text-text-muted" hidden data-customer-picker-locked>{{ __('customers.intake.locked_hint') }}</p>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-forms.field name="new_vehicle_plate" :label="__('vehicles.form.registration_plate')" autocomplete="off" />
            <x-forms.field name="new_vehicle_make" :label="__('vehicles.form.make')" autocomplete="off" />
            <x-forms.field name="new_vehicle_model" :label="__('vehicles.form.model')" autocomplete="off" />
            <x-forms.field name="new_vehicle_year" type="number" :label="__('vehicles.form.year')" min="1900" inputmode="numeric" />
        </div>
    </fieldset>
</fieldset>
