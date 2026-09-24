@props(['action', 'method' => 'PATCH', 'name', 'label', 'idPrefix', 'options', 'columns' => 3, 'tone' => 'inverse'])

@php
    $grid = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
    ][$columns];

    $align = $columns === 1 ? 'justify-start' : 'justify-center';

    $tones = [
        'inverse' => [
            'label' => 'text-text-muted',
            'current' => 'border-text-inverse/60 bg-text-inverse/10 font-semibold text-text-inverse',
            'other' => 'border-transparent font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse',
        ],
        'surface' => [
            'label' => 'text-text-muted',
            'current' => 'border-border-strong bg-surface font-semibold text-text',
            'other' => 'border-transparent font-medium text-text-soft hover:bg-surface-muted hover:text-text',
        ],
    ][$tone];
@endphp

<form method="POST" action="{{ $action }}" {{ $attributes }}>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div role="group" aria-labelledby="{{ $idPrefix }}-label">
        <p id="{{ $idPrefix }}-label" class="px-3 pt-1.5 pb-1 text-xs font-medium uppercase tracking-wider {{ $tones['label'] }}">
            {{ $label }}
        </p>

        <div class="grid {{ $grid }} gap-1 px-3 pb-2">
            @foreach ($options as $option)
                <button
                    type="submit"
                    name="{{ $name }}"
                    value="{{ $option['value'] }}"
                    aria-pressed="{{ $option['current'] ? 'true' : 'false' }}"
                    @isset($option['lang']) lang="{{ $option['lang'] }}" @endisset
                    @class([
                        'inline-flex items-center gap-1 rounded-sm border px-2 py-1.5 text-sm whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
                        $align,
                        $tones['current'] => $option['current'],
                        $tones['other'] => ! $option['current'],
                    ])
                >
                    {{ $option['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</form>
