@props(['job'])

@php
    $checkIn = $job->checkIn;
@endphp

<div role="note" class="mt-3 border border-border bg-surface-muted p-3">
    <p class="font-medium">
        {{ __('check_ins.sign.signed', [
            'name' => $checkIn->signed_by_name,
            'date' => $checkIn->signed_at->isoFormat('D MMM YYYY HH:mm'),
        ]) }}
    </p>

    <p class="text-sm text-text-soft">{{ $checkIn->signature_method->label() }}</p>

    <p class="mt-1 text-sm text-text-soft">{{ __('check_ins.sign.read_only') }}</p>

    @if ($checkIn->hasDrawnSignature())
        <div class="mt-3 aspect-3/1 w-full max-w-xl overflow-hidden border border-border">
            <img
                src="{{ route('jobs.check-in.signature', $job) }}"
                alt="{{ __('check_ins.sign.image_alt', ['name' => $checkIn->signed_by_name]) }}"
                class="size-full object-contain"
            >
        </div>
    @endif
</div>
