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

    public function update(UpdateJobItemRequest $request, int $jobItem): RedirectResponse
    {
        $item = $this->visibleItem($request, $jobItem);

        Gate::authorize('update', $item);

        $item = $this->items->update($item, $request->user(), $request->validated());

        return $this->redirectToJob($item->job_id, 'updated', $this->stockWarning($item));
    }

    public function destroy(Request $request, int $jobItem): RedirectResponse
    {
        $item = $this->visibleItem($request, $jobItem);

        Gate::authorize('delete', $item);

        $jobId = $item->job_id;
        $this->items->delete($item, $request->user());

        return $this->redirectToJob($jobId, 'deleted');
    }

    private function visibleItem(Request $request, int $jobItem): JobItem
    {
        return JobItem::query()->visibleTo($request->user())->whereKey($jobItem)->firstOrFail();
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
