<x-layouts.app :title="__('staff.offboard.title', ['name' => $member->name])">
    <h1 class="text-2xl font-bold">{{ __('staff.offboard.title', ['name' => $member->name]) }}</h1>

    <x-staff.offboard-notice />

    <form method="POST" action="{{ route('admin.users.destroy', $member) }}" class="mt-4 flex flex-wrap gap-2">
        @csrf
        @method('DELETE')

        <x-ui.button type="submit" variant="danger" aria-describedby="offboard-consequences">
            {{ __('staff.offboard.submit', ['name' => $member->name]) }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="route('admin.users.index')">
            {{ __('app.actions.cancel') }}
        </x-ui.button>
    </form>
</x-layouts.app>
