<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentOverlapRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AppointmentOverlapController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
    ) {}

    public function __invoke(AppointmentOverlapRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('create', Appointment::class);

        return AppointmentResource::collection($this->appointments->overlapping(
            $request->start(),
            $request->durationMinutes(),
            $request->excludeId(),
        ));
    }
}
