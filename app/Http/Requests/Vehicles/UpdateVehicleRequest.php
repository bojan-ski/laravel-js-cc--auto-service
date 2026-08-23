<?php

namespace App\Http\Requests\Vehicles;

use App\Models\Vehicle;

class UpdateVehicleRequest extends VehicleRequest
{
    protected function vehicle(): ?Vehicle
    {
        return $this->route('vehicle');
    }
}
