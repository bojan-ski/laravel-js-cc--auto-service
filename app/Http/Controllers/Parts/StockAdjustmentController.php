<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\RecordStockMovementRequest;
use App\Models\Part;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function create(Request $request, Part $part): View
    {
        Gate::authorize('adjustStock', Part::class);

        return view('pages.parts.adjust', [
            'part' => Part::query()->visibleTo($request->user())->findOrFail($part->getKey()),
        ]);
    }

    public function store(RecordStockMovementRequest $request, Part $part): RedirectResponse
    {
        Gate::authorize('adjustStock', Part::class);

        $movement = $this->stock->record(
            $part,
            (int) $request->validated('quantity_change'),
            $request->reason(),
            $request->user(),
            note: $request->validated('note'),
        );

        return $this->redirectAfter($part, $movement);
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
