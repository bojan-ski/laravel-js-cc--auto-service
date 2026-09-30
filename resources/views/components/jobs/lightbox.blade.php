<dialog
    data-lightbox-dialog
    aria-label="{{ __('jobs.attachments.lightbox.label') }}"
    class="m-auto max-w-[90vw] bg-transparent p-0 backdrop:bg-surface-dark/80 dark:backdrop:bg-background/90"
>
    <div class="border border-border-strong bg-surface p-2">
        <img data-lightbox-image src="" alt="" class="max-h-[80vh] max-w-full object-contain">

        <div class="mt-2 flex justify-end gap-2">
            <form method="POST" action="" data-lightbox-delete data-confirm="" hidden class="mr-auto">
                @csrf
                @method('DELETE')

                <x-ui.button type="submit" variant="danger">
                    {{ __('jobs.attachments.delete.action') }}
                </x-ui.button>
            </form>

            <x-ui.button type="button" variant="secondary" data-lightbox-close>
                {{ __('jobs.attachments.lightbox.close') }}
            </x-ui.button>
        </div>
    </div>
</dialog>
