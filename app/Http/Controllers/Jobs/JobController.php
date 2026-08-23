<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\JobIndexRequest;
use App\Http\Requests\Jobs\StoreJobRequest;
use App\Models\Appointment;
use App\Models\Job;
use App\Models\Vehicle;
use App\Services\JobService;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobController extends Controller
{
    public function __construct(
        private JobService $jobs,
        private VehicleService $vehicles,
    ) {}

    public function index(JobIndexRequest $request): View
    {
        Gate::authorize('viewAny', Job::class);

        $filters = $request->filters();

        return view('pages.jobs.index', [
            'jobs' => $this->jobs->paginateFiltered($filters),
            'counts' => $this->jobs->countByStatus($filters),
            'filters' => $filters,
            'hasAny' => $this->jobs->hasAny(),
        ]);
    }

    public function create(Request $request): View
    {
        if (! $request->filled('appointment')) {
            Gate::authorize('create', Job::class);

            return view('pages.jobs.create', ['vehicleOptions' => $this->vehicles->pickerOptions()]);
        }

        $appointment = Appointment::query()
            ->with(['vehicle', 'customer', 'createdBy'])
            ->findOrFail($request->integer('appointment'));

        Gate::authorize('markArrived', $appointment);

        return view('pages.jobs.create', ['appointment' => $appointment]);
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $appointment = $request->appointment();

        return $appointment === null
            ? $this->storeWalkIn($request)
            : $this->storeFromAppointment($request, $appointment);
    }

    private function storeWalkIn(StoreJobRequest $request): RedirectResponse
    {
        Gate::authorize('create', Job::class);

        $vehicle = Vehicle::findOrFail($request->integer('vehicle_id'));
        $job = $this->jobs->createWalkIn($vehicle, $request->user(), $request->validated());

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with($this->flashes($job));
    }

    private function storeFromAppointment(StoreJobRequest $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('markArrived', $appointment);

        $job = $this->jobs->createFromAppointment($appointment, $request->user(), $request->validated());

        return redirect()
            ->route('appointments.index', ['date' => $appointment->scheduled_at->format('Y-m-d')])
            ->with($this->flashes($job));
    }

    /**
     * @return array<string, string>
     */
    private function flashes(Job $job): array
    {
        $flashes = ['success' => __('jobs.flash.created', ['reference' => $job->reference])];

        if ($job->mileageInIsBelowVehicleMileage()) {
            $flashes['warning'] = __('jobs.flash.mileage_lower', [
                'entered' => number_format($job->mileage_in),
                'stored' => number_format($job->vehicle->mileage),
            ]);
        }

        return $flashes;
    }
}
