@props(['customer'])

<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ $customer->name }}</h1>

    <div class="flex flex-wrap gap-2">
        @can('update', $customer)
            <x-ui.button
                variant="secondary"
                :href="route('customers.edit', $customer)"
                :aria-label="__('customers.edit.title', ['name' => $customer->name])"
            >
                {{ __('app.actions.edit') }}
            </x-ui.button>
        @endcan

        @can('delete', $customer)
            <form
                method="POST"
                action="{{ route('customers.destroy', $customer) }}"
                data-confirm="{{ __('customers.delete.confirm', ['name' => $customer->name]) }}"
            >
                @csrf
                @method('DELETE')

                <x-ui.button
                    type="submit"
                    variant="danger"
                    :aria-label="__('customers.delete.action', ['name' => $customer->name])"
                >
                    {{ __('app.actions.delete') }}
                </x-ui.button>
            </form>
        @endcan
    </div>
</div>
