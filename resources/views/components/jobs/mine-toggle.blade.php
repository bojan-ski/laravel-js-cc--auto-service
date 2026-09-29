<form method="GET" action="{{ route('jobs.index') }}">
    @foreach ($hiddenFields as $field => $value)
        <input type="hidden" name="{{ $field }}" value="{{ $value }}">
    @endforeach

    <button
        type="submit"
        @unless ($active) name="mine" value="1" @endunless
        aria-pressed="{{ $active ? 'true' : 'false' }}"
        @class([
            'inline-flex items-center justify-center rounded-sm border px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
            'border-transparent bg-surface-dark text-text-inverse' => $active,
            'border-border text-text-soft hover:bg-surface-muted' => ! $active,
        ])
    >
        {{ __('jobs.board.mine') }}<span class="sr-only"> {{ __('jobs.board.mine_hint') }}</span>
    </button>
</form>
