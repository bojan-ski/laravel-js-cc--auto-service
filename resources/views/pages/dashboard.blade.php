<x-layouts.app :title="__('dashboard.title')">
    <h1 class="text-2xl font-bold">{{ __('dashboard.title') }}</h1>

    <div class="mt-4 grid gap-3 xl:grid-cols-2">
        <x-dashboard.today :today="$today" :appointments="$appointments" />
        <x-dashboard.active-jobs :jobs="$jobs" :count="$jobCount" />
    </div>

    <div class="mt-3">
        <x-dashboard.low-stock :parts="$parts" :count="$partCount" :url="$lowStockUrl" />
    </div>
</x-layouts.app>
