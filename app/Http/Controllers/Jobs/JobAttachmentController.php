<?php

namespace App\Http\Controllers\Jobs;

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
        Gate::authorize('delete', $attachment);

        $jobId = $attachment->job_id;

        $this->attachments->delete($attachment);

        return redirect()
            ->route('jobs.show', $jobId)
            ->with('success', __('jobs.attachments.flash.deleted'));
    }
}
