@props(['part', 'types'])

@php
    $numberNote = $errors->has('number') ? null : 'number-note';
@endphp

<form
    method="POST"
    action="{{ route('parts.numbers.store', $part) }}"
    class="mt-4 flex flex-wrap items-start gap-2 border-t border-border pt-4"
>
    @csrf

    <h3 class="sr-only">{{ __('parts.numbers.add') }}</h3>

    <div class="w-40">
        <x-forms.select
            name="type"
            :label="__('parts.numbers.type_label')"
            :options="$types"
        />
    </div>

    <div class="min-w-56 flex-1">
        <x-forms.field
            name="number"
            :label="__('parts.numbers.number_label')"
            :aria-describedby="$numberNote"
            required
        />
    </div>

    <x-ui.button type="submit" class="mt-6">{{ __('parts.numbers.submit') }}</x-ui.button>

    <p id="number-note" class="w-full text-sm text-text-soft">{{ __('parts.numbers.note') }}</p>
</form>
