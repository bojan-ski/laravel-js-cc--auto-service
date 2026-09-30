@props(['job'])

<section {{ $attributes->class('border border-border bg-surface p-4') }}>
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('check_ins.sign.heading') }}</h2>

    @if ($job->checkIn->isSigned())
        <x-check-ins.signature-record :job="$job" />
    @else
        @can('signCheckIn', $job)
            <x-check-ins.signature-form :job="$job" />
        @else
            <p class="mt-2 text-sm text-text-muted">{{ __('check_ins.sign.empty') }}</p>
        @endcan
    @endif
</section>
