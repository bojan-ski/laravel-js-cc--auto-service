<div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-3 border border-border bg-surface p-3">
    <nav class="flex gap-1" aria-label="{{ __('appointments.index.view_label') }}">
        @foreach ($modes as $mode)
            <x-appointments.filter-link :item="$mode" />
        @endforeach
    </nav>

    <div class="flex items-center gap-2">
        <x-ui.button variant="secondary" :href="$previousUrl" :aria-label="__('appointments.index.previous')">
            <span aria-hidden="true">&lsaquo;</span>
        </x-ui.button>

        <span class="min-w-40 text-center text-sm font-medium tabular-nums">{{ $periodLabel }}</span>

        <x-ui.button variant="secondary" :href="$nextUrl" :aria-label="__('appointments.index.next')">
            <span aria-hidden="true">&rsaquo;</span>
        </x-ui.button>

        <x-ui.button variant="secondary" :href="$todayUrl">{{ __('appointments.index.today') }}</x-ui.button>
    </div>

    <nav class="flex flex-wrap gap-1" aria-label="{{ __('appointments.index.status_label') }}">
        @foreach ($statuses as $status)
            <x-appointments.filter-link :item="$status" />
        @endforeach
    </nav>
</div>
