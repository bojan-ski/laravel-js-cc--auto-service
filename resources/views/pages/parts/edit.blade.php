<x-layouts.app :title="__('parts.edit.title', ['sku' => $part->sku])">
    <h1 class="text-2xl font-bold">{{ __('parts.edit.title', ['sku' => $part->sku]) }}</h1>

    <x-parts.form
        method="PUT"
        :action="route('parts.update', $part)"
        :part="$part"
        :submit="__('parts.edit.submit')"
        :cancel="route('parts.show', $part)"
    />
</x-layouts.app>
