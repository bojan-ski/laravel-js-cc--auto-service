@props([
    'vehicle',
    'customers',
])

<form
    method="POST"
    action="{{ route('vehicles.owner.update', $vehicle) }}"
    class="mt-4 max-w-2xl space-y-4"
    data-confirm="{{ __('vehicles.reassign.confirm', [
        'plate' => $vehicle->registration_plate,
        'name' => $vehicle->customer->name,
    ]) }}"
    data-confirm-source="customer_id"
>
    @csrf
    @method('PATCH')

    <div class="border border-border bg-surface-muted px-3 py-2">
        <span class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('vehicles.reassign.current_owner') }}</span>
        <span class="ms-2 font-medium">{{ $vehicle->customer->name }}</span>
    </div>

    <x-forms.select
        name="customer_id"
        :label="__('vehicles.reassign.new_owner')"
        :options="$customers->pluck('name', 'id')"
        :placeholder="__('vehicles.reassign.placeholder')"
        required
        autofocus
    />

    <div class="border-s-4 border-status-awaiting-parts bg-surface p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('vehicles.reassign.warning_title') }}</p>

        <p class="mt-1 text-sm text-text">
            {{ __('vehicles.reassign.warning', ['name' => $vehicle->customer->name]) }}
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ __('vehicles.reassign.submit') }}</x-ui.button>

        <x-ui.button variant="secondary" :href="route('vehicles.show', $vehicle)">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
