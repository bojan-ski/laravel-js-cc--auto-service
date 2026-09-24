@props(['today', 'appointments'])

<section class="border border-border bg-surface">
    <h2 class="flex items-baseline justify-between gap-2 border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
        <span>{{ __('dashboard.today.title') }}</span>
        <span class="font-medium tabular-nums normal-case tracking-normal">{{ $today->isoFormat('ddd D MMM') }}</span>
    </h2>

    @if ($appointments->isEmpty())
        <p class="px-3 py-4 text-sm text-text-muted">{{ __('dashboard.today.empty') }}</p>
    @else
        <ul>
            @foreach ($appointments as $appointment)
                @php
                    $arrived = $appointment->isArrived();
                    $missed = $appointment->isMissed();
                @endphp

                <li class="border-b border-border last:border-b-0">
                    <a
                        href="{{ route('appointments.show', $appointment) }}"
                        @class([
                            'flex items-baseline gap-2 px-3 py-1.5 text-sm hover:bg-surface-muted hover:text-brand-dark',
                            'text-text-muted' => $missed,
                        ])
                    >
                        <span class="font-medium tabular-nums">{{ $appointment->scheduled_at->isoFormat('HH:mm') }}</span>
                        <span class="tabular-nums underline underline-offset-2">{{ $appointment->vehicle->registration_plate }}</span>
                        <span class="min-w-0 truncate text-text-muted">{{ $appointment->vehicle->make }} {{ $appointment->vehicle->model }}</span>

                        @if ($arrived)
                            <span class="ms-auto font-semibold text-status-arrived" aria-hidden="true">&check;</span>
                            <span class="sr-only">{{ __('dashboard.today.arrived') }}</span>
                        @elseif ($missed)
                            <x-ui.status-badge class="ms-auto" :status="$appointment->status" />
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="border-t border-border px-3 py-2 text-end text-sm">
        <a
            href="{{ route('appointments.index', ['view' => 'day', 'date' => $today->format('Y-m-d')]) }}"
            class="font-medium underline underline-offset-2 hover:text-brand-dark"
        >{{ __('dashboard.today.view_all') }} <span aria-hidden="true">&rarr;</span></a>
    </p>
</section>
