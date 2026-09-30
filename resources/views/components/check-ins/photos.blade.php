@props(['job'])

<section {{ $attributes->class('@container border border-border bg-surface p-4') }}>
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('check_ins.photos.heading') }}</h2>

    @if ($job->checkInPhotos->isEmpty())
        <x-ui.empty-state class="mt-2" :title="__('check_ins.photos.empty')" :level="0" />
    @else
        <ul
            role="list"
            aria-label="{{ __('check_ins.photos.heading') }}"
            class="mt-3 grid grid-cols-1 gap-3 @sm:grid-cols-2 @lg:grid-cols-3 @3xl:grid-cols-4 @4xl:grid-cols-5"
        >
            @foreach ($job->checkInPhotos as $photo)
                <li>
                    <x-jobs.attachment-thumb :attachment="$photo" :job="$job" />
                </li>
            @endforeach
        </ul>
    @endif

    @can('recordCheckIn', $job)
        <x-check-ins.photo-upload-form :job="$job" />
    @endcan

    @if ($job->checkInPhotos->isNotEmpty())
        <x-jobs.lightbox />
    @endif
</section>
