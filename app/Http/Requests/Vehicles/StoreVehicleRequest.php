<?php

namespace App\Http\Requests\Vehicles;

use App\Models\Vehicle;

class StoreVehicleRequest extends VehicleRequest
{
    protected function vehicle(): ?Vehicle
    {
        return null;
    }
}
