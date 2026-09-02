<?php

namespace App\Http\Controllers\Invoices;

use App\Exceptions\InvalidInvoiceTransition;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceVoidController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function create(Invoice $invoice): View
    {
        Gate::authorize('void', $invoice);

        $invoice->load('job');

        return view('pages.invoices.void', [
            'invoice' => $invoice,
            'totals' => $this->invoices->storedTotals($invoice),
        ]);
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('void', $invoice);

        $invoice->load('job');

        try {
            $this->invoices->void($invoice, $request->user());
        } catch (InvalidInvoiceTransition) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', __('invoices.flash.not_voidable'));
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', __('invoices.flash.voided', [
                'number' => $invoice->number,
                'reference' => $invoice->job->reference,
            ]));
    }
}
