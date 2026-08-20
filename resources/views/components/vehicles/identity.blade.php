@props(['vehicle'])

@php
    $rows = [
        'year' => $vehicle->year,
        'engine' => $vehicle->engine,
        'vin' => $vehicle->vin,
        'mileage' => $vehicle->mileage === null
            ? null
            : __('vehicles.show.mileage_value', ['mileage' => number_format($vehicle->mileage)]),
        'notes' => $vehicle->notes,
    ];
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('vehicles.show.details') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('vehicles.show.owner') }}
        </dt>

        <dd>
            <x-vehicles.owner :customer="$vehicle->customer" />
        </dd>

        @foreach ($rows as $key => $value)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('vehicles.show.'.$key) }}
            </dt>

            <dd @class([
                'whitespace-pre-line',
                'tabular-nums' => in_array($key, ['year', 'vin', 'mileage'], true),
                'text-text-muted' => $value === null,
            ])>
                <x-ui.value :value="$value" />
            </dd>
        @endforeach
    </dl>
</section>
