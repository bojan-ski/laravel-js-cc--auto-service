<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\PartIndexRequest;
use App\Services\AppointmentService;
use App\Services\JobService;
use App\Services\PartService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
        private JobService $jobs,
        private PartService $parts,
    ) {}

    public function __invoke(): View
    {
        $today = CarbonImmutable::today();

        return view('pages.dashboard', [
            'today' => $today,
            'appointments' => $this->appointments->scheduledOn($today),
            'jobs' => $this->jobs->active(),
            'jobCount' => $this->jobs->countActive(),
            'parts' => $this->parts->lowStock(),
            'partCount' => $this->parts->countLowStock(),
            'lowStockUrl' => route('parts.index', ['filter' => PartIndexRequest::LOW_STOCK]),
        ]);
    }
}
