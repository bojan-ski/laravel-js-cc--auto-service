<?php

namespace App\Http\Controllers\Vehicles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerVehicleController extends Controller
{
    public function __construct(
        private VehicleService $vehicles,
    ) {}

    public function create(Customer $customer): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('pages.vehicles.create', ['customer' => $customer]);
    }

    public function store(StoreVehicleRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);

        $vehicle = $this->vehicles->create($customer, $request->validated());

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', __('vehicles.flash.created', ['plate' => $vehicle->registration_plate]));
    }
}
