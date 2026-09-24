<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\IdentifierNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerLookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $term = IdentifierNormalizer::normalize($request->string('q')->toString());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'vehicles' => $this->vehicles->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'plate' => $vehicle->registration_plate,
                'label' => $vehicle->make.' '.$vehicle->model,
            ])->values(),
            'matchedVehicleId' => $term === '' ? null : $this->vehicles->first(
                fn (Vehicle $vehicle): bool => str_contains(IdentifierNormalizer::normalize($vehicle->registration_plate), $term),
            )?->id,
        ];
    }
}
