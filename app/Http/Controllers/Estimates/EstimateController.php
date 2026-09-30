<?php

namespace App\Http\Controllers\Estimates;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EstimateController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function show(int $estimate): View
    {
        Gate::authorize('viewAny', Estimate::class);

        $record = Estimate::query()
            ->with(['job.customer', 'job.vehicle', 'lines', 'createdBy', 'decidedBy'])
            ->findOrFail($estimate);

        Gate::authorize('view', $record);

        $history = $this->estimates->historyFor($record);

        return view('pages.estimates.show', [
            'estimate' => $record,
            'totals' => $record->storedTotals(),
            'history' => $history->count() > 1 ? $history : new Collection,
            'canEmail' => Gate::allows('email', $record) && filled($record->job->customer->email),
        ]);
    }
}
