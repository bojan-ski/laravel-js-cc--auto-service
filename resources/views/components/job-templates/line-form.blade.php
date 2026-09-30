@if ($hasNoParts())
    <div id="template-line-form" class="mt-4 border border-border bg-surface-muted p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">{{ $heading() }}</h3>

            @unless ($isEdit())
                <x-job-templates.add-switch :template="$template" :adding="$type()->value" />
            @endunless
        </div>

        <p class="mt-2 text-sm text-text-soft">{{ __('templates.lines.add.no_parts') }}</p>
    </div>
@else
    <form id="template-line-form" method="POST" action="{{ $action() }}" class="mt-4 border border-border bg-surface-muted p-3">
        @csrf

        @if ($isEdit())
            @method('PUT')
        @else
            <input type="hidden" name="type" value="{{ $type()->value }}">
        @endif

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">{{ $heading() }}</h3>

            @unless ($isEdit())
                <x-job-templates.add-switch :template="$template" :adding="$type()->value" />
            @endunless
        </div>

        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_7rem_auto]">
            @if ($isPart())
                <x-forms.select
                    class="lg:h-10.5"
                    name="part_id"
                    :label="__('templates.lines.add.part')"
                    :options="$partOptions"
                    :selected="$selectedPart()"
                    :placeholder="__('parts.picker.placeholder')"
                    required
                />
            @else
                <x-forms.field
                    name="line_description"
                    :label="__('templates.lines.add.description')"
                    :value="$item?->description"
                    required
                />
            @endif

            <x-forms.field
                name="quantity"
                type="number"
                :step="$quantityStep()"
                :min="$quantityMin()"
                :label="$quantityLabel()"
                :value="$quantityValue()"
                required
            />

            <div class="flex flex-wrap gap-2 lg:pt-6">
                <x-ui.button class="lg:h-10.5">{{ $submitLabel() }}</x-ui.button>

                @if ($isEdit())
                    <x-ui.button variant="secondary" class="lg:h-10.5" :href="route('job-templates.edit', $template)">
                        {{ __('templates.lines.edit.cancel') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </form>
@endif
