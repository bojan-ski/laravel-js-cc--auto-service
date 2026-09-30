<?php

namespace App\View\Components\Estimates;

use App\Models\Estimate;
use App\Services\Money;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Warning extends Component
{
    /**
     * @param  array{estimate: Estimate, warning: 'over'|'not_approved'|'declined'|null, over_by: ?string}  $summary
     */
    public function __construct(
        public array $summary,
    ) {}

    public function text(): string
    {
        $number = $this->summary['estimate']->number;

        return match ($this->summary['warning']) {
            'over' => __('estimates.warning.over', ['amount' => Money::format($this->summary['over_by']), 'number' => $number]),
            'declined' => __('estimates.warning.declined', ['number' => $number]),
            default => __('estimates.warning.not_approved', ['number' => $number]),
        };
    }

    public function render(): View
    {
        return view('components.estimates.warning');
    }
}
