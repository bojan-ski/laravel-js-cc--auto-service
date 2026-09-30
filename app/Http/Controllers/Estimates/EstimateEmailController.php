<?php

namespace App\Http\Controllers\Estimates;

use App\Exceptions\InvalidEstimateTransition;
use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EstimateEmailController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function store(int $estimate): RedirectResponse
    {
        Gate::authorize('viewAny', Estimate::class);

        $record = Estimate::query()->with('job.customer')->findOrFail($estimate);

        Gate::authorize('email', $record);

        try {
            $sent = $this->estimates->email($record);
        } catch (InvalidEstimateTransition) {
            return redirect()->route('estimates.show', $record)->with('error', __('estimates.flash.not_emailable'));
        }

        return $sent
            ? redirect()->route('estimates.show', $record)->with('success', __('estimates.flash.emailed', ['number' => $record->number, 'email' => $record->job->customer->email]))
            : redirect()->route('estimates.show', $record)->with('warning', __('estimates.flash.no_email'));
    }
}
