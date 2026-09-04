@props(['part', 'numbers', 'types'])

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="text-lg font-semibold">{{ __('parts.numbers.section') }}</h2>

    @if ($numbers->isEmpty())
        <p class="mt-2 text-text-muted">{{ __('parts.numbers.empty') }}</p>
    @else
        <ul class="mt-2">
            @foreach ($numbers as $number)
                <x-parts.number-row :part="$part" :number="$number" />
            @endforeach
        </ul>
    @endif

    @can('update', $part)
        <x-parts.number-form :part="$part" :types="$types" />
    @endcan
</section>
