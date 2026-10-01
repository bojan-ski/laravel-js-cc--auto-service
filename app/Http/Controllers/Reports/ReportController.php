<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRequest;
use App\Models\Part;
use App\Services\ReportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(ReportRequest $request): View
    {
        ['from' => $from, 'to' => $to] = $request->period();
        $withMargin = Gate::allows('viewCostPrice', Part::class);

        return view('pages.reports.index', [
            'from' => $from,
            'to' => $to,
            'revenue' => $this->reports->revenue($from, $to),
            'vat' => $this->reports->vatByRate($from, $to),
            'payments' => $this->reports->payments($from, $to),
            'labor' => $this->reports->laborByMechanic($from, $to),
            'parts' => $this->reports->partsSold($from, $to, $withMargin),
            'withMargin' => $withMargin,
        ]);
    }

    public function export(ReportRequest $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $request->period();

        return response()->streamDownload(function () use ($from, $to): void {
            foreach ($this->reports->exportLines($from, $to) as $line) {
                echo $line;
            }
        }, "invoices-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
