@props(['customer'])

@php
    $rows = [
        'phone' => $customer->phone,
        'email' => $customer->email,
        'address' => $customer->address,
        'notes' => $customer->notes,
    ];
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('customers.show.contact') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        @foreach ($rows as $key => $value)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('customers.show.'.$key) }}
            </dt>

            <dd @class([
                'whitespace-pre-line',
                'tabular-nums' => $key === 'phone',
                'text-text-muted' => $value === null,
            ])>
                <x-ui.value :value="$value" />
            </dd>
        @endforeach
    </dl>
</section>
