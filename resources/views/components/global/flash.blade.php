@php
    $regions = [
        'success' => ['border' => 'border-status-completed', 'tint' => 'bg-status-completed/10', 'timeout' => 3000],
        'error' => ['border' => 'border-status-cancelled', 'tint' => 'bg-status-cancelled/10', 'timeout' => null],
        'warning' => ['border' => 'border-status-awaiting-parts', 'tint' => 'bg-status-awaiting-parts/10', 'timeout' => null],
        'info' => ['border' => 'border-status-in-progress', 'tint' => 'bg-status-in-progress/10', 'timeout' => 3000],
    ];

    $present = array_filter($regions, fn (array $region, string $key): bool => session()->has($key), ARRAY_FILTER_USE_BOTH);
@endphp

@if ($present !== [])
    <div
        class="pointer-events-none fixed inset-x-3 top-14 z-50 flex flex-col gap-2 print:hidden md:inset-x-auto md:inset-e-4 md:w-96"
        data-flash-stack
    >
        @foreach ($present as $key => $region)
            <div
                class="pointer-events-auto border bg-surface shadow-lg transition-opacity duration-200 motion-reduce:transition-none {{ $region['border'] }}"
                data-flash="{{ $key }}"
                @if ($region['timeout']) data-flash-timeout="{{ $region['timeout'] }}" @endif
                role="{{ $key === 'error' ? 'alert' : 'status' }}"
                aria-live="{{ $key === 'error' ? 'assertive' : 'polite' }}"
            >
                <div class="flex items-start gap-3 px-3 py-2 {{ $region['tint'] }}">
                    <p class="flex-1 py-0.5 text-sm font-medium text-text">
                        <span class="sr-only">{{ __('app.flash.'.$key) }}</span>
                        {{ session($key) }}
                    </p>

                    <button
                        type="button"
                        class="-me-1 rounded-sm px-1.5 text-lg leading-6 text-text-soft hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        data-flash-dismiss
                        hidden
                    >
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">{{ __('app.flash.dismiss') }}</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif
