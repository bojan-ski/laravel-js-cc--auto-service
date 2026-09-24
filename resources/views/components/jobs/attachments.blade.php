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
        <dialog
            data-lightbox-dialog
            aria-label="{{ __('jobs.attachments.lightbox.label') }}"
            class="m-auto max-w-[90vw] bg-transparent p-0 backdrop:bg-surface-dark/80 dark:backdrop:bg-background/90"
        >
            <div class="border border-border-strong bg-surface p-2">
                <img data-lightbox-image src="" alt="" class="max-h-[80vh] max-w-full object-contain">

                <div class="mt-2 flex justify-end gap-2">
                    <form method="POST" action="" data-lightbox-delete data-confirm="" hidden class="mr-auto">
                        @csrf
                        @method('DELETE')

                        <x-ui.button type="submit" variant="danger">
                            {{ __('jobs.attachments.delete.action') }}
                        </x-ui.button>
                    </form>

                    <x-ui.button type="button" variant="secondary" data-lightbox-close>
                        {{ __('jobs.attachments.lightbox.close') }}
                    </x-ui.button>
                </div>
            </div>
        </dialog>
    @endif
</section>
