<div id="offboard-consequences" role="note" class="mt-4 max-w-2xl border border-danger bg-surface p-4">
    <ul class="list-disc space-y-1 ps-5 text-sm text-text-soft">
        @foreach (__('staff.offboard.consequences') as $consequence)
            <li>{{ $consequence }}</li>
        @endforeach
    </ul>
</div>
