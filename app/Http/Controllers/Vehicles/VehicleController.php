<?php

namespace App\Http\Controllers\Vehicles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function __construct(
        private VehicleService $vehicles,
    ) {}

    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        $vehicle->load(['customer' => fn ($query) => $query->withTrashed()]);

        return view('pages.vehicles.show', ['vehicle' => $vehicle]);
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        $vehicle->load(['customer' => fn ($query) => $query->withTrashed()]);

        return view('pages.vehicles.edit', ['vehicle' => $vehicle]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $this->vehicles->update($vehicle, $request->validated());

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', __('vehicles.flash.updated', ['plate' => $vehicle->registration_plate]));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        $owner = $vehicle->customer()->withTrashed()->firstOrFail();

        $this->vehicles->delete($vehicle);

        return redirect()->to($owner->trashed() ? route('customers.index') : route('customers.show', $owner))
            ->with('success', __('vehicles.flash.deleted', ['plate' => $vehicle->registration_plate]));
    }

    public function restore(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('restore', $vehicle);

        $owner = $vehicle->customer()->withTrashed()->firstOrFail();

        $this->vehicles->restore($vehicle);

        return redirect()->to($owner->trashed() ? route('vehicles.show', $vehicle) : route('customers.show', $owner))
            ->with('success', __('vehicles.flash.restored', ['plate' => $vehicle->registration_plate]));
    }
}
