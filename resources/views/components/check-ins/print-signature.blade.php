@props(['job'])

@php
    $checkIn = $job->checkIn;
    $lines = [
        __('check_ins.print.signature_line'),
        __('check_ins.print.name_line'),
        __('check_ins.print.date_line'),
    ];
@endphp

<section class="mt-6 print:break-inside-avoid">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('check_ins.sign.heading') }}</h2>

    <p class="mt-2">{{ __('check_ins.print.declaration') }}</p>

    @if ($checkIn->hasDrawnSignature())
        <div class="mt-4 aspect-3/1 w-72 border-b border-border-strong">
            <img
                src="{{ route('jobs.check-in.signature', $job) }}"
                alt="{{ __('check_ins.sign.image_alt', ['name' => $checkIn->signed_by_name]) }}"
                class="size-full object-contain"
            >
        </div>

        <p class="mt-1">
            {{ __('check_ins.sign.signed', [
                'name' => $checkIn->signed_by_name,
                'date' => $checkIn->signed_at->isoFormat('D MMM YYYY HH:mm'),
            ]) }}
        </p>
    @elseif ($checkIn->isSigned())
        <p class="mt-4 font-medium">
            {{ __('check_ins.print.signed_on_paper', [
                'name' => $checkIn->signed_by_name,
                'date' => $checkIn->signed_at->isoFormat('D MMM YYYY HH:mm'),
            ]) }}
        </p>
    @else
        <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-[2fr_1fr_1fr] print:grid-cols-[2fr_1fr_1fr]">
            @foreach ($lines as $line)
                <div>
                    <div class="h-12 border-b border-border-strong"></div>
                    <p class="mt-1 text-xs uppercase tracking-wider text-text-muted">{{ $line }}</p>
                </div>
            @endforeach
        </div>
    @endif
</section>
