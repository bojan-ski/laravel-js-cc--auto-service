<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\InvoiceIndexRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(InvoiceIndexRequest $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->filters();

        return view('pages.invoices.index', [
            'invoices' => $this->invoices->paginateFiltered($filters),
            'counts' => $this->invoices->countByStatus($filters),
            'filters' => $filters,
            'hasAny' => $this->invoices->hasAny(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['job.customer', 'job.vehicle', 'issuedBy', 'voidedBy']);

        return view('pages.invoices.show', [
            'invoice' => $invoice,
            'totals' => $invoice->storedTotals(),
            'lineItems' => $invoice->isVoid()
                ? new Collection
                : $invoice->job->items()->visibleTo($request->user())->orderBy('id')->get(),
        ]);
    }
}
