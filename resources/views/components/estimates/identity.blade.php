@props(['estimate'])

@php
    $customer = $estimate->job->customer;
    $vehicle = $estimate->job->vehicle;
@endphp

<div class="hidden print:block">
    <div class="flex items-start justify-between gap-6">
        <div>
            <p class="text-lg font-bold">{{ config('shop.identity.name') }}</p>
            <p>{{ config('shop.identity.address') }}</p>
            <p class="tabular-nums">{{ config('shop.identity.tax_number') }}</p>
        </div>

        <div class="text-end">
            <p class="font-semibold tabular-nums">
                {{ __('estimates.identity.heading', ['number' => $estimate->number]) }}
            </p>

            <p class="tabular-nums">
                {{ __('estimates.identity.created', ['date' => $estimate->created_at->isoFormat('D MMM YYYY')]) }}
            </p>
        </div>
    </div>

    <div class="mt-6">
        <p class="text-xs font-medium uppercase tracking-wider">{{ __('estimates.identity.prepared_for') }}</p>

        <p class="font-medium">{{ $customer->name }}</p>

        @if (filled($customer->address))
            <p>{{ $customer->address }}</p>
        @endif

        <p class="mt-2">
            <span class="tabular-nums">{{ $vehicle->registration_plate }}</span>
            <span aria-hidden="true">&middot;</span>
            {{ $vehicle->make }} {{ $vehicle->model }}
        </p>
    </div>
</div>
