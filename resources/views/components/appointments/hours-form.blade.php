@props([
    'hours',
    'inDialog' => false,
])

<form method="POST" action="{{ route('admin.appointment-hours.update') }}" {{ $attributes->class(['space-y-4']) }}>
    @csrf
    @method('PUT')

    <p class="text-sm text-text-soft">{{ __('appointments.hours.intro') }}</p>

    <div class="grid grid-cols-2 gap-3">
        <x-forms.field
            name="open_at"
            type="time"
            :label="__('appointments.hours.open_at')"
            :value="$hours->opensAt()"
            class="tabular-nums"
            required
        />

        <x-forms.field
            name="close_at"
            type="time"
            :label="__('appointments.hours.close_at')"
            :value="$hours->closesAt()"
            class="tabular-nums"
            required
        />
    </div>

    <div class="flex items-center gap-3">
        <x-ui.button type="submit">{{ __('appointments.hours.submit') }}</x-ui.button>

        @if ($inDialog)
            <x-ui.button type="button" variant="secondary" data-dialog-close>
                {{ __('appointments.hours.cancel') }}
            </x-ui.button>
        @else
            <x-ui.button variant="secondary" :href="route('appointments.index')">
                {{ __('appointments.hours.cancel') }}
            </x-ui.button>
        @endif
    </div>
</form>
