<?php

namespace App\Http\Requests;

use App\Models\Vehicle;

class StoreVehicleRequest extends VehicleRequest
{
    protected function vehicle(): ?Vehicle
    {
        return null;
    }
}
