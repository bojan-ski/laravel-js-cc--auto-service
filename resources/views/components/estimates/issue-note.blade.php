@props(['summary'])

<div class="border border-status-unpaid bg-status-unpaid/10 p-4" role="note">
    <x-estimates.warning :summary="$summary" />
</div>
