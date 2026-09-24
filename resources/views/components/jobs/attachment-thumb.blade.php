@props(['attachment', 'job'])

@php
    $uploader = $attachment->uploadedBy->shortName();
    $uploadedOn = $attachment->created_at->format('j M Y');
    $alt = __('jobs.attachments.photo_alt', ['reference' => $job->reference, 'name' => $uploader]);
    $openLabel = __('jobs.attachments.open_photo', [
        'reference' => $job->reference,
        'name' => $uploader,
        'date' => $uploadedOn,
    ]);
@endphp

<figure class="flex h-full flex-col border border-border bg-surface">
    <a
        href="{{ route('attachments.show', ['attachment' => $attachment, 'variant' => 'full']) }}"
        data-lightbox-trigger
        data-lightbox-alt="{{ $alt }}"
        @can('delete', $attachment)
            data-lightbox-delete-action="{{ route('attachments.destroy', $attachment) }}"
            data-lightbox-delete-confirm="{{ __('jobs.attachments.delete.confirm', ['filename' => $attachment->original_filename]) }}"
            data-lightbox-delete-label="{{ __('jobs.attachments.delete.action_for', ['filename' => $attachment->original_filename]) }}"
        @endcan
        aria-label="{{ $openLabel }}"
        class="block aspect-4/3 shrink-0 overflow-hidden focus:outline-2 focus:outline-offset-2 focus:outline-accent"
    >
        <img
            src="{{ route('attachments.show', ['attachment' => $attachment, 'variant' => 'thumb']) }}"
            alt="{{ $alt }}"
            class="size-full object-cover"
        >
    </a>

    <figcaption class="flex-1 px-2 py-1.5 text-xs text-text-muted">
        {{ __('jobs.attachments.uploaded_by', [
            'name' => $uploader,
            'date' => $uploadedOn,
        ]) }}

        @if ($attachment->uploadedBy->trashed())
            ({{ __('app.deleted') }})
        @endif
    </figcaption>
</figure>
