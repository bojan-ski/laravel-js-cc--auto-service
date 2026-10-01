<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Jobs\Concerns\RedirectsClosedJob;
use App\Http\Requests\Jobs\UpdateLineDiscountRequest;
use App\Models\JobItem;
use App\Services\JobItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobItemDiscountController extends Controller
{
    use RedirectsClosedJob;

    public function __construct(
        private JobItemService $items,
    ) {}

    public function update(UpdateLineDiscountRequest $request, int $jobItem): RedirectResponse
    {
        $item = JobItem::query()->visibleTo($request->user())->whereKey($jobItem)->firstOrFail();

        Gate::authorize('discount', $item);

        return $this->unlessJobClosed($item->job_id, function () use ($request, $item): RedirectResponse {
            $this->items->setDiscount($item, (string) $request->validated('discount_percent'));

            return redirect()
                ->route('jobs.show', $item->job_id)
                ->with('success', __('jobs.items.flash.discounted'));
        });
    }
}
