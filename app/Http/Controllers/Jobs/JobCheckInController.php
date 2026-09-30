<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\CheckInNotOpen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\RecordCheckInRequest;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobCheckInController extends Controller
{
    public function __construct(
        private CheckInService $checkIns,
    ) {}

    public function show(Job $job): View
    {
        Gate::authorize('view', $job);

        return view('pages.check-ins.show', ['job' => $this->loadSheet($job)]);
    }

    public function update(RecordCheckInRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('recordCheckIn', $job);

        try {
            $this->checkIns->record($job, $request->user(), $request->validated());
        } catch (CheckInNotOpen) {
            return redirect()->route('jobs.check-in.show', $job)->with('error', __('check_ins.flash.not_open'));
        }

        return redirect()->route('jobs.check-in.show', $job)->with('success', __('check_ins.flash.saved'));
    }

    public function print(Job $job): View
    {
        Gate::authorize('view', $job);

        $job = $this->loadSheet($job);

        abort_if($job->checkIn === null, 404);

        return view('pages.check-ins.print', ['job' => $job, 'printedAt' => now()]);
    }

    private function loadSheet(Job $job): Job
    {
        $job->load(['customer', 'vehicle', 'checkIn.recordedBy', 'checkInPhotos.uploadedBy']);

        $job->checkInPhotos->each(fn (JobAttachment $photo) => $photo->setRelation('job', $job));

        return $job;
    }
}
