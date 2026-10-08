<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\PartNumberRequest;
use App\Models\PartNumber;
use App\Services\PartNumberService;
use App\Services\PartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PartNumberController extends Controller
{
    public function __construct(
        private PartService $parts,
        private PartNumberService $numbers,
    ) {}

    public function store(PartNumberRequest $request, int $part): RedirectResponse
    {
        $visible = $this->parts->findVisible($part, $request->user());

        Gate::authorize('update', $visible);

        $number = $this->numbers->add($visible, $request->type(), $request->validated('number'));

        $redirect = redirect()->route('parts.show', $visible)
            ->with('success', __('parts.numbers.flash.added', ['number' => $number->number]));

        $conflict = $request->type()->isCatalog()
            ? $this->numbers->catalogueConflict($visible, $number->number)
            : null;

        if ($conflict === null) {
            return $redirect;
        }

        return $redirect->with('warning', __('parts.numbers.flash.catalogue_conflict', [
            'number' => $number->number,
            'sku' => $conflict->sku,
            'name' => $conflict->name,
        ]));
    }

    public function destroy(Request $request, int $part, int $number): RedirectResponse
    {
        $visible = $this->parts->findVisible($part, $request->user());

        Gate::authorize('update', $visible);

        $target = PartNumber::query()
            ->where('part_id', $visible->getKey())
            ->findOrFail($number);

        $this->numbers->remove($target);

        return redirect()->route('parts.show', $visible)
            ->with('success', __('parts.numbers.flash.removed', ['number' => $target->number]));
    }
}
