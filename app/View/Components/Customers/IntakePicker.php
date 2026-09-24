<?php

namespace App\View\Components\Customers;

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
     * @param  array<int, string>  $vehicleOptions
     */
    public function __construct(
        CustomerIntakeService $intake,
        public array $vehicleOptions = [],
    ) {
        $customerId = request()->old('customer_id');
        $customer = $intake->picked(filled($customerId) ? (int) $customerId : null);

        $this->picked = $customer === null ? null : CustomerLookupResource::make($customer)->resolve(request());
    }

    public function render(): View
    {
        return view('components.customers.intake-picker');
    }
}
