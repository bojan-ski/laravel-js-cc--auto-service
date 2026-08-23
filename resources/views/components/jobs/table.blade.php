@props(['jobs', 'filters'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('jobs.board.count', $jobs->total(), ['count' => $jobs->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface">
    <table role="table" class="w-full text-left">
        <x-jobs.table-head :filters="$filters" />

        <tbody role="rowgroup">
            @foreach ($jobs as $job)
                <x-jobs.row :job="$job" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $jobs->links() }}</div>
