<x-layouts.app :title="$job->reference">
    <x-jobs.show-header :job="$job" />

    <x-jobs.actions :job="$job" :invoice="$invoice" />

    <div class="mt-4 grid items-start gap-4 xl:grid-cols-3">
        <div class="min-w-0">
            <x-jobs.details :job="$job" />
        </div>

        <div class="min-w-0 xl:col-span-2">
            <x-jobs.attachments :job="$job" :photos="$photos" :documents="$documents" />
        </div>
    </div>

    <div class="mt-4">
        <x-jobs.line-items
            :job="$job"
            :show-pricing="$showPricing"
            :subtotal="$subtotal"
            :editing="$editing"
            :adding="$adding"
            :show-part-form="$showPartForm"
            :user-options="$userOptions"
            :part-options="$partOptions"
            :default-rate="$defaultRate"
        />
    </div>
</x-layouts.app>
