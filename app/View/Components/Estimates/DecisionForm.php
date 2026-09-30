<?php

namespace App\View\Components\Estimates;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DecisionForm extends Component
{
    /**
     * @var array<string, array{label: string}>
     */
    public array $decisions;

    /**
     * @var array<string, array{label: string}>
     */
    public array $methods;

    public function __construct(public Estimate $estimate)
    {
        $this->decisions = [
            EstimateStatus::Approved->value => ['label' => __('estimates.decision.approve')],
            EstimateStatus::Declined->value => ['label' => __('estimates.decision.decline')],
        ];

        $this->methods = [];

        foreach (DecisionMethod::cases() as $method) {
            $this->methods[$method->value] = ['label' => $method->label()];
        }
    }

    public function render(): View
    {
        return view('components.estimates.decision-form');
    }
}
