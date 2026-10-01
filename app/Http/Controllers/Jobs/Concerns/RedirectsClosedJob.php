<?php

namespace App\Http\Controllers\Jobs\Concerns;

use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use Closure;
use Illuminate\Http\RedirectResponse;

trait RedirectsClosedJob
{
    /**
     * @param  Closure(): RedirectResponse  $write
     */
    protected function unlessJobClosed(int $jobId, Closure $write): RedirectResponse
    {
        try {
            return $write();
        } catch (JobLockedException|JobNotAcceptingLineItems) {
            return redirect()
                ->route('jobs.show', $jobId)
                ->with('error', __('jobs.items.flash.not_accepting'));
        }
    }
}
