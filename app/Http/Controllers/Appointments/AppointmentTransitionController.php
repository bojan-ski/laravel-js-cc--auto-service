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

    public function arrive(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('markArrived', $appointment);

        $this->appointments->markArrived($appointment);

        return $this->redirectToDay($appointment, 'arrived');
    }

    public function noShow(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('markNoShow', $appointment);

        $this->appointments->markNoShow($appointment);

        return $this->redirectToDay($appointment, 'no_show');
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);

        $this->appointments->cancel($appointment);

        return $this->redirectToDay($appointment, 'cancelled');
    }

    private function redirectToDay(Appointment $appointment, string $flashKey): RedirectResponse
    {
        $appointment->load('vehicle');

        return redirect()
            ->route('appointments.index', ['date' => $appointment->scheduled_at->format('Y-m-d')])
            ->with('success', __("appointments.flash.{$flashKey}", [
                'plate' => $appointment->vehicle->registration_plate,
            ]));
    }
}
