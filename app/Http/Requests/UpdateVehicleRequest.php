<?php

namespace App\Http\Requests;

use App\Models\Vehicle;

class UpdateVehicleRequest extends VehicleRequest
{
    protected function vehicle(): ?Vehicle
    {
        return $this->route('vehicle');
    }
}
