<?php

namespace App\Http\Controllers\Vehicles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\ReassignVehicleRequest;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\CustomerService;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleOwnerController extends Controller
{
    public function __construct(
        private VehicleService $vehicles,
        private CustomerService $customers,
    ) {}

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('reassign', $vehicle);

        $vehicle->load(['customer' => fn ($query) => $query->withTrashed()]);

        return view('pages.vehicles.reassign', [
            'vehicle' => $vehicle,
            'customers' => $this->customers->selectable($vehicle->customer),
        ]);
    }

    public function update(ReassignVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('reassign', $vehicle);

        $newOwner = Customer::findOrFail($request->validated('customer_id'));

        $this->vehicles->reassign($vehicle, $newOwner);

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', __('vehicles.flash.reassigned', [
                'plate' => $vehicle->registration_plate,
                'name' => $newOwner->name,
            ]));
    }
}
