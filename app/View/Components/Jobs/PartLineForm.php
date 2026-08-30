<?php

namespace App\View\Components\Jobs;

use App\Enums\JobItemType;
use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PartLineForm extends Component
{
    /**
     * @param  array<int, string>  $partOptions
     * @param  array<int, string>  $userOptions
     */
    public function __construct(
        public Job $job,
        public array $partOptions = [],
        public bool $showPricing = false,
        public array $userOptions = [],
    ) {}

    public function quantityStep(): string
    {
        return JobItemType::Part->quantityStep();
    }

    public function quantityMin(): string
    {
        return JobItemType::Part->quantityMin();
    }

    public function render(): View
    {
        return view('components.jobs.part-line-form');
    }
}
