<x-layouts.app :title="$job->reference">
    <x-jobs.show-header :job="$job" />

    <x-jobs.actions :job="$job" />

    <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
        <x-jobs.details :job="$job" />

        <div class="space-y-4">
            <x-jobs.line-items
                :job="$job"
                :show-pricing="$showPricing"
                :subtotal="$subtotal"
                :editing="$editing"
                :user-options="$userOptions"
                :default-rate="$defaultRate"
            />

            <x-jobs.attachments />
        </div>
    </div>
</x-layouts.app>
