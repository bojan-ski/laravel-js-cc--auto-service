<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\PartIndexRequest;
use App\Http\Requests\Parts\PartRequest;
use App\Models\Part;
use App\Models\User;
use App\Services\PartService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PartController extends Controller
{
    public function __construct(private PartService $parts) {}

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
            'withTrashed' => $withTrashed,
            'hasAny' => $this->parts->hasAny($withTrashed),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Part::class);

        return view('pages.parts.create');
    }

    public function store(PartRequest $request): RedirectResponse
    {
        Gate::authorize('create', Part::class);

        $part = $this->parts->create($request->validated());

        return redirect()->route('parts.show', $part)
            ->with('success', __('parts.flash.created', ['sku' => $part->sku]));
    }

    public function show(Request $request, Part $part): View
    {
        Gate::authorize('view', $part);

        return view('pages.parts.show', ['part' => $this->visible($part, $request->user())]);
    }

    public function edit(Request $request, Part $part): View
    {
        Gate::authorize('update', $part);

        return view('pages.parts.edit', ['part' => $this->visible($part, $request->user())]);
    }

    public function update(PartRequest $request, Part $part): RedirectResponse
    {
        Gate::authorize('update', $part);

        $this->parts->update($part, $request->validated());

        return redirect()->route('parts.show', $part)
            ->with('success', __('parts.flash.updated', ['sku' => $part->sku]));
    }

    public function destroy(Part $part): RedirectResponse
    {
        Gate::authorize('delete', $part);

        $this->parts->delete($part);

        return redirect()->route('parts.index')
            ->with('success', __('parts.flash.deleted', ['sku' => $part->sku]));
    }

    public function restore(Part $part): RedirectResponse
    {
        Gate::authorize('restore', $part);

        $this->parts->restore($part);

        return redirect()->route('parts.show', $part)
            ->with('success', __('parts.flash.restored', ['sku' => $part->sku]));
    }

    private function visible(Part $part, User $user): Part
    {
        return Part::query()
            ->visibleTo($user)
            ->when(
                Gate::allows('viewDeleted', Part::class),
                fn (Builder $query) => $query->withTrashed(),
            )
            ->findOrFail($part->getKey());
    }
}
