@props(['members', 'currentUserId'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('staff.count', $members->total(), ['count' => $members->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
    <table role="table" class="w-full text-left">
        <x-staff.table-head />

        <tbody role="rowgroup">
            @foreach ($members as $member)
                <x-staff.row :member="$member" :current-user-id="$currentUserId" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $members->links() }}</div>
