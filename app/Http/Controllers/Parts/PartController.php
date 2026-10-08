<?php

namespace App\Http\Controllers\Parts;

use App\Enums\PartNumberType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\PartIndexRequest;
use App\Http\Requests\Parts\PartRequest;
use App\Models\Part;
use App\Services\BrandService;
use App\Services\PartService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PartController extends Controller
{
    public function __construct(
        private PartService $parts,
        private StockService $stock,
        private BrandService $brands,
    ) {}

    public function index(PartIndexRequest $request): View
    {
        Gate::authorize('viewAny', Part::class);

        $filters = $request->filters();
        $withTrashed = $filters['trashed'] === PartIndexRequest::TRASHED_WITH
            && Gate::allows('viewDeleted', Part::class);

        return view('pages.parts.index', [
            'parts' => $this->parts->paginateFiltered($filters, $request->user(), $withTrashed),
            'counts' => $this->parts->counts($filters, $withTrashed),
            'filters' => $filters,
            'hasAny' => $this->parts->hasAny($withTrashed),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Part::class);

        return view('pages.parts.create', $this->formOptions());
    }

    public function store(PartRequest $request): RedirectResponse
    {
        Gate::authorize('create', Part::class);

        $part = $this->parts->create($request->validated());

        return redirect()->route('parts.show', $part)
            ->with('success', __('parts.flash.created', ['sku' => $part->sku]));
    }

    public function show(Request $request, int $part): View
    {
        $visible = $this->parts->findVisible(
            $part,
            $request->user(),
            withTrashed: Gate::allows('viewDeleted', Part::class),
        );

        Gate::authorize('view', $visible);

        $visible->load(['brand:id,name,quality_tier,deleted_at', 'numbers']);

        return view('pages.parts.show', [
            'part' => $visible,
            'numbers' => $visible->numbers,
            'numberTypes' => PartNumberType::options(),
            'movements' => $this->stock->historyFor($visible),
            'movementTotal' => $this->stock->movementCountFor($visible),
        ]);
    }

    public function edit(Request $request, int $part): View
    {
        $visible = $this->parts->findVisible($part, $request->user());

        Gate::authorize('update', $visible);

        return view('pages.parts.edit', ['part' => $visible, ...$this->formOptions()]);
    }

    /**
     * @return array{brands: array<int, string>, categories: list<string>}
     */
    private function formOptions(): array
    {
        return [
            'brands' => $this->brands->pickerOptions(),
            'categories' => $this->parts->categoryOptions(),
        ];
    }

    public function update(PartRequest $request, int $part): RedirectResponse
    {
        $visible = $this->parts->findVisible($part, $request->user());

        Gate::authorize('update', $visible);

        $this->parts->update($visible, $request->validated());

        return redirect()->route('parts.show', $visible)
            ->with('success', __('parts.flash.updated', ['sku' => $visible->sku]));
    }

    public function destroy(Request $request, int $part): RedirectResponse
    {
        $visible = $this->parts->findVisible($part, $request->user());

        Gate::authorize('delete', $visible);

        $this->parts->delete($visible);

        return redirect()->route('parts.index')
            ->with('success', __('parts.flash.deleted', ['sku' => $visible->sku]));
    }

    public function restore(Request $request, int $part): RedirectResponse
    {
        $visible = $this->parts->findVisible(
            $part,
            $request->user(),
            withTrashed: Gate::allows('viewDeleted', Part::class),
        );

        Gate::authorize('restore', $visible);

        $this->parts->restore($visible);

        return redirect()->route('parts.show', $visible)
            ->with('success', __('parts.flash.restored', ['sku' => $visible->sku]));
    }
}
