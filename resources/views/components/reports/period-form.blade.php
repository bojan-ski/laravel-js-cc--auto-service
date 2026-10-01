@props(['from', 'to'])

<form method="GET" action="{{ route('reports.index') }}" class="mt-4">
    <fieldset>
        <legend class="sr-only">{{ __('reports.period.legend') }}</legend>

        <div class="grid gap-3 md:flex md:items-end">
            <div class="md:w-44">
                <x-forms.field name="from" type="date" :label="__('reports.period.from')" :value="$from->toDateString()" />
            </div>

            <div class="md:w-44">
                <x-forms.field name="to" type="date" :label="__('reports.period.to')" :value="$to->toDateString()" />
            </div>

            <div class="flex gap-3 md:contents">
                <x-ui.button type="submit">{{ __('reports.period.apply') }}</x-ui.button>

                <x-ui.button
                    variant="secondary"
                    class="ms-auto"
                    :href="route('reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()])"
                    :aria-label="__('reports.export.aria', ['from' => $from->isoFormat('D MMM YYYY'), 'to' => $to->isoFormat('D MMM YYYY')])"
                >{{ __('reports.export.label') }}</x-ui.button>
            </div>
        </div>
    </fieldset>
</form>

<p class="mt-2 text-sm text-text-soft tabular-nums">
    {{ __('reports.period.summary', ['from' => $from->isoFormat('D MMM YYYY'), 'to' => $to->isoFormat('D MMM YYYY')]) }}
</p>
