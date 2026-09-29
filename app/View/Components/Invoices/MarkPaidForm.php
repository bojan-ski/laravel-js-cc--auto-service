<?php

namespace App\View\Components\Invoices;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MarkPaidForm extends Component
{
    /**
     * @var array<string, string>
     */
    public array $methods;

    public function __construct(public Invoice $invoice)
    {
        $this->methods = PaymentMethod::options();
    }

    public function render(): View
    {
        return view('components.invoices.mark-paid-form');
    }
}
