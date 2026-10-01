<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\CheckInNotOpen;
use App\Exceptions\UnreadableSignature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\SignCheckInRequest;
use App\Models\Job;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckInSignatureController extends Controller
{
    public function __construct(
        private CheckInService $checkIns,
    ) {}

    public function store(SignCheckInRequest $request, Job $job): RedirectResponse
    {
        abort_if($job->checkIn === null, 404);

        Gate::authorize('signCheckIn', $job);

        try {
            $checkIn = $this->checkIns->sign($job, $request->user(), $request->signatureMethod(), $request->validated('signed_by_name'), $request->validated('signature'));
        } catch (UnreadableSignature) {
            return back()->withErrors(['signature' => __('check_ins.sign.errors.unreadable')])->withInput();
        } catch (CheckInNotOpen) {
            return redirect()->route('jobs.check-in.show', $job)->with('error', __('check_ins.flash.not_open'));
        }

        return redirect()->route('jobs.check-in.show', $job)->with('success', __('check_ins.flash.signed', ['name' => $checkIn->signed_by_name]));
    }

    public function show(Job $job): StreamedResponse
    {
        Gate::authorize('view', $job);

        abort_unless($job->checkIn?->hasDrawnSignature() ?? false, 404);

        $disk = Storage::disk(config('attachments.disk'));

        abort_unless($disk->exists($job->checkIn->signature_path), 404);

        return $disk->response($job->checkIn->signature_path, null, ['Content-Type' => 'image/png']);
    }
}
