@props(['job'])

@php
    $hasError = $errors->has('file');
    $describedBy = trim(($hasError ? 'attachment-file-error ' : '').'attachment-file-hint');
@endphp

<form
    method="POST"
    action="{{ route('jobs.attachments.store', $job) }}"
    enctype="multipart/form-data"
    class="mt-3 border-t border-border pt-3"
>
    @csrf

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <label for="attachment-file" class="w-full text-sm font-medium @md:w-auto">
            {{ __('jobs.attachments.upload.label') }}
        </label>
        <input
            id="attachment-file"
            name="file"
            type="file"
            accept="image/jpeg,image/png,image/webp,application/pdf"
            aria-describedby="{{ $describedBy }}"
            @if ($hasError) aria-invalid="true" @endif
            class="block min-w-48 flex-1 rounded-sm border bg-surface px-3 py-2 text-sm text-text file:mr-3 file:rounded-sm file:border-0 file:bg-surface-muted file:px-3 file:py-1 file:text-sm file:font-medium file:text-text hover:file:bg-border
                {{ $hasError ? 'border-status-cancelled' : 'border-border focus:border-accent' }}"
        >

        <x-ui.button type="submit" :aria-label="__('jobs.attachments.upload.action_for', ['reference' => $job->reference])">
            {{ __('jobs.attachments.upload.action') }}
        </x-ui.button>
    </div>

    <p id="attachment-file-hint" class="mt-1 text-xs text-text-muted">
        {{ __('jobs.attachments.upload.hint') }}
    </p>

    @error('file')
        <p id="attachment-file-error" class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
    @enderror
</form>
