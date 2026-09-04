<?php

namespace App\View\Components\Parts;

use App\Enums\StockMovementReason;
use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AdjustForm extends Component
{
    /**
     * @var array<string, array{label: string, help: string}>
     */
    public array $reasons;

    /**
     * @param  list<string>  $suppliers
     */
    public function __construct(
        public Part $part,
        public array $suppliers = [],
    ) {
        $this->reasons = $this->buildReasons();
    }

    /**
     * @return array<string, array{label: string, help: string}>
     */
    private function buildReasons(): array
    {
        $reasons = [];

        foreach (StockMovementReason::recordableByHand() as $reason) {
            $reasons[$reason->value] = [
                'label' => $reason->label(),
                'help' => __('parts.adjust.help.'.$reason->value),
            ];
        }

        return $reasons;
    }

    public function render(): View
    {
        return view('components.parts.adjust-form');
    }
}
