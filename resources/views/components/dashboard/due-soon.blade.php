@props(['rows', 'count'])

@php
    $more = $count - count($rows);
@endphp

<section class="border border-border bg-surface">
    <h2 class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
        {{ trans_choice('dashboard.due_soon.title', $count, ['count' => $count]) }}
    </h2>

    @if ($rows === [])
        <p class="px-3 py-4 text-sm text-text-muted">{{ __('dashboard.due_soon.empty') }}</p>
    @else
        <ul role="list">
            @foreach ($rows as $row)
                <li class="border-b border-border last:border-b-0">
                    <a
                        href="{{ route('vehicles.show', $row['vehicle']) }}"
                        class="grid grid-cols-[auto_minmax(0,1fr)] items-baseline gap-x-2 gap-y-0.5 px-3 py-2 text-sm hover:bg-surface-muted hover:text-brand-dark md:grid-cols-[7rem_minmax(0,1fr)_8rem_9rem_minmax(0,12rem)]"
                    >
                        <span class="font-medium tabular-nums underline underline-offset-2">{{ $row['vehicle']->registration_plate }}</span>
                        <span class="min-w-0 truncate text-text-muted">{{ $row['vehicle']->make }} {{ $row['vehicle']->model }}</span>

                        <span class="col-span-2 flex flex-wrap items-baseline gap-x-3 md:contents">
                            <span>{{ $row['kind']->label() }}</span>

                            <span class="tabular-nums">
                                {{ $row['due_on']->isoFormat('D MMM') }}

                                @if ($row['overdue'])
                                    <strong class="ms-1 font-bold">{{ __('dashboard.due_soon.overdue') }}</strong>
                                @endif
                            </span>

                            <span class="md:text-end">
                                @switch ($row['contact'])
                                    @case ('emailed')
                                        <span class="font-semibold text-status-completed" aria-hidden="true">&check;</span>
                                        {{ __('dashboard.due_soon.emailed', ['date' => $row['sent_at']->isoFormat('D MMM')]) }}
                                        @break
                                    @case ('pending')
                                        <span class="text-text-muted">{{ __('dashboard.due_soon.pending') }}</span>
                                        @break
                                    @default
                                        <span class="tabular-nums">{{ $row['phone'] }}</span>
                                @endswitch
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($more > 0)
            <p class="border-t border-border px-3 py-2 text-sm text-text-muted">
                {{ trans_choice('dashboard.due_soon.more', $more, ['count' => $more]) }}
            </p>
        @endif
    @endif
</section>
