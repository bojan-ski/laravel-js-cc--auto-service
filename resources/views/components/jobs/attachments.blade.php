@props(['job', 'photos', 'documents'])

<section class="@container border border-border bg-surface p-4">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">
        {{ __('jobs.show.attachments') }}
    </h2>

    @if ($photos->isEmpty() && $documents->isEmpty())
        <x-ui.empty-state class="mt-2" :title="__('jobs.show.attachments_empty')" :level="0" />
    @endif

    @if ($photos->isNotEmpty())
        <ul
            role="list"
            aria-label="{{ __('jobs.attachments.photos_label') }}"
            class="mt-3 grid grid-cols-1 gap-3 @sm:grid-cols-2 @lg:grid-cols-3 @3xl:grid-cols-4 @4xl:grid-cols-5"
        >
            @foreach ($photos as $photo)
                <li>
                    <x-jobs.attachment-thumb :attachment="$photo" :job="$job" />
                </li>
            @endforeach
        </ul>
    @endif

    @if ($documents->isNotEmpty())
        <ul role="list" aria-label="{{ __('jobs.attachments.documents_label') }}" class="mt-3">
            @foreach ($documents as $document)
                <x-jobs.attachment-row :attachment="$document" />
            @endforeach
        </ul>
    @endif

    @can('upload', $job)
        <x-jobs.attachment-upload-form :job="$job" />
    @endcan

    @if ($photos->isNotEmpty())
        <x-jobs.lightbox />
    @endif
</section>
