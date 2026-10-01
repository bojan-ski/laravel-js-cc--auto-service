<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\UpdateNextServiceRequest;
use App\Models\Job;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobNextServiceController extends Controller
{
    public function __construct(
        private VehicleService $vehicles,
    ) {}

    public function edit(Job $job): View
    {
        Gate::authorize('scheduleNextService', $job);

        return view('pages.jobs.next-service', [
            'job' => $job,
            'vehicle' => $this->vehicles->activeForJob($job),
        ]);
    }

    public function update(UpdateNextServiceRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('scheduleNextService', $job);

        $vehicle = $this->vehicles->update($this->vehicles->activeForJob($job), $request->validated());

        return redirect()->route('jobs.show', $job)
            ->with('success', __('vehicles.flash.next_service_saved', ['plate' => $vehicle->registration_plate]));
    }
}
