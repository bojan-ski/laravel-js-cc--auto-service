@props(['template', 'lines', 'editing', 'adding', 'partOptions', 'hasDeletedPart'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-6 max-w-4xl border border-border bg-surface p-4">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('templates.lines.heading') }}</h2>

    @if ($hasDeletedPart)
        <p role="note" class="mt-2 text-sm font-medium text-status-cancelled">{{ __('templates.lines.deleted_part_notice') }}</p>
    @endif

    @if ($lines->isEmpty())
        <x-ui.empty-state class="mt-2" :title="__('templates.lines.empty')" :level="0" />
    @else
        <div class="mt-2 overflow-x-auto border border-border">
            <table role="table" class="w-full text-left">
                <thead role="rowgroup">
                    <tr role="row" class="border-b border-border bg-surface-muted">
                        <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('templates.lines.headers.type') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('templates.lines.headers.description') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('templates.lines.headers.quantity') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">
                            <span class="sr-only">{{ __('templates.lines.headers.actions') }}</span>
                        </th>
                    </tr>
                </thead>

                <tbody role="rowgroup">
                    @foreach ($lines as $line)
                        <x-job-templates.line-row :template="$template" :line="$line" :editing="$editing?->is($line) ?? false" />
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-job-templates.line-form
        :template="$template"
        :item="$editing"
        :adding="$adding"
        :part-options="$partOptions"
    />
</section>
