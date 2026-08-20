@props(['customer'])

@if ($customer->trashed())
    <span>
        {{ $customer->name }}
        <span class="text-sm text-text-muted">({{ __('vehicles.show.owner_deleted') }})</span>
    </span>
@else
    <a href="{{ route('customers.show', $customer) }}" class="font-medium underline underline-offset-2 hover:text-brand-dark">
        {{ $customer->name }}
    </a>
@endif
