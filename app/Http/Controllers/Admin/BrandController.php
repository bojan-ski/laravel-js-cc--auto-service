<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QualityTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function __construct(private BrandService $brands) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Brand::class);

        $search = trim((string) $request->query('search', ''));

        return view('pages.admin.brands.index', [
            'brands' => $this->brands->paginateFiltered($search, withTrashed: true),
            'search' => $search,
            'hasAny' => $this->brands->hasAny(withTrashed: true),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Brand::class);

        return view('pages.admin.brands.create', ['tiers' => QualityTier::options()]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Gate::authorize('create', Brand::class);

        $brand = $this->brands->create($request->validated());

        return redirect()->route('admin.brands.index')
            ->with('success', __('brands.flash.created', ['name' => $brand->name]));
    }

    public function edit(Brand $brand): View
    {
        Gate::authorize('update', $brand);

        return view('pages.admin.brands.edit', [
            'brand' => $brand,
            'tiers' => QualityTier::options(),
        ]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        $this->brands->update($brand, $request->validated());

        return redirect()->route('admin.brands.index')
            ->with('success', __('brands.flash.updated', ['name' => $brand->name]));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);

        $this->brands->delete($brand);

        return redirect()->route('admin.brands.index')
            ->with('success', __('brands.flash.deleted', ['name' => $brand->name]));
    }

    public function restore(Brand $brand): RedirectResponse
    {
        Gate::authorize('restore', $brand);

        $this->brands->restore($brand);

        return redirect()->route('admin.brands.index')
            ->with('success', __('brands.flash.restored', ['name' => $brand->name]));
    }
}
