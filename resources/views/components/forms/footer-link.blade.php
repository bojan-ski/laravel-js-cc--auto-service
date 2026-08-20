@props(['href'])

<p class="mt-4 text-center text-sm text-text-muted">
    <a
        href="{{ $href }}"
        {{ $attributes->class([
            'underline hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        ]) }}
    >
        {{ $slot }}
    </a>
</p>
