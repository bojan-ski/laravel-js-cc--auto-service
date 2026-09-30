<?php

namespace App\View\Components\CheckIns;

use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Summary extends Component
{
    public string $state;

    public ?string $photos;

    public ?string $linkLabel = null;

    public ?string $linkUrl = null;

    public function __construct(
        public Job $job,
    ) {
        $checkIn = $job->checkIn;

        $this->state = match (true) {
            $checkIn === null => __('check_ins.summary.not_recorded'),
            $checkIn->isSigned() => __('check_ins.summary.signed', [
                'date' => $checkIn->signed_at->isoFormat('D MMM HH:mm'),
                'method' => $checkIn->signature_method->summaryLabel(),
            ]),
            default => __('check_ins.summary.unsigned'),
        };

        $count = (int) $checkIn?->photos_count;
        $this->photos = $count > 0 ? trans_choice('check_ins.summary.photos', $count) : null;

        $this->linkLabel = match (true) {
            $checkIn === null => $job->acceptsCheckIn() ? __('check_ins.summary.record') : null,
            $job->acceptsCheckIn() || $job->acceptsCheckInSignature() => __('check_ins.summary.open'),
            default => __('check_ins.summary.view'),
        };

        $this->linkUrl = $this->linkLabel === null ? null : route('jobs.check-in.show', $job);
    }

    public function render(): View
    {
        return view('components.check-ins.summary');
    }
}
