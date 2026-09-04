@props(['parts', 'count', 'url'])

<section class="border border-border bg-surface">
    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-2 px-3 py-2">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">
            {{ trans_choice('dashboard.low_stock.title', $count, ['count' => $count]) }}
        </h2>

        @if ($parts->isEmpty())
            <p class="text-sm text-text-muted">{{ __('dashboard.low_stock.empty') }}</p>
        @else
            <ul class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
                @foreach ($parts as $part)
                    <li class="flex items-baseline gap-2">
                        @unless ($loop->first)
                            <span class="text-text-muted" aria-hidden="true">&middot;</span>
                        @endunless

                        <a
                            href="{{ route('parts.show', $part) }}"
                            @class([
                                'underline underline-offset-2 hover:text-brand-dark',
                                'font-bold text-status-low-stock' => $part->isOutOfStock(),
                            ])
                        >{{ $part->name }}</a>

                        @if ($part->isOutOfStock())
                            <span class="text-xs font-semibold uppercase tracking-wider text-status-low-stock">{{ __('dashboard.low_stock.out_of_stock') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="ms-auto text-sm">
            <a
                href="{{ $url }}"
                class="font-medium underline underline-offset-2 hover:text-brand-dark"
            >{{ __('dashboard.low_stock.view_all') }} <span aria-hidden="true">&rarr;</span></a>
        </p>
    </div>
</section>
