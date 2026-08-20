@props(['heading', 'message'])

<section class="mt-6">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $heading }}</h2>

    <x-ui.empty-state class="mt-2" :title="$message" />
</section>
