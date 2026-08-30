<x-layouts.app :title="__('invoices.issue.title', ['reference' => $job->reference])">
    <h1 class="text-2xl font-bold">
        {{ __('invoices.issue.heading') }}
        <span class="tabular-nums">{{ $job->reference }}</span>
    </h1>

    <x-invoices.issue-form :job="$job" :totals="$totals" />
</x-layouts.app>
