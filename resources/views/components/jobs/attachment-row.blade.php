@props(['attachment'])

<li class="border-b border-border py-2 last:border-b-0 md:flex md:items-center md:gap-3">
    <span class="block text-xs font-medium uppercase tracking-wider text-text-muted md:shrink-0">
        {{ $attachment->type->label() }}
    </span>

    <span class="mt-0.5 block break-all text-sm text-text md:mt-0 md:min-w-0 md:flex-1 md:break-normal">
        {{ $attachment->displayFilename() }}
    </span>

    <span class="mt-0.5 block text-sm tabular-nums text-text-muted md:mt-0 md:shrink-0">
        {{ $attachment->humanSize() }}
    </span>

    <span class="mt-2 flex items-center gap-2 md:mt-0 md:shrink-0">
        <x-ui.button
            variant="secondary"
            :href="route('attachments.show', $attachment)"
            :aria-label="__('jobs.attachments.download.action_for', ['filename' => $attachment->original_filename])"
        >
            {{ __('jobs.attachments.download.action') }}
        </x-ui.button>

        @can('delete', $attachment)
            <x-ui.action-form
                :action="route('attachments.destroy', $attachment)"
                method="DELETE"
                variant="danger"
                :confirm="__('jobs.attachments.delete.confirm', ['filename' => $attachment->original_filename])"
                :aria-label="__('jobs.attachments.delete.action_for', ['filename' => $attachment->original_filename])"
            >
                {{ __('jobs.attachments.delete.action') }}
            </x-ui.action-form>
        @endcan
    </span>
</li>
