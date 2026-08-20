<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vehicle;

class VehicleService
{
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
