<section class="mt-4 max-w-2xl border border-border bg-surface p-4 print:hidden">
    <h2 class="font-semibold">{{ __('estimates.decision.title') }}</h2>

    <form method="POST" action="{{ route('estimates.decision.update', $estimate) }}" class="mt-3 space-y-4">
        @csrf
        @method('PATCH')

        <x-forms.radio-group
            name="decision"
            :label="__('estimates.decision.decision')"
            :options="$decisions"
            inline
            required
        />

        <x-forms.radio-group
            name="decision_method"
            :label="__('estimates.decision.method')"
            :options="$methods"
            inline
            required
        />

        <x-forms.textarea name="decision_note" :label="__('estimates.decision.note')" rows="2" maxlength="500" />

        <x-ui.button
            type="submit"
            :aria-label="__('estimates.decision.submit_for', ['number' => $estimate->number])"
        >
            {{ __('estimates.decision.submit') }}
        </x-ui.button>
    </form>
</section>
