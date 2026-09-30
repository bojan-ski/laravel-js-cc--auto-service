<section {{ $attributes->class('border border-border bg-surface p-4') }}>
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('check_ins.form.heading') }}</h2>

    <form method="POST" action="{{ route('jobs.check-in.update', $job) }}" class="mt-3 max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <x-forms.radio-group
            name="fuel_level"
            :label="__('check_ins.fields.fuel_level')"
            :options="$fuelLevels"
            :selected="$checkIn?->fuel_level?->value"
            inline
            required
        />

        <x-forms.textarea
            name="damage_notes"
            :label="__('check_ins.fields.damage_notes')"
            :value="$checkIn?->damage_notes"
            rows="3"
            maxlength="2000"
        />

        <x-forms.textarea
            name="valuables"
            :label="__('check_ins.fields.valuables')"
            :value="$checkIn?->valuables"
            rows="2"
            maxlength="1000"
        />

        <div class="flex justify-end">
            <x-ui.button type="submit">{{ __('check_ins.form.submit') }}</x-ui.button>
        </div>
    </form>
</section>
