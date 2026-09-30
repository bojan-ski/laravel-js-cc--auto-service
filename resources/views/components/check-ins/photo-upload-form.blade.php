@props(['job'])

@php
    $hasError = $errors->has('photo');
    $describedBy = trim(($hasError ? 'check-in-photo-error ' : '').'check-in-photo-hint');
@endphp

<form
    method="POST"
    action="{{ route('jobs.check-in.photos.store', $job) }}"
    enctype="multipart/form-data"
    class="mt-3 border-t border-border pt-3"
>
    @csrf

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <label for="check-in-photo" class="w-full text-sm font-medium @md:w-auto">
            {{ __('check_ins.photos.label') }}
        </label>
        <input
            id="check-in-photo"
            name="photo"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            aria-describedby="{{ $describedBy }}"
            @if ($hasError) aria-invalid="true" @endif
            class="block min-w-48 flex-1 rounded-sm border bg-surface px-3 py-2 text-sm text-text file:mr-3 file:rounded-sm file:border-0 file:bg-surface-muted file:px-3 file:py-1 file:text-sm file:font-medium file:text-text hover:file:bg-border
                {{ $hasError ? 'border-status-cancelled' : 'border-border focus:border-accent' }}"
        >

        <x-ui.button type="submit" :aria-label="__('check_ins.photos.upload_for', ['reference' => $job->reference])">
            {{ __('check_ins.photos.upload') }}
        </x-ui.button>
    </div>

    <p id="check-in-photo-hint" class="mt-1 text-xs text-text-muted">
        {{ __('check_ins.photos.hint') }}
    </p>

    @error('photo')
        <p id="check-in-photo-error" class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
    @enderror
</form>
