@props([
    'action',
    'method' => 'POST',
    'part' => null,
    'brands',
    'categories',
    'submit',
    'cancel',
])

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-forms.field
        name="sku"
        :label="__('parts.form.sku')"
        :value="$part?->sku"
        class="tabular-nums"
        required
        autofocus
    />

    <x-forms.field name="name" :label="__('parts.form.name')" :value="$part?->name" required />

    <x-forms.select
        name="brand_id"
        :label="__('parts.form.brand_id')"
        :options="$brands"
        :selected="$part?->brand_id"
        :placeholder="__('brands.picker.placeholder')"
    />

    <x-forms.field
        name="category"
        :label="__('parts.form.category')"
        :value="$part?->category"
        list="part-categories"
    />

    <datalist id="part-categories">
        @foreach ($categories as $category)
            <option value="{{ $category }}"></option>
        @endforeach
    </datalist>

    <x-forms.textarea name="description" :label="__('parts.form.description')" :value="$part?->description" />

    <x-forms.field
        name="unit_price"
        type="number"
        step="0.01"
        min="0"
        :label="__('parts.form.unit_price')"
        :value="$part?->unit_price"
        class="tabular-nums"
        required
    />

    @can('viewCostPrice', App\Models\Part::class)
        <x-forms.field
            name="cost_price"
            type="number"
            step="0.01"
            min="0"
            :label="__('parts.form.cost_price')"
            :value="$part?->cost_price"
            class="tabular-nums"
        />
    @else
        @error('cost_price')
            <p class="text-sm font-medium text-status-cancelled" role="alert">{{ $message }}</p>
        @enderror
    @endcan

    <x-forms.field
        name="reorder_level"
        type="number"
        min="0"
        :label="__('parts.form.reorder_level')"
        :value="$part?->reorder_level"
        class="tabular-nums"
        required
    />

    <x-forms.field name="location" :label="__('parts.form.location')" :value="$part?->location" />

    <div class="border border-border bg-surface-muted px-3 py-2">
        <p class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('parts.form.stock_title') }}</p>
        <p class="mt-1 text-sm text-text-soft">{{ __('parts.form.stock_note') }}</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="$cancel">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
