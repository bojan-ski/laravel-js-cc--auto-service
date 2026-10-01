<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Jobs\Concerns\FlashesStockWarning;
use App\Http\Controllers\Jobs\Concerns\RedirectsClosedJob;
use App\Http\Requests\Jobs\StorePartLineRequest;
use App\Models\Job;
use App\Models\Part;
use App\Services\JobItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobPartLineController extends Controller
{
    use FlashesStockWarning;
    use RedirectsClosedJob;

    public function __construct(
        private JobItemService $items,
    ) {}

    public function store(StorePartLineRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('createItem', $job);

        $part = Part::query()
            ->visibleTo($request->user())
            ->whereKey($request->integer('part_id'))
            ->firstOrFail();

        return $this->unlessJobClosed($job->getKey(), function () use ($request, $job, $part): RedirectResponse {
            $item = $this->items->addPart($job, $part, $request->user(), $request->validated());

            return redirect()
                ->route('jobs.show', $job)
                ->with(['success' => __('jobs.items.flash.part_added')] + $this->stockWarning($item));
        });
    }
}
