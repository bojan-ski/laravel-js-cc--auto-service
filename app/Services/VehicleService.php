<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vehicle;

class VehicleService
{
    /**
     * @return array<int, string>
     */
    public function pickerOptions(): array
    {
        return Vehicle::query()
            ->with(['customer' => fn ($query) => $query->withTrashed()])
            ->orderBy('registration_plate')
            ->get()
            ->mapWithKeys(fn (Vehicle $vehicle): array => [
                $vehicle->getKey() => __('vehicles.picker.option', [
                    'plate' => $vehicle->registration_plate,
                    'vehicle' => $vehicle->make.' '.$vehicle->model,
                    'owner' => $vehicle->customer->trashed()
                        ? __('vehicles.picker.owner_deleted', ['name' => $vehicle->customer->name])
                        : $vehicle->customer->name,
                ]),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Customer $customer, array $attributes): Vehicle
    {
        return $customer->vehicles()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Vehicle $vehicle, array $attributes): Vehicle
    {
        $vehicle->update($attributes);

        return $vehicle;
    }

    public function delete(Vehicle $vehicle): void
    {
        $vehicle->delete();
    }

    public function reassign(Vehicle $vehicle, Customer $newOwner): Vehicle
    {
        $vehicle->customer()->associate($newOwner);
        $vehicle->save();

        return $vehicle;
    }

    public function restore(Vehicle $vehicle): Vehicle
    {
        $vehicle->restore();

        return $vehicle;
    }
}
