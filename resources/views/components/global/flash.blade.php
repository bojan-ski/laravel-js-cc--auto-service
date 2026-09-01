@php
    $regions = [
        'success' => 'border-status-completed bg-status-completed/10',
        'error' => 'border-status-cancelled bg-status-cancelled/10',
        'warning' => 'border-status-awaiting-parts bg-status-awaiting-parts/10',
        'info' => 'border-status-in-progress bg-status-in-progress/10',
    ];
@endphp

@foreach ($regions as $key => $classes)
    @if (session()->has($key))
        <div
            class="border-b print:hidden {{ $classes }}"
            data-flash="{{ $key }}"
            role="{{ $key === 'error' ? 'alert' : 'status' }}"
            aria-live="{{ $key === 'error' ? 'assertive' : 'polite' }}"
        >
            <div class="mx-auto w-full max-w-7xl px-3 py-2 text-sm font-medium text-text md:px-4 lg:px-6">
                <span class="sr-only">{{ __('app.flash.'.$key) }}</span>
                {{ session($key) }}
            </div>
        </div>
    @endif
@endforeach
