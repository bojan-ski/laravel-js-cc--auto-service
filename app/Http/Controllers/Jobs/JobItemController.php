<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Jobs\Concerns\FlashesStockWarning;
use App\Http\Requests\Jobs\StoreJobItemRequest;
use App\Http\Requests\Jobs\UpdateJobItemRequest;
use App\Models\Job;
use App\Models\JobItem;
use App\Services\JobItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JobItemController extends Controller
{
    use FlashesStockWarning;

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

        $item = $this->items->update($jobItem, $request->user(), $request->validated());

        return $this->redirectToJob($item->job_id, 'updated', $this->stockWarning($item));
    }

    public function destroy(Request $request, JobItem $jobItem): RedirectResponse
    {
        Gate::authorize('delete', $jobItem);

        $jobId = $jobItem->job_id;
        $this->items->delete($jobItem, $request->user());

        return $this->redirectToJob($jobId, 'deleted');
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function redirectToJob(int $jobId, string $flashKey, array $extra = []): RedirectResponse
    {
        return redirect()
            ->route('jobs.show', $jobId)
            ->with(['success' => __("jobs.items.flash.{$flashKey}")] + $extra);
    }
}
