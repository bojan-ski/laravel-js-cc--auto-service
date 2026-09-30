<?php

namespace App\View\Components\Ui;

use App\Services\Money as MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Money extends Component
{
    public function __construct(
        public string|int|float $value,
    ) {}

    public function formatted(): string
    {
        return MoneyFormatter::format($this->value);
    }

    public function render(): View
    {
        return view('components.ui.money');
    }
}
