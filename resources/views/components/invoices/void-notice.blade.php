@props(['invoice'])

<div class="mt-4 flex gap-3 border border-border bg-surface-muted p-4" role="note">
    <span aria-hidden="true">&#9432;</span>

    <div>
        <h2 class="font-semibold">{{ __('invoices.void.title') }}</h2>

        <p class="mt-1 text-sm text-text-muted">{{ __('invoices.void.body') }}</p>

        <div class="mt-3 print:hidden">
            <x-ui.button
                variant="secondary"
                :href="route('jobs.show', $invoice->job)"
                :aria-label="__('invoices.void.view_job_for', ['reference' => $invoice->job->reference])"
            >
                {{ __('invoices.void.view_job') }}
            </x-ui.button>
        </div>
    </div>
</div>
