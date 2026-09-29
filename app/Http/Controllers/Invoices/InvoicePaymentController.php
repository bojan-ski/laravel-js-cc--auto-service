<?php

namespace App\Http\Controllers\Invoices;

use App\Exceptions\InvalidInvoiceTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\MarkInvoicePaidRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InvoicePaymentController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function markPaid(MarkInvoicePaidRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('markPaid', $invoice);

        try {
            $this->invoices->markPaid($invoice, $request->paymentMethod());
        } catch (InvalidInvoiceTransition) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', __('invoices.flash.not_payable'));
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', __('invoices.flash.paid', ['number' => $invoice->number, 'method' => $request->paymentMethod()->label()]));
    }
}
