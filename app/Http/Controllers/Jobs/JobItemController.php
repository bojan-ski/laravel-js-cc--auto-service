<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreJobItemRequest;
use App\Http\Requests\Jobs\UpdateJobItemRequest;
use App\Models\Job;
use App\Models\JobItem;
use App\Services\JobItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobItemController extends Controller
{
    public function __construct(
        private JobItemService $items,
    ) {}

    public function store(StoreJobItemRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('createItem', $job);

        $this->items->addLabor($job, $request->user(), $request->validated());

        return $this->redirectToJob($job->getKey(), 'added');
    }

    public function update(UpdateJobItemRequest $request, JobItem $jobItem): RedirectResponse
    {
        Gate::authorize('update', $jobItem);

        $this->items->update($jobItem, $request->user(), $request->validated());

        return $this->redirectToJob($jobItem->job_id, 'updated');
    }

    public function destroy(JobItem $jobItem): RedirectResponse
    {
        Gate::authorize('delete', $jobItem);

        $jobId = $jobItem->job_id;
        $this->items->delete($jobItem);

        return $this->redirectToJob($jobId, 'deleted');
    }

    private function redirectToJob(int $jobId, string $flashKey): RedirectResponse
    {
        return redirect()
            ->route('jobs.show', $jobId)
            ->with('success', __("jobs.items.flash.{$flashKey}"));
    }
}
