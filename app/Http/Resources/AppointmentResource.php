<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduledAt' => $this->scheduled_at->toIso8601String(),
            'durationMinutes' => $this->duration_minutes,
            'endsAt' => $this->endsAt()->toIso8601String(),
            'plate' => $this->vehicle->registration_plate,
            'vehicleLabel' => $this->vehicle->make.' '.$this->vehicle->model,
            'customerName' => $this->customer->name,
        ];
    }
}
