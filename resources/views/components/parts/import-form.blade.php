@props(['maxRows'])

@php
    // Two failure paths land in different bags: the Form Request's own rules
    // (missing file, wrong type, too large) in the default bag, and a rejected
    // CSV body in the named `import` bag. The second is the likelier failure,
    // so the input must announce as invalid for it too — otherwise a screen
    // reader arriving at the control hears nothing wrong.
    $rowErrors = $errors->getBag('import')->isNotEmpty();
    $fieldError = $errors->has('file');

    $describedBy = match (true) {
        $fieldError => null,
        $rowErrors => 'import-errors-heading',
        default => 'import-limits',
    };
@endphp

<form
    method="POST"
    action="{{ route('parts.import.store') }}"
    enctype="multipart/form-data"
    class="mt-4 max-w-2xl space-y-4"
>
    @csrf

    <p class="text-text-soft">{{ __('parts.import.intro') }}</p>

    <x-forms.field
        name="file"
        type="file"
        :label="__('parts.import.file_label')"
        accept=".csv,text/csv,text/plain"
        :aria-describedby="$describedBy"
        :aria-invalid="$rowErrors && ! $fieldError ? 'true' : null"
        required
    />

    <p id="import-limits" class="text-sm text-text-soft">
        {{ __('parts.import.limits', ['rows' => $maxRows]) }}
    </p>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="submit">{{ __('parts.import.submit') }}</x-ui.button>

        <x-ui.button variant="secondary" :href="route('parts.import.template')">
            {{ __('parts.import.template') }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="route('parts.index')">
            {{ __('app.actions.cancel') }}
        </x-ui.button>
    </div>

    <p class="text-sm text-text-soft">{{ __('parts.import.template_note') }}</p>
</form>
