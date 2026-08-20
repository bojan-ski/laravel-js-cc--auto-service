<button
    type="submit"
    {{ $attributes->class([
        'w-full rounded-sm bg-brand px-3 py-2 text-sm font-semibold text-text-inverse hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
    ]) }}
>
    {{ $slot }}
</button>
