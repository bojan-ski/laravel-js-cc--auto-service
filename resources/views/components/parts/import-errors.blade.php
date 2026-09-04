@props(['errors' => null])

@php
    $messages = $errors?->all() ?? [];
@endphp

@if ($messages !== [])
    <section
        class="mt-4 border border-status-cancelled bg-status-cancelled/10 p-4"
        role="alert"
        aria-labelledby="import-errors-heading"
    >
        <h2 id="import-errors-heading" class="font-semibold text-text">
            {{ __('parts.import.errors.heading') }}
        </h2>

        <p class="mt-1 text-sm text-text">{{ __('parts.import.errors.intro') }}</p>

        <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-text">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </section>
@endif
