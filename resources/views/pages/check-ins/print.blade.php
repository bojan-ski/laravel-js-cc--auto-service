<x-layouts.app :title="__('check_ins.show.title', ['reference' => $job->reference])">
    <div class="mb-4 flex flex-wrap items-center gap-3 print:hidden">
        <span data-print-slot data-print-label="{{ __('check_ins.print.action') }}"></span>

        <a href="{{ route('jobs.check-in.show', $job) }}" class="font-medium underline underline-offset-2 hover:text-brand-dark">
            {{ __('check_ins.print.back') }}
        </a>
    </div>

    <div class="border border-border bg-surface p-6 print:border-0 print:p-0">
        <x-check-ins.letterhead :job="$job" :printed-at="$printedAt" />

        <x-check-ins.print-details :job="$job" />

        @if ($job->checkInPhotos->isNotEmpty())
            <section class="mt-6 print:break-inside-avoid">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('check_ins.photos.heading') }}</h2>

                <ul role="list" class="mt-2 grid grid-cols-3 gap-3">
                    @foreach ($job->checkInPhotos as $photo)
                        <li class="print:break-inside-avoid">
                            <img
                                src="{{ route('attachments.show', ['attachment' => $photo, 'variant' => 'thumb']) }}"
                                alt="{{ __('jobs.attachments.photo_alt', ['reference' => $job->reference, 'name' => $photo->uploadedBy->shortName()]) }}"
                                class="aspect-4/3 w-full border border-border object-cover"
                            >
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-check-ins.print-signature :job="$job" />
    </div>
</x-layouts.app>
