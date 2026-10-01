@props(['vehicle' => null])

<x-forms.field
    name="next_service_on"
    type="date"
    :label="__('vehicles.form.next_service_on')"
    :value="$vehicle?->next_service_on?->format('Y-m-d')"
    class="tabular-nums"
/>

<x-forms.field
    name="next_service_mileage"
    type="number"
    :label="__('vehicles.form.next_service_mileage')"
    :value="$vehicle?->next_service_mileage"
    min="0"
    class="tabular-nums"
/>

<x-forms.field
    name="registration_expires_on"
    type="date"
    :label="__('vehicles.form.registration_expires_on')"
    :value="$vehicle?->registration_expires_on?->format('Y-m-d')"
    class="tabular-nums"
/>
