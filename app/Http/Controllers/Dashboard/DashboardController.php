<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\PartIndexRequest;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use App\Services\JobService;
use App\Services\PartService;
use App\Services\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
        private JobService $jobs,
        private PartService $parts,
        private ReminderService $reminders,
    ) {}

    public function __invoke(): View
    {
        $today = CarbonImmutable::today();
        $showDueSoon = Gate::allows('viewDueSoon', Vehicle::class);

        return view('pages.dashboard', [
            'today' => $today,
            'appointments' => $this->appointments->scheduledOn($today),
            'jobs' => $this->jobs->active(),
            'jobCount' => $this->jobs->countActive(),
            'parts' => $this->parts->lowStock(),
            'partCount' => $this->parts->countLowStock(),
            'lowStockUrl' => route('parts.index', ['filter' => PartIndexRequest::LOW_STOCK]),
            'dueSoon' => $showDueSoon ? $this->reminders->dueSoon($today) : null,
            'dueSoonCount' => $showDueSoon ? $this->reminders->countDueSoon($today) : null,
        ]);
    }
}
