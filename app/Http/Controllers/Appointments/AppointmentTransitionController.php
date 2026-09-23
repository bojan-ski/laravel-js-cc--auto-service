<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AppointmentTransitionController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
    ) {}

    public function noShow(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('markNoShow', $appointment);

        $this->appointments->markNoShow($appointment);

        return $this->redirectToAppointment($appointment, 'no_show');
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);

        $this->appointments->cancel($appointment);

        return $this->redirectToAppointment($appointment, 'cancelled');
    }

    private function redirectToAppointment(Appointment $appointment, string $flashKey): RedirectResponse
    {
        $appointment->load('vehicle');

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', __("appointments.flash.{$flashKey}", [
                'plate' => $appointment->vehicle->registration_plate,
            ]));
    }
}
