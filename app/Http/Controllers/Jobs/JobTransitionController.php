<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobTransitionController extends Controller
{
    public function __construct(
        private JobService $jobs,
    ) {}

    public function start(Job $job): RedirectResponse
    {
        Gate::authorize('start', $job);

        $this->jobs->start($job);

        return $this->redirectToJob($job, 'started');
    }

    public function pause(Job $job): RedirectResponse
    {
        Gate::authorize('pause', $job);

        $this->jobs->pause($job);

        return $this->redirectToJob($job, 'paused');
    }

    public function resume(Job $job): RedirectResponse
    {
        Gate::authorize('resume', $job);

        $this->jobs->resume($job);

        return $this->redirectToJob($job, 'resumed');
    }

    public function complete(Job $job): RedirectResponse
    {
        Gate::authorize('complete', $job);

        $this->jobs->complete($job);

        return $this->redirectToJob($job, 'completed');
    }

    public function cancel(Job $job): RedirectResponse
    {
        Gate::authorize('cancel', $job);

        $this->jobs->cancel($job);

        return $this->redirectToJob($job, 'cancelled');
    }

    public function reopen(Job $job): RedirectResponse
    {
        Gate::authorize('reopen', $job);

        $this->jobs->reopen($job);

        return $this->redirectToJob($job, 'reopened');
    }

    private function redirectToJob(Job $job, string $flashKey): RedirectResponse
    {
        return redirect()
            ->route('jobs.show', $job)
            ->with('success', __("jobs.flash.{$flashKey}", ['reference' => $job->reference]));
    }
}
