<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\EstimateNotCreatable;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JobEstimateController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function store(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('estimate', $job);

        try {
            $estimate = $this->estimates->create($job, $request->user());
        } catch (EstimateNotCreatable) {
            return redirect()
                ->route('jobs.show', $job)
                ->with('error', __('estimates.flash.not_creatable'));
        }

        return redirect()
            ->route('estimates.show', $estimate)
            ->with('success', __('estimates.flash.created', ['number' => $estimate->number]));
    }
}
