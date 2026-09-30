@props(['job', 'printedAt'])

<div class="flex flex-wrap items-start justify-between gap-6 border-b border-border-strong pb-4">
    <div>
        <p class="text-lg font-bold">{{ config('shop.identity.name') }}</p>
        <p>{{ config('shop.identity.address') }}</p>
        <p class="tabular-nums">{{ config('shop.identity.tax_number') }}</p>
    </div>

    <div class="text-end">
        <h1 class="text-2xl font-bold">{{ __('check_ins.print.title') }}</h1>
        <p class="font-semibold tabular-nums">{{ $job->reference }}</p>
        <p class="text-sm tabular-nums">{{ __('check_ins.print.printed', ['date' => $printedAt->isoFormat('D MMM YYYY HH:mm')]) }}</p>
    </div>
</div>
