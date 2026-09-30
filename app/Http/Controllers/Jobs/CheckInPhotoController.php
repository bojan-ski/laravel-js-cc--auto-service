<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\CheckInNotOpen;
use App\Exceptions\UnreadableAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreCheckInPhotoRequest;
use App\Models\Job;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CheckInPhotoController extends Controller
{
    public function __construct(
        private CheckInService $checkIns,
    ) {}

    public function store(StoreCheckInPhotoRequest $request, Job $job): RedirectResponse
    {
        abort_if($job->checkIn === null, 404);

        Gate::authorize('recordCheckIn', $job);

        try {
            $this->checkIns->addPhoto($job, $request->file('photo'), $request->user());
        } catch (UnreadableAttachment) {
            return back()->with('error', __('jobs.attachments.flash.unreadable'));
        } catch (CheckInNotOpen) {
            return redirect()->route('jobs.check-in.show', $job)->with('error', __('check_ins.flash.not_open'));
        }

        return redirect()->route('jobs.check-in.show', $job)->with('success', __('check_ins.flash.photo_added'));
    }
}
