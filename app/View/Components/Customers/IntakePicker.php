<?php

namespace App\View\Components\Customers;

use App\Enums\Locale;
use App\Http\Resources\CustomerLookupResource;
use App\Services\CustomerIntakeService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IntakePicker extends Component
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $picked;

    /**
     * @var array{one: string, few: string, other: string}
     */
    public array $resultTemplates;

    /**
     * @param  array<int, string>  $vehicleOptions
     */
    public function __construct(
        CustomerIntakeService $intake,
        public array $vehicleOptions = [],
    ) {
        $customerId = request()->old('customer_id');
        $customer = $intake->picked(filled($customerId) ? (int) $customerId : null);

        $this->picked = $customer === null ? null : CustomerLookupResource::make($customer)->resolve(request());
        $this->resultTemplates = Locale::pluralTemplates('customers.intake.results');
    }

    public function render(): View
    {
        return view('components.customers.intake-picker');
    }
}
