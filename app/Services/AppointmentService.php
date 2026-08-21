<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Vehicle;

class AppointmentService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Vehicle $vehicle, User $bookedBy, array $attributes): Appointment
    {
        $appointment = new Appointment;
        $appointment->fill($attributes);

        $appointment->customer_id = $vehicle->customer_id;
        $appointment->vehicle_id = $vehicle->getKey();
        $appointment->created_by = $bookedBy->getKey();
        $appointment->status = AppointmentStatus::Booked;

        $appointment->save();

        return $appointment;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reschedule(Appointment $appointment, array $attributes): Appointment
    {
        if (! $appointment->canBeRescheduled()) {
            throw new InvalidAppointmentTransition($appointment, 'reschedule');
        }

        $appointment->update($attributes);

        return $appointment;
    }

    public function cancel(Appointment $appointment): Appointment
    {
        return $this->transition(
            $appointment,
            $appointment->canBeCancelled(),
            'cancel',
            AppointmentStatus::Cancelled,
            'cancelled_at',
        );
    }

    public function markArrived(Appointment $appointment): Appointment
    {
        return $this->transition(
            $appointment,
            $appointment->canBeMarkedArrived(),
            'mark arrived',
            AppointmentStatus::Arrived,
            'arrived_at',
        );
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        return $this->transition(
            $appointment,
            $appointment->canBeMarkedNoShow(),
            'mark no-show',
            AppointmentStatus::NoShow,
            'no_show_at',
        );
    }

    private function transition(
        Appointment $appointment,
        bool $permitted,
        string $name,
        AppointmentStatus $status,
        string $timestampColumn,
    ): Appointment {
        if (! $permitted) {
            throw new InvalidAppointmentTransition($appointment, $name);
        }

        $appointment->status = $status;
        $appointment->{$timestampColumn} = now();
        $appointment->save();

        return $appointment;
    }
}
