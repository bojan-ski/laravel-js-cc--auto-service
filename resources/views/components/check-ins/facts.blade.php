@props(['checkIn'])

<section {{ $attributes->class('border border-border bg-surface p-4') }}>
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('check_ins.form.heading') }}</h2>

    <dl class="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-[12rem_minmax(0,1fr)]">
        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">{{ __('check_ins.fields.fuel_level') }}</dt>
        <dd class="tabular-nums">{{ $checkIn->fuel_level->label() }}</dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">{{ __('check_ins.fields.damage_notes') }}</dt>
        <dd class="whitespace-pre-line wrap-break-word"><x-ui.value :value="$checkIn->damage_notes" /></dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">{{ __('check_ins.fields.valuables') }}</dt>
        <dd class="whitespace-pre-line wrap-break-word"><x-ui.value :value="$checkIn->valuables" /></dd>
    </dl>
</section>
