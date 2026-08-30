<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\InvoiceNotIssuable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\IssueInvoiceRequest;
use App\Models\Job;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobInvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function create(Job $job): View
    {
        Gate::authorize('issue', $job);

        $job->load(['customer', 'vehicle', 'items']);

        return view('pages.invoices.issue', [
            'job' => $job,
            'totals' => $this->invoices->totalsFor($job),
        ]);
    }

    public function store(IssueInvoiceRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('issue', $job);

        try {
            $invoice = $this->invoices->issue($job, $request->user(), $request->dueAt());
        } catch (InvoiceNotIssuable) {
            return redirect()
                ->route('jobs.show', $job)
                ->with('error', __('invoices.flash.not_issuable'));
        }

        return redirect()
            ->route('jobs.show', $job)
            ->with('success', __('invoices.flash.issued', ['number' => $invoice->number]));
    }
}
