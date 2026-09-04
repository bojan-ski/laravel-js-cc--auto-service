<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\RecordStockMovementRequest;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private StockService $stock,
        private SupplierService $suppliers,
    ) {}

    public function create(Request $request, int $part): View
    {
        $visible = $this->visible($part, $request->user());

        Gate::authorize('adjustStock', Part::class);

        return view('pages.parts.adjust', [
            'part' => $visible,
            'suppliers' => $this->suppliers->pickerOptions(),
        ]);
    }

    public function store(RecordStockMovementRequest $request, int $part): RedirectResponse
    {
        $visible = $this->visible($part, $request->user());

        Gate::authorize('adjustStock', Part::class);

        $movement = $this->stock->record(
            $visible,
            (int) $request->validated('quantity_change'),
            $request->reason(),
            $request->user(),
            note: $request->validated('note'),
            supplier: $this->suppliers->resolveByName($request->validated('supplier_name')),
        );

        return $this->redirectAfter($visible, $movement);
    }

    /**
     * A soft-deleted part is not adjustable, so this deliberately omits
     * withTrashed() and 404s — the behaviour spec 15 settled.
     */
    private function visible(int $part, User $user): Part
    {
        return Part::query()->visibleTo($user)->findOrFail($part);
    }

    private function redirectAfter(Part $part, StockMovement $movement): RedirectResponse
    {
        $redirect = redirect()->route('parts.show', $part)
            ->with('success', __('parts.flash.stock_recorded', [
                'sku' => $part->sku,
                'change' => sprintf('%+d', $movement->quantity_change),
                'balance' => $part->quantity_in_stock,
            ]));

        if (! $part->hasNegativeStock()) {
            return $redirect;
        }

        return $redirect->with('warning', __('parts.flash.stock_negative', [
            'sku' => $part->sku,
            'balance' => $part->quantity_in_stock,
        ]));
    }
}
