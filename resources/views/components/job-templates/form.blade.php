@props([
    'template' => null,
    'action',
    'method' => 'POST',
    'submit',
])

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4 border border-border bg-surface p-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-forms.field
        name="name"
        :label="__('templates.fields.name')"
        :value="$template?->name"
        required
        :autofocus="$template === null"
    />

    <x-forms.textarea
        name="description"
        :label="__('templates.fields.description')"
        :value="$template?->description"
        rows="3"
    />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="route('job-templates.index')">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
