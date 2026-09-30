<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\CheckInNotOpen;
use App\Exceptions\UnreadableAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreAttachmentRequest;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobAttachmentController extends Controller
{
    public function __construct(
        private AttachmentService $attachments,
    ) {}

    public function store(StoreAttachmentRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('upload', $job);

        try {
            $this->attachments->upload($job, $request->file('file'), $request->user());
        } catch (UnreadableAttachment) {
            return back()->with('error', __('jobs.attachments.flash.unreadable'));
        }

        return redirect()
            ->route('jobs.show', $job)
            ->with('success', __('jobs.attachments.flash.uploaded'));
    }

    public function destroy(JobAttachment $attachment): RedirectResponse
    {
        if ($attachment->isCheckInPhoto()) {
            $attachment->loadMissing('job.checkIn');
        }

        Gate::authorize('delete', $attachment);

        $back = $attachment->isCheckInPhoto() ? 'jobs.check-in.show' : 'jobs.show';

        try {
            $this->attachments->delete($attachment);
        } catch (CheckInNotOpen) {
            return redirect()->route($back, $attachment->job_id)->with('error', __('check_ins.flash.not_open'));
        }

        return redirect()
            ->route($back, $attachment->job_id)
            ->with('success', $attachment->isCheckInPhoto() ? __('check_ins.flash.photo_deleted') : __('jobs.attachments.flash.deleted'));
    }
}
