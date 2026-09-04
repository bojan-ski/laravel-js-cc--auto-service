<x-layouts.app :title="__('parts.adjust.title', ['sku' => $part->sku])">
    <h1 class="text-2xl font-bold">{{ __('parts.adjust.title', ['sku' => $part->sku]) }}</h1>

    <x-parts.adjust-form :part="$part" :suppliers="$suppliers" />
</x-layouts.app>
