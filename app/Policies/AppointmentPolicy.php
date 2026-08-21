<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can reschedule the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->isFrontOffice() && $appointment->canBeRescheduled();
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->isFrontOffice() && $appointment->canBeCancelled();
    }

    /**
     * Determine whether the user can mark the model arrived.
     */
    public function markArrived(User $user, Appointment $appointment): bool
    {
        return $appointment->canBeMarkedArrived();
    }

    /**
     * Determine whether the user can mark the model a no-show.
     */
    public function markNoShow(User $user, Appointment $appointment): bool
    {
        return $appointment->canBeMarkedNoShow();
    }
}
