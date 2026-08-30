<x-layouts.app :title="$part->sku">
    <x-parts.show-header :part="$part" />

    <x-parts.details :part="$part" />

    <x-parts.movements-section />
</x-layouts.app>
