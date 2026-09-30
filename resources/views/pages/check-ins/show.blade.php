<x-layouts.app :title="__('check_ins.show.title', ['reference' => $job->reference])">
    <x-check-ins.header :job="$job" />

    @can('recordCheckIn', $job)
        <x-check-ins.sheet-form :job="$job" class="mt-4" />
    @elseif ($job->checkIn)
        <x-check-ins.facts :check-in="$job->checkIn" class="mt-4" />
    @else
        <x-ui.empty-state class="mt-4" :title="__('check_ins.show.none')" />
    @endcan

    @if ($job->checkIn)
        <x-check-ins.photos :job="$job" class="mt-4" />

        <x-check-ins.signature :job="$job" class="mt-4" />
    @endif
</x-layouts.app>
