@props(['member', 'currentUserId'])

@php
    $isSelf = $member->getKey() === $currentUserId;
@endphp

<tr role="row" @class([
    'grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0',
    'bg-surface-muted text-text-muted' => $member->isOffboarded(),
])>
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-2">
        <span class="font-medium">{{ $member->name }}</span>

        @if ($isSelf)
            <span class="ml-1 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('staff.you') }}</span>
        @endif

        @if ($member->isOffboarded())
            <span class="block text-xs font-medium uppercase tracking-wider">
                {{ __('staff.offboarded.on', ['date' => $member->deleted_at->translatedFormat('j M Y')]) }}
            </span>
        @endif
    </td>

    <td role="cell" class="hidden lg:table-cell lg:px-3 lg:py-2">
        {{ $member->email }}
    </td>

    <td role="cell" class="col-start-1 row-start-2 md:table-cell md:px-3 md:py-2">
        <x-staff.role-badge :role="$member->role" />
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2 md:text-start">
        <div class="flex flex-wrap items-center justify-end gap-2 md:justify-start">
            @if ($member->isOffboarded())
                @can('restore', $member)
                    <x-ui.action-form
                        :action="route('admin.users.restore', $member)"
                        :confirm="__('staff.restore.confirm', ['name' => $member->name])"
                        :aria-label="__('staff.actions.restore_named', ['name' => $member->name])"
                    >
                        {{ __('staff.actions.restore') }}
                    </x-ui.action-form>
                @endcan
            @else
                @can('update', $member)
                    <x-ui.button
                        variant="secondary"
                        :href="route('admin.users.edit', $member)"
                        :aria-label="__('staff.actions.edit', ['name' => $member->name])"
                    >
                        {{ __('app.actions.edit') }}
                    </x-ui.button>
                @endcan

                @can('delete', $member)
                    <x-ui.button
                        variant="danger"
                        :href="route('admin.users.offboard', $member)"
                        :aria-label="__('staff.actions.offboard_named', ['name' => $member->name])"
                    >
                        {{ __('staff.actions.offboard') }}
                    </x-ui.button>
                @endcan
            @endif
        </div>
    </td>
</tr>
