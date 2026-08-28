@props(['vehicle'])

@if ($vehicle->trashed())
    <span class="font-medium tabular-nums">{{ $vehicle->registration_plate }}</span>
    <span class="text-sm">({{ __('app.deleted') }})</span>
@else
    <a
        href="{{ route('vehicles.show', $vehicle) }}"
        class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
    >
        {{ $vehicle->registration_plate }}
    </a>
@endif
