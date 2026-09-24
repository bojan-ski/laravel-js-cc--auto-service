@props(['options', 'idPrefix'])

<form method="POST" action="{{ route('preferences.theme.update') }}" {{ $attributes }}>
    @csrf
    @method('PATCH')

    <div role="group" aria-labelledby="{{ $idPrefix }}-theme-label">
        <p id="{{ $idPrefix }}-theme-label" class="px-3 pt-1.5 pb-1 text-xs font-medium uppercase tracking-wider text-text-muted">
            {{ __('app.theme.label') }}
        </p>

        <div class="flex gap-1 px-3 pb-2">
            @foreach ($options as $option)
                <button
                    type="submit"
                    name="theme"
                    value="{{ $option['value'] }}"
                    aria-pressed="{{ $option['current'] ? 'true' : 'false' }}"
                    @class([
                        'inline-flex flex-1 items-center justify-center gap-1 rounded-sm border px-2 py-1.5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
                        'border-text-inverse/60 bg-text-inverse/10 font-semibold text-text-inverse' => $option['current'],
                        'border-transparent font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse' => ! $option['current'],
                    ])
                >
                    @if ($option['current'])
                        <span aria-hidden="true">&#10003;</span>
                    @endif
                    {{ $option['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</form>
