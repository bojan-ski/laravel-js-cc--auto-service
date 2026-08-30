<x-layouts.app :title="$part->sku">
    <x-parts.show-header :part="$part" />

    <x-parts.details :part="$part" />

    <x-parts.movements-section :part="$part" :movements="$movements" :total="$movementTotal" />
</x-layouts.app>
