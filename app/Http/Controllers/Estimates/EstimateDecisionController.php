<?php

namespace App\Http\Controllers\Estimates;

use App\Enums\EstimateStatus;
use App\Exceptions\InvalidEstimateTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estimates\RecordEstimateDecisionRequest;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EstimateDecisionController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function update(RecordEstimateDecisionRequest $request, int $estimate): RedirectResponse
    {
        Gate::authorize('viewAny', Estimate::class);

        $record = Estimate::query()->findOrFail($estimate);

        Gate::authorize('decide', $record);

        try {
            $this->estimates->decide($record, $request->decision(), $request->decisionMethod(), $request->note(), $request->user());
        } catch (InvalidEstimateTransition) {
            return redirect()->route('estimates.show', $record)->with('error', __('estimates.flash.not_decidable'));
        }

        $message = $request->decision() === EstimateStatus::Approved
            ? __('estimates.flash.approved', ['number' => $record->number])
            : __('estimates.flash.declined', ['number' => $record->number]);

        return redirect()->route('estimates.show', $record)->with('success', $message);
    }
}
