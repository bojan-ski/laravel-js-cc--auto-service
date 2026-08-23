<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\AppointmentIndexRequest;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
    ) {}

    public function index(AppointmentIndexRequest $request): View
    {
        Gate::authorize('viewAny', Appointment::class);

        $filters = $request->filters();
        $appointments = $this->appointments->paginateFiltered($filters);
        $days = $this->appointments->daysInWindow($filters);

        return view('pages.appointments.index', [
            'appointments' => $appointments,
            'groups' => $this->appointments->groupByDay($appointments->items(), $days),
            'filters' => $filters,
            'hasAny' => $this->appointments->hasAny(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Appointment::class);

        return view('pages.appointments.create', [
            'vehicleOptions' => $this->appointments->vehicleOptions(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Appointment::class);

        $vehicle = Vehicle::findOrFail($request->integer('vehicle_id'));
        $appointment = $this->appointments->create($vehicle, $request->user(), $request->validated());

        return $this->redirectToDay($appointment)
            ->with('success', __('appointments.flash.created', ['plate' => $vehicle->registration_plate]))
            ->with($this->overlapWarning($appointment));
    }

    public function edit(Appointment $appointment): View
    {
        Gate::authorize('update', $appointment);

        $appointment->load('vehicle', 'customer');

        return view('pages.appointments.edit', ['appointment' => $appointment]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('update', $appointment);

        $this->appointments->reschedule($appointment, $request->validated());
        $appointment->load('vehicle');

        return $this->redirectToDay($appointment)
            ->with('success', __('appointments.flash.updated', ['plate' => $appointment->vehicle->registration_plate]))
            ->with($this->overlapWarning($appointment));
    }

    private function redirectToDay(Appointment $appointment): RedirectResponse
    {
        return redirect()->route('appointments.index', [
            'date' => $appointment->scheduled_at->format('Y-m-d'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function overlapWarning(Appointment $appointment): array
    {
        $count = $this->appointments->overlapping(
            $appointment->scheduled_at,
            $appointment->duration_minutes,
            $appointment->getKey(),
        )->count();

        return $count > 0
            ? ['warning' => trans_choice('appointments.overlap.warning', $count, ['count' => $count])]
            : [];
    }
}
