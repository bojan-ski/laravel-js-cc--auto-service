@props(['groups', 'appointments', 'filters'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('appointments.index.count', $appointments->total(), ['count' => $appointments->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
    <table role="table" class="w-full text-left">
        <x-appointments.table-head />

        @foreach ($groups as $group)
            <tbody role="rowgroup">
                @if ($filters['view'] === 'week')
                    <x-appointments.day-heading :date="$group['date']" />
                @endif

                @forelse ($group['appointments'] as $appointment)
                    <x-appointments.row :appointment="$appointment" />
                @empty
                    <x-appointments.empty-day />
                @endforelse
            </tbody>
        @endforeach
    </table>
</div>

<div class="mt-4">{{ $appointments->links() }}</div>
